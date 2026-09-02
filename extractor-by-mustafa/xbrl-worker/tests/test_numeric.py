import pytest

from hissa_xbrl_worker.numeric import normalize_numeric_lexical, parse_decimal_lexical


@pytest.mark.parametrize("value", ["0", "-12.50", "1E+6", "+7", " 8 "])
def test_parse_decimal_accepts_decimal_lexical_values_without_changing_text(value):
    assert parse_decimal_lexical(value) == value


@pytest.mark.parametrize("value", [None, "", "abc", "1,000", "NaN", "Infinity"])
def test_parse_decimal_rejects_missing_invalid_or_non_finite_values(value):
    assert parse_decimal_lexical(value) is None


def test_normalize_numeric_lexical_uses_same_precision_safe_behavior():
    assert normalize_numeric_lexical("42800000000000") == "42800000000000"
    assert normalize_numeric_lexical("not-a-number") is None

