import json
from pathlib import Path
from hissa_xbrl_worker.contracts import ParseRequest
from hissa_xbrl_worker.parser import parse_filing

ROOT = Path(__file__).parents[3]
FIXTURE = Path(__file__).parent / "fixtures" / "minimal-valid" / "minimal-instance.xbrl"

def test_required_contract_fields_and_lineage():
    result = parse_filing(ParseRequest(FIXTURE, "f"))
    context_schema = json.loads((ROOT / "contracts/v1/context.json").read_text())
    unit_schema = json.loads((ROOT / "contracts/v1/unit.json").read_text())
    for record, schema in [(x, context_schema) for x in result["contexts"]] + [(x, unit_schema) for x in result["units"]]:
        assert set(schema["required"]).issubset(record)
    contexts = {x["context_id"] for x in result["contexts"]}; units = {x["unit_id"] for x in result["units"]}
    for fact in result["facts"]:
        assert fact["context_ref"] in contexts
        assert fact["unit_ref"] is None or fact["unit_ref"] in units
