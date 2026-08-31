from hissa_xbrl_worker.ids import (
    make_context_id,
    make_dimension_id,
    make_fact_id,
    make_unit_id,
)


def test_context_and_unit_ids_are_stable_and_namespaced():
    assert make_context_id("filing", "ctx") == make_context_id("filing", "ctx")
    assert make_context_id("filing", "ctx").startswith("ctx_")
    assert make_unit_id("filing", "unit").startswith("unit_")
    assert make_context_id("filing-a", "ctx") != make_context_id("filing-b", "ctx")


def test_dimension_id_includes_optional_dimension_values():
    explicit = make_dimension_id("f", "c", "axis", "member", None)
    typed = make_dimension_id("f", "c", "axis", None, "typed")

    assert explicit.startswith("dim_")
    assert typed.startswith("dim_")
    assert explicit != typed
    assert len(explicit.split("_", 1)[1]) == 24


def test_fact_occurrence_makes_duplicate_ids_distinct():
    assert make_fact_id("fingerprint", 0) != make_fact_id("fingerprint", 1)
    assert make_fact_id("fingerprint", 0) == make_fact_id("fingerprint", 0)

