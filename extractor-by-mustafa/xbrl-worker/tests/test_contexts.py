from datetime import date, datetime
from types import SimpleNamespace

from hissa_xbrl_worker.extractors.contexts import build_context_id_map, extract_contexts


def _context(entity="entity-1", **kwargs):
    defaults = {
        "entityIdentifier": ("scheme", entity),
        "isInstantPeriod": False,
        "isStartEndPeriod": False,
        "isForeverPeriod": False,
        "instantDate": None,
        "startDatetime": None,
        "endDate": None,
    }
    defaults.update(kwargs)
    return SimpleNamespace(**defaults)


def test_extract_contexts_maps_instant_period_and_entity():
    model = SimpleNamespace(contexts={"instant": _context(instantDate=date(2025, 12, 31), isInstantPeriod=True)})

    records = extract_contexts(model, "filing-1")

    assert records[0]["source_context_id"] == "instant"
    assert records[0]["entity_identifier"] == "entity-1"
    assert records[0]["period_type"] == "INSTANT"
    assert records[0]["instant_date"] == "2025-12-31"
    assert records[0]["start_date"] is None
    assert records[0]["context_status"] == "RESOLVED"


def test_extract_contexts_maps_duration_period_dates():
    model = SimpleNamespace(contexts={"duration": _context(startDatetime=datetime(2025, 1, 1, 12), endDate=date(2025, 12, 31), isStartEndPeriod=True)})

    record = extract_contexts(model, "filing-1")[0]

    assert record["period_type"] == "DURATION"
    assert record["start_date"] == "2025-01-01"
    assert record["end_date"] == "2025-12-31"


def test_extract_contexts_maps_forever_and_invalid_contexts():
    model = SimpleNamespace(contexts={
        "forever": _context(isForeverPeriod=True),
        "invalid": _context(entity=""),
        "unknown": _context(entity="entity-2"),
    })

    records = {record["source_context_id"]: record for record in extract_contexts(model, "filing-1")}

    assert records["forever"]["period_type"] == "FOREVER"
    assert records["forever"]["context_status"] == "RESOLVED"
    assert records["invalid"]["context_status"] == "INVALID"
    assert records["unknown"]["period_type"] == "FOREVER"


def test_context_id_map_contains_all_source_contexts():
    model = SimpleNamespace(contexts={"b": object(), "a": object()})

    mapping = build_context_id_map(model, "filing-1")

    assert set(mapping) == {"a", "b"}
    assert mapping["a"].startswith("ctx_")

