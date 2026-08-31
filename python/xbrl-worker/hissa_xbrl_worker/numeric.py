from decimal import Decimal, InvalidOperation

def parse_decimal_lexical(value: str | None) -> str | None:
    if value is None or value == "": return None
    try: parsed = Decimal(value)
    except (InvalidOperation, ValueError): return None
    return value if parsed.is_finite() else None

def normalize_numeric_lexical(raw_value: str) -> str | None:
    return parse_decimal_lexical(raw_value)
