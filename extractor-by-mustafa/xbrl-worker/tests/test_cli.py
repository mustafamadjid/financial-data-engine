import json
from pathlib import Path
from unittest.mock import patch

import pytest

from hissa_xbrl_worker.cli import main

FIXTURE = Path(__file__).parent / "fixtures" / "minimal-valid" / "minimal-instance.xbrl"

def test_cli_success_stdout_json(capsys):
    assert main(["--input", str(FIXTURE), "--filing-id", "cli"]) == 0
    payload = json.loads(capsys.readouterr().out)
    assert payload["status"] == "SUCCESS"

def test_cli_missing_file(capsys):
    assert main(["--input", str(FIXTURE / "missing"), "--filing-id", "cli"]) == 10
    payload = json.loads(capsys.readouterr().out)
    assert payload["errors"][0]["code"] == "INPUT_FILE_ERROR"


def test_cli_pretty_flag_preserves_json_payload_and_adds_indentation(capsys):
    assert main(["--input", str(FIXTURE), "--filing-id", "cli", "--pretty"]) == 0

    output = capsys.readouterr().out
    assert "\n  \"contexts\"" in output
    assert json.loads(output)["status"] == "SUCCESS"


@pytest.mark.parametrize("value", ["bad id", "bad/id", "x" * 129, "line\nfeed"])
def test_cli_rejects_invalid_correlation_id(value, capsys):
    assert main(["--input", str(FIXTURE), "--filing-id", "cli", "--correlation-id", value]) == 2

    payload = json.loads(capsys.readouterr().out)
    assert payload["errors"][0]["code"] == "CLI_ARGUMENT_ERROR"


def test_cli_rejects_unknown_log_level(capsys):
    assert main(["--input", str(FIXTURE), "--filing-id", "cli", "--log-level", "TRACE"]) == 2

    assert json.loads(capsys.readouterr().out)["status"] == "FAILED"


def test_cli_converts_unexpected_parser_exception_to_internal_error(capsys):
    with patch("hissa_xbrl_worker.cli.parse_filing", side_effect=RuntimeError("unexpected")):
        assert main(["--input", str(FIXTURE), "--filing-id", "cli"]) == 20

    payload = json.loads(capsys.readouterr().out)
    assert payload["errors"][0]["code"] == "INTERNAL_ERROR"


def test_cli_returns_serialization_failure_payload_when_serializer_raises(capsys):
    with patch("hissa_xbrl_worker.cli.serialize_payload", side_effect=RuntimeError("serialize")):
        assert main(["--input", str(FIXTURE), "--filing-id", "cli"]) == 14

    payload = json.loads(capsys.readouterr().out)
    assert payload["errors"][0]["code"] == "SERIALIZATION_ERROR"


def test_cli_invalid_argument_shape_returns_structured_failure(capsys):
    assert main(["--input", str(FIXTURE)]) == 2

    payload = json.loads(capsys.readouterr().out)
    assert payload["errors"][0]["code"] == "CLI_ARGUMENT_ERROR"


def test_cli_help_exits_successfully():
    with pytest.raises(SystemExit) as exc:
        main(["--help"])

    assert exc.value.code == 0
