from types import SimpleNamespace

from hissa_xbrl_worker.extractors.taxonomy import extract_taxonomy


def test_taxonomy_inventory_is_deterministic_and_compact():
    model = SimpleNamespace(
        targetNamespace="urn:example:taxonomy",
        urlDocs={"b": SimpleNamespace(uri="https://example.test/b.xsd"), "a": SimpleNamespace(uri="https://example.test/a.xsd")},
        relationshipSets={"role-z": object(), "role-a": object()},
    )

    first = extract_taxonomy(model)
    second = extract_taxonomy(model)

    assert first == second
    assert first["target_namespace"] == "urn:example:taxonomy"
    assert first["imports"] == ["https://example.test/a.xsd", "https://example.test/b.xsd"]
    assert first["linkbase_references"] == ["role-a", "role-z"]
