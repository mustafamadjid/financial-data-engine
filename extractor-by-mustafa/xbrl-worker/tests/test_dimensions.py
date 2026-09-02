from types import SimpleNamespace

from hissa_xbrl_worker.extractors.dimensions import _qname, extract_dimensions


class QName:
    def __init__(self, namespace, local):
        self.namespaceURI = namespace
        self.localName = local


def test_qname_serialization_handles_namespaced_and_plain_values():
    assert _qname(QName("urn:test", "Axis")) == "{urn:test}Axis"
    assert _qname(None) is None
    assert _qname("Plain") == "Plain"


def test_extract_dimensions_maps_explicit_and_typed_dimensions():
    axis = QName("urn:test", "Axis")
    member = QName("urn:test", "Member")
    typed = SimpleNamespace(textValue="typed-value")
    model = SimpleNamespace(contexts={
        "c1": SimpleNamespace(qnameDims={
            axis: SimpleNamespace(memberQname=member, typedMember=None),
            QName("urn:test", "TypedAxis"): SimpleNamespace(memberQname=None, typedMember=typed),
        })
    })

    records = extract_dimensions(model, "filing-1", {"c1": "ctx-1"})

    explicit = next(item for item in records if item["member"] is not None)
    typed_record = next(item for item in records if item["typed_value"] is not None)
    assert explicit["context_id"] == "ctx-1"
    assert explicit["axis"] == "{urn:test}Axis"
    assert explicit["member"] == "{urn:test}Member"
    assert explicit["typed_value"] is None
    assert typed_record["member"] is None
    assert typed_record["typed_value"] == "typed-value"


def test_extract_dimensions_is_deterministically_sorted():
    axis_a = QName("urn:test", "A")
    axis_b = QName("urn:test", "B")
    model = SimpleNamespace(contexts={
        "c2": SimpleNamespace(qnameDims={axis_b: SimpleNamespace(memberQname=None, typedMember=None)}),
        "c1": SimpleNamespace(qnameDims={axis_a: SimpleNamespace(memberQname=None, typedMember=None)}),
    })

    records = extract_dimensions(model, "filing-1", {"c1": "ctx-1", "c2": "ctx-2"})

    assert records == sorted(records, key=lambda item: (item["context_id"], item["axis"], item["member"] or "", item["typed_value"] or ""))

