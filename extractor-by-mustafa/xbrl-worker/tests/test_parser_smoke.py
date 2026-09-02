import json
from pathlib import Path
from hissa_xbrl_worker.contracts import ParseRequest
from hissa_xbrl_worker.parser import parse_filing
from hissa_xbrl_worker.serialization import serialize_payload

FIXTURE = Path(__file__).parent / "fixtures" / "minimal-valid" / "minimal-instance.xbrl"

def test_parse_minimal_is_deterministic():
    first = parse_filing(ParseRequest(FIXTURE, "filing_01"))
    second = parse_filing(ParseRequest(FIXTURE, "filing_01"))
    assert first["status"] == "SUCCESS"
    assert serialize_payload(first) == serialize_payload(second)
    assert first["counts"]["contexts"] == len(first["contexts"])
    assert first["facts"][0]["source_concept"] == "Assets"

def test_cli_payload_is_json():
    assert json.loads(serialize_payload(parse_filing(ParseRequest(FIXTURE, "f"))))["status"] == "SUCCESS"
