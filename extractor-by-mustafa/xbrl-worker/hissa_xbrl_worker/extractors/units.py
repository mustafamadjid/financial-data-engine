from ..ids import make_unit_id

def _qname(q):
    ns = getattr(q, "namespaceURI", None); ln = getattr(q, "localName", None) or str(q)
    return f"{{{ns}}}{ln}" if ns else ln

def extract_units(model_xbrl, filing_id: str):
    result, ids = [], {}
    for source_id, unit in sorted(getattr(model_xbrl, "units", {}).items()):
        uid = make_unit_id(filing_id, source_id); ids[source_id] = uid
        nums = sorted(_qname(x) for x in (getattr(unit, "measures", ([], []))[0] or [])); dens = sorted(_qname(x) for x in (getattr(unit, "measures", ([], []))[1] or []))
        if dens: typ = "RATIO"
        elif len(nums) == 1 and ("iso4217" in nums[0] or nums[0].split("}")[-1] not in ("shares", "pure")): typ = "CURRENCY" if "iso4217" in nums[0] else "CUSTOM"
        elif nums and nums[0].endswith("shares"): typ = "SHARES"
        elif nums and nums[0].endswith("pure"): typ = "PURE"
        else: typ = "UNKNOWN"
        measure = "*".join(nums) + ("/" + "*".join(dens) if dens else "") or None
        currency = nums[0].split("}")[-1] if typ == "CURRENCY" and "iso4217" in nums[0] else None
        result.append({"unit_id": uid, "filing_id": filing_id, "source_unit_id": source_id, "unit_type": typ, "measure": measure, "currency": currency})
    return sorted(result, key=lambda x: x["unit_id"])

def build_unit_id_map(model_xbrl, filing_id: str):
    return {source_id: make_unit_id(filing_id, source_id) for source_id in getattr(model_xbrl, "units", {})}
