from pathlib import Path

from hissa_xbrl_worker.contracts import ParseRequest
from hissa_xbrl_worker.errors import WorkerError


def test_parse_request_is_immutable_value_object():
    request = ParseRequest(Path("file.xbrl"), "filing-1")

    assert request.input_path == Path("file.xbrl")
    assert request.filing_id == "filing-1"


def test_worker_error_string_returns_safe_message():
    error = WorkerError("XBRL_LOAD_ERROR", "Unable to load.", 12)

    assert str(error) == "Unable to load."
    assert error.code == "XBRL_LOAD_ERROR"
    assert error.exit_code == 12

