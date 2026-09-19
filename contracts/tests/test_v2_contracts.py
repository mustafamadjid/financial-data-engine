import json
from pathlib import Path


ROOT = Path(__file__).parents[1]


def load(name: str) -> dict:
    return json.loads((ROOT / "v2" / name).read_text(encoding="utf-8"))


def test_v2_schemas_are_valid_json_and_have_required_lineage():
    raw = load("raw-fact.json")
    mapping = load("mapping-rule.json")
    parser = load("parser-result.schema.json")
    normalized = load("normalized-fact.json")

    assert {"source_namespace", "source_element_id", "source_concept"}.issubset(raw["required"])
    assert {"source_namespace", "source_concept", "dimension_policy"}.issubset(mapping["required"])
    assert {"source", "runtime", "taxonomy", "counts", "contexts", "units", "dimensions", "facts", "warnings", "errors"}.issubset(parser["required"])
    assert {"value", "availability_status"}.issubset(normalized["required"])


def test_v2_lineage_fields_are_non_empty_strings():
    raw = load("raw-fact.json")
    mapping = load("mapping-rule.json")

    for schema, field in ((raw, "source_element_id"), (mapping, "source_namespace")):
        definition = schema["properties"][field]
        assert definition["type"] == "string"
        assert definition["minLength"] > 0
