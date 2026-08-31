import json
from pathlib import Path
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
