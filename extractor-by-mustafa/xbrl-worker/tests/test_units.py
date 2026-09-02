from types import SimpleNamespace

from hissa_xbrl_worker.extractors.units import _qname, build_unit_id_map, extract_units


class QName:
    def __init__(self, namespace, local):
        self.namespaceURI = namespace
        self.localName = local


def _unit(numerator, denominator=()):
    return SimpleNamespace(measures=(list(numerator), list(denominator)))


def test_extract_units_classifies_supported_unit_types():
    model = SimpleNamespace(units={
        "currency": _unit([QName("http://www.xbrl.org/2003/iso4217", "IDR")]),
        "shares": _unit([QName("http://www.xbrl.org/2003/instance", "shares")]),
        "pure": _unit([QName("http://www.xbrl.org/2003/instance", "pure")]),
        "ratio": _unit([QName("http://www.xbrl.org/2003/iso4217", "IDR")], [QName("http://www.xbrl.org/2003/instance", "shares")]),
        "custom": _unit([QName("http://example.com", "customMeasure")]),
        "unknown": _unit([]),
    })

    records = {record["source_unit_id"]: record for record in extract_units(model, "filing-1")}

    assert records["currency"]["unit_type"] == "CURRENCY"
    assert records["currency"]["currency"] == "IDR"
    assert records["shares"]["unit_type"] == "SHARES"
    assert records["pure"]["unit_type"] == "PURE"
    assert records["ratio"]["unit_type"] == "RATIO"
    assert records["ratio"]["measure"].endswith("/\u007bhttp://www.xbrl.org/2003/instance\u007dshares")
    assert records["custom"]["unit_type"] == "CUSTOM"
    assert records["unknown"]["unit_type"] == "UNKNOWN"


def test_extract_units_sorts_measure_components_and_unit_records():
    model = SimpleNamespace(units={
        "z": _unit([QName("n", "B"), QName("n", "A")], [QName("n", "D"), QName("n", "C")]),
        "a": _unit([QName("n", "A")]),
    })

    records = extract_units(model, "filing-1")

    assert records == sorted(records, key=lambda item: item["unit_id"])
    assert next(item for item in records if item["source_unit_id"] == "z")["measure"] == "{n}A*{n}B/{n}C*{n}D"


def test_unit_qname_serialization_handles_plain_and_namespaced_values():
    assert _qname(QName("urn:test", "IDR")) == "{urn:test}IDR"
    assert _qname("plain") == "plain"


def test_unit_id_map_contains_source_ids():
    model = SimpleNamespace(units={"u": object()})

    assert build_unit_id_map(model, "filing-1")["u"].startswith("unit_")
