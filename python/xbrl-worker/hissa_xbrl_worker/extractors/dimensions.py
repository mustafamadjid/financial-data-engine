from ..ids import make_dimension_id

def _qname(q):
    if q is None: return None
    ns = getattr(q, "namespaceURI", None); ln = getattr(q, "localName", None) or str(q)
    return f"{{{ns}}}{ln}" if ns else ln

def extract_dimensions(model_xbrl, filing_id: str, context_id_map: dict[str, str]):
    result = []
    for source_id, context in sorted(getattr(model_xbrl, "contexts", {}).items()):
        for axis, dim in sorted(getattr(context, "qnameDims", {}).items(), key=lambda x: _qname(x[0])):
            axis_s = _qname(axis); member = getattr(dim, "memberQname", None); typed = getattr(dim, "typedMember", None)
            member_s = _qname(member) if member is not None else None
            typed_s = getattr(typed, "textValue", None) if typed is not None else None
            result.append({"dimension_id": make_dimension_id(filing_id, source_id, axis_s, member_s, typed_s), "context_id": context_id_map[source_id], "axis": axis_s, "member": member_s, "typed_value": typed_s})
    return sorted(result, key=lambda x: (x["context_id"], x["axis"], x["member"] or "", x["typed_value"] or ""))
