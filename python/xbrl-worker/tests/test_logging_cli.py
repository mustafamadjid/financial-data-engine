import json
import re
import sys
from pathlib import Path

from hissa_xbrl_worker.cli import main


FIXTURE = Path(__file__).parent / "fixtures" / "minimal-valid" / "minimal-instance.xbrl"
INVALID_FIXTURE = Path(__file__).parent / "fixtures" / "invalid" / "malformed.xml"


def _lines(raw: str):
    return [line for line in raw.splitlines() if line.strip()]


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
    assert all(re.match(r"^\d{2}:\d{2}:\d{2} (DEBUG|INFO |WARN |ERROR|CRITICAL) \[.+\] [a-z0-9_]+", line) for line in logs)
    assert any("[worker] started version=" in line for line in logs)
    assert any("[xbrl] parse_started filing=logging-cli file=minimal-instance.xbrl" in line for line in logs)
    assert any("[xbrl] parse_completed filing=logging-cli facts=" in line for line in logs)
    assert all("contexts=[" not in line and "facts=[" not in line for line in logs)


def test_log_level_does_not_change_parser_stdout(capsys):
    assert main(["--input", str(FIXTURE), "--filing-id", "logging-cli", "--log-level", "ERROR"]) == 0
    error_output = capsys.readouterr().out

    assert main(["--input", str(FIXTURE), "--filing-id", "logging-cli", "--log-level", "DEBUG"]) == 0
    debug_output = capsys.readouterr().out

    assert json.loads(error_output) == json.loads(debug_output)


def test_cli_keeps_terminal_output_to_formatted_logs_only(capsys, monkeypatch):
    monkeypatch.setattr(sys.stdout, "isatty", lambda: True)

    assert main([
        "--input", str(FIXTURE), "--filing-id", "terminal-only"
    ]) == 0

    captured = capsys.readouterr()
    assert captured.out == ""
    assert "[xbrl] parse_completed filing=terminal-only" in captured.err
    assert "{\"contexts\"" not in captured.err


def test_cli_failure_keeps_stdout_json_and_stderr_structured(capsys):
    assert main(["--input", str(INVALID_FIXTURE), "--filing-id", "broken", "--correlation-id", "job-error"]) == 11

    captured = capsys.readouterr()
    payload = json.loads(captured.out)
    logs = _lines(captured.err)

    assert payload["status"] == "FAILED"
    assert payload["errors"][0]["code"] == "UNSUPPORTED_DOCUMENT"
    assert any("[arelle] arelle_load_failed" in line for line in logs)
    assert any("[xbrl] parse_failed filing=broken code=UNSUPPORTED_DOCUMENT" in line for line in logs)
