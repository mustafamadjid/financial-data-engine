from contextlib import contextmanager
from pathlib import Path
from unittest.mock import patch

import pytest

from hissa_xbrl_worker.contracts import ParseRequest
from hissa_xbrl_worker.errors import WorkerError
from hissa_xbrl_worker.parser import _runtime, build_failure_payload, parse_filing


def test_runtime_contains_worker_arelle_and_python_metadata():
    runtime = _runtime()

    assert runtime["worker_version"] == "1.0.0"
    assert runtime["arelle_package"] == "arelle-release"
    assert "arelle_version" in runtime
    assert "python_version" in runtime


def test_runtime_uses_unknown_arelle_version_when_metadata_lookup_fails():
    with patch("importlib.metadata.version", side_effect=Exception("metadata unavailable")):
        assert _runtime()["arelle_version"] == "unknown"


def test_build_failure_payload_has_stable_empty_arrays_and_error():
    payload = build_failure_payload("filing-1", Path("input.xbrl"), "INPUT_FILE_ERROR", "Unable to read.", "abc")

    assert payload["status"] == "FAILED"
    assert payload["filing_id"] == "filing-1"
    assert payload["source"] == {"path": "input.xbrl", "sha256": "abc"}
    assert payload["counts"] == {"contexts": 0, "units": 0, "dimensions": 0, "facts": 0}
    assert payload["errors"] == [{"code": "INPUT_FILE_ERROR", "message": "Unable to read."}]
    assert payload["contexts"] == payload["units"] == payload["dimensions"] == payload["facts"] == []


def test_parse_filing_rejects_missing_and_empty_files(tmp_path):
    with pytest.raises(WorkerError) as missing:
        parse_filing(ParseRequest(tmp_path / "missing.xbrl", "filing-1"))
    assert missing.value.code == "INPUT_FILE_ERROR"
    empty = tmp_path / "empty.xbrl"
    empty.touch()
    with pytest.raises(WorkerError) as blank:
        parse_filing(ParseRequest(empty, "filing-1"))
    assert blank.value.exit_code == 10


def test_parse_filing_returns_success_and_counts_records(monkeypatch, tmp_path):
    source = tmp_path / "source.xbrl"
    source.write_text("fixture", encoding="utf-8")

    @contextmanager
    def fake_loader(path):
        yield object()

    monkeypatch.setattr("hissa_xbrl_worker.parser.load_model", fake_loader)
    monkeypatch.setattr("hissa_xbrl_worker.parser.sha256_file", lambda path: "hash")
    monkeypatch.setattr("hissa_xbrl_worker.parser.extract_contexts", lambda model, filing: [{"id": 1}])
    monkeypatch.setattr("hissa_xbrl_worker.parser.build_context_id_map", lambda model, filing: {})
    monkeypatch.setattr("hissa_xbrl_worker.parser.extract_units", lambda model, filing: [{"id": 2}, {"id": 3}])
    monkeypatch.setattr("hissa_xbrl_worker.parser.build_unit_id_map", lambda model, filing: {})
    monkeypatch.setattr("hissa_xbrl_worker.parser.extract_dimensions", lambda model, filing, cmap: [])
    monkeypatch.setattr("hissa_xbrl_worker.parser.extract_facts", lambda model, filing, cmap, umap: [{"id": 4}])

    payload = parse_filing(ParseRequest(source, "filing-1"))

    assert payload["status"] == "SUCCESS"
    assert payload["source"] == {"path": str(source), "sha256": "hash"}
    assert payload["counts"] == {"contexts": 1, "units": 2, "dimensions": 0, "facts": 1}


def test_parse_filing_converts_extraction_exception_to_worker_error(monkeypatch, tmp_path):
    source = tmp_path / "source.xbrl"
    source.write_text("fixture", encoding="utf-8")

    @contextmanager
    def fake_loader(path):
        yield object()

    monkeypatch.setattr("hissa_xbrl_worker.parser.load_model", fake_loader)
    monkeypatch.setattr("hissa_xbrl_worker.parser.sha256_file", lambda path: "hash")
    monkeypatch.setattr("hissa_xbrl_worker.parser.extract_contexts", lambda model, filing: (_ for _ in ()).throw(RuntimeError("boom")))

    with pytest.raises(WorkerError) as exc:
        parse_filing(ParseRequest(source, "filing-1"))

    assert exc.value.code == "EXTRACTION_ERROR"
    assert exc.value.exit_code == 13


def test_parse_filing_propagates_worker_error_from_extractor(monkeypatch, tmp_path):
    source = tmp_path / "source.xbrl"
    source.write_text("fixture", encoding="utf-8")

    @contextmanager
    def fake_loader(path):
        yield object()

    monkeypatch.setattr("hissa_xbrl_worker.parser.load_model", fake_loader)
    monkeypatch.setattr("hissa_xbrl_worker.parser.sha256_file", lambda path: "hash")
    monkeypatch.setattr("hissa_xbrl_worker.parser.extract_contexts", lambda model, filing: (_ for _ in ()).throw(WorkerError("CUSTOM", "custom", 99)))

    with pytest.raises(WorkerError) as exc:
        parse_filing(ParseRequest(source, "filing-1"))

    assert exc.value.code == "CUSTOM"


def test_parse_filing_propagates_hash_error_as_input_error(monkeypatch, tmp_path):
    source = tmp_path / "source.xbrl"
    source.write_text("fixture", encoding="utf-8")
    monkeypatch.setattr("hissa_xbrl_worker.parser.sha256_file", lambda path: (_ for _ in ()).throw(OSError("denied")))

    with pytest.raises(WorkerError) as exc:
        parse_filing(ParseRequest(source, "filing-1"))

    assert exc.value.code == "INPUT_FILE_ERROR"
    assert exc.value.exit_code == 10
