import json
from pathlib import Path

from hissa_xbrl_worker.cli import main


FIXTURE = Path(__file__).parent / "fixtures" / "minimal-valid" / "minimal-instance.xbrl"
INVALID_FIXTURE = Path(__file__).parent / "fixtures" / "invalid" / "malformed.xml"


def _lines(raw: str):
    return [json.loads(line) for line in raw.splitlines() if line.strip()]


def test_cli_emits_structured_logs_to_stderr_and_keeps_stdout_json(capsys):
    assert main([
        "--input", str(FIXTURE), "--filing-id", "logging-cli", "--correlation-id", "job-123"
    ]) == 0

    captured = capsys.readouterr()
    payload = json.loads(captured.out)
    logs = _lines(captured.err)

    assert payload["status"] == "SUCCESS"
    assert "correlation_id" not in payload
    assert logs
    assert all(item["correlation_id"] == "job-123" for item in logs)
    assert all(item["schema"] == "hissa_parser_log" for item in logs)
    assert "parser.started" in {item["event"] for item in logs}
    assert "parser.completed" in {item["event"] for item in logs}
    events = {item["event"] for item in logs}
    assert {"extract.contexts.started", "extract.units.started", "extract.dimensions.started", "extract.facts.started"}.issubset(events)


def test_log_level_does_not_change_parser_stdout(capsys):
    assert main(["--input", str(FIXTURE), "--filing-id", "logging-cli", "--log-level", "ERROR"]) == 0
    error_output = capsys.readouterr().out

    assert main(["--input", str(FIXTURE), "--filing-id", "logging-cli", "--log-level", "DEBUG"]) == 0
    debug_output = capsys.readouterr().out

    assert json.loads(error_output) == json.loads(debug_output)


def test_cli_failure_keeps_stdout_json_and_stderr_structured(capsys):
    assert main(["--input", str(INVALID_FIXTURE), "--filing-id", "broken", "--correlation-id", "job-error"]) == 11

    captured = capsys.readouterr()
    payload = json.loads(captured.out)
    logs = _lines(captured.err)

    assert payload["status"] == "FAILED"
    assert payload["errors"][0]["code"] == "UNSUPPORTED_DOCUMENT"
    assert "arelle.load.failed" in {item["event"] for item in logs}
    assert "parser.failed" in {item["event"] for item in logs}
