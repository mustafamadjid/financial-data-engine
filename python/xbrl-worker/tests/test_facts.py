from types import SimpleNamespace
from unittest.mock import patch

from hissa_xbrl_worker.extractors.facts import _concept, extract_facts


class QName:
    def __init__(self, namespace, local):
        self.namespaceURI = namespace
        self.localName = local


def _fact(local="Assets", raw="123.45", *, context="c1", unit="u1", numeric=True, nil=False, decimals="INF", precision=None):
    return SimpleNamespace(
        qname=QName("urn:test", local), value=raw, contextID=context, unitID=unit,
        decimals=decimals, precision=precision, isNil=nil, isNumeric=numeric,
    )


def test_concept_uses_qname_and_fallback_concept_name():
    assert _concept(_fact())[0:2] == ("Assets", "urn:test")
    fallback = SimpleNamespace(qname=None, concept=SimpleNamespace(name="Fallback"))
    assert _concept(fallback) == ("Fallback", None)


def test_extract_facts_maps_numeric_non_numeric_and_nil_values():
    numeric = _fact(raw="42800000000000")
    text = _fact(local="Name", raw="Issuer", unit=None, numeric=False)
    nil = _fact(local="Missing", raw="", nil=True)
    model = SimpleNamespace(facts=[numeric, text, nil])

    records = {record["source_concept"]: record for record in extract_facts(model, "filing-1", {"c1": "ctx-1"}, {"u1": "unit-1"})}

    assert records["Assets"]["raw_value"] == "42800000000000"
    assert records["Assets"]["normalized_numeric_value"] == "42800000000000"
    assert records["Assets"]["unit_ref"] == "unit-1"
    assert records["Name"]["normalized_numeric_value"] is None
    assert records["Name"]["fact_status"] == "EXTRACTED"
    assert records["Missing"]["is_nil"] is True
    assert records["Missing"]["normalized_numeric_value"] is None


def test_extract_facts_marks_numeric_parse_error_without_logging_raw_value():
    bad_value = "SECRET-RAW-VALUE"
    model = SimpleNamespace(facts=[_fact(raw=bad_value, numeric=True)])

    with patch("hissa_xbrl_worker.extractors.facts.log_event") as emit_log:
        records = extract_facts(model, "filing-1", {"c1": "ctx-1"}, {"u1": "unit-1"})

    assert records[0]["fact_status"] == "PARSE_ERROR"
    assert emit_log.call_args.args[2:4] == ("fact.parse_error", "Fact could not be parsed as numeric value.")
    assert bad_value not in repr(emit_log.call_args)


def test_extract_facts_suppresses_repeated_parse_warnings_after_twenty():
    model = SimpleNamespace(facts=[_fact(raw=f"bad-{index}") for index in range(25)])

    with patch("hissa_xbrl_worker.extractors.facts.log_event") as emit_log:
        records = extract_facts(model, "filing-1", {"c1": "ctx-1"}, {"u1": "unit-1"})

    assert len(records) == 25
    events = [call.args[2] for call in emit_log.call_args_list]
    assert events.count("fact.parse_error") == 20
    assert events.count("fact.warning_suppressed") == 1
    assert emit_log.call_args_list[-1].kwargs["suppressed_count"] == 5


def test_extract_facts_skips_unknown_context_and_keeps_missing_unit_reference():
    model = SimpleNamespace(facts=[_fact(context="known", unit="missing"), _fact(local="Ignored", context="unknown")])

    records = extract_facts(model, "filing-1", {"known": "ctx-1"}, {})

    assert len(records) == 1
    assert records[0]["unit_ref"] is None


def test_extract_facts_assigns_distinct_ids_to_duplicate_facts():
    model = SimpleNamespace(facts=[_fact(raw="10"), _fact(raw="10")])

    records = extract_facts(model, "filing-1", {"c1": "ctx-1"}, {"u1": "unit-1"})

    assert len({record["raw_fact_id"] for record in records}) == 2
    assert records == sorted(records, key=lambda item: item["raw_fact_id"])
