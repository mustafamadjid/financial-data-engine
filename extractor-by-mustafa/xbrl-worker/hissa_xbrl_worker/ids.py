import hashlib

def _id(prefix: str, *parts: object) -> str:
    digest = hashlib.sha256("\x1f".join("" if p is None else str(p) for p in parts).encode()).hexdigest()[:24]
    return f"{prefix}_{digest}"

def make_context_id(filing_id: str, source_context_id: str) -> str: return _id("ctx", filing_id, source_context_id)
def make_unit_id(filing_id: str, source_unit_id: str) -> str: return _id("unit", filing_id, source_unit_id)
def make_dimension_id(filing_id: str, source_context_id: str, axis: str, member: str | None, typed_value: str | None) -> str: return _id("dim", filing_id, source_context_id, axis, member, typed_value)
def make_fact_id(fingerprint: str, occurrence: int) -> str: return _id("fact", fingerprint, occurrence)
