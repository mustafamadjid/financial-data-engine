from hissa_xbrl_worker.ids import make_context_id
from hissa_xbrl_worker.numeric import parse_decimal_lexical
from hissa_xbrl_worker.serialization import serialize_payload

def test_primitives_are_deterministic():
    assert make_context_id("f", "c") == make_context_id("f", "c")
    assert parse_decimal_lexical("42800000000000") == "42800000000000"
    assert parse_decimal_lexical("abc") is None
    assert serialize_payload({"b": 1, "a": 2}) == '{"a":2,"b":1}'
