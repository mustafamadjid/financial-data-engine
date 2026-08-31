from ..ids import make_context_id

def extract_contexts(model_xbrl, filing_id: str):
    records, ids = [], {}
    for source_id, context in sorted(getattr(model_xbrl, "contexts", {}).items()):
        cid = make_context_id(filing_id, source_id); ids[source_id] = cid
        entity = getattr(context, "entityIdentifier", (None, "")); entity_id = entity[1] if isinstance(entity, tuple) else str(entity or "")
        if getattr(context, "isInstantPeriod", False):
            period_type, instant, start, end = "INSTANT", getattr(context, "instantDate", None), None, None
        elif getattr(context, "isStartEndPeriod", False):
            period_type = "DURATION"
            sd = getattr(context, "startDatetime", None); ed = getattr(context, "endDate", None)
            instant, start, end = None, sd.date().isoformat() if hasattr(sd, "date") else str(sd)[:10], ed.isoformat() if hasattr(ed, "isoformat") else str(ed)[:10]
        elif getattr(context, "isForeverPeriod", False): period_type, instant, start, end = "FOREVER", None, None, None
        else: period_type, instant, start, end = "FOREVER", None, None, None
        def date_value(value): return value.isoformat() if hasattr(value, "isoformat") else (str(value) if value else None)
        records.append({"context_id": cid, "filing_id": filing_id, "source_context_id": source_id, "entity_identifier": entity_id, "scope": "UNKNOWN", "period_type": period_type, "instant_date": date_value(instant), "start_date": start, "end_date": end, "context_status": "RESOLVED" if entity_id else "INVALID"})
    return sorted(records, key=lambda x: x["context_id"])

def build_context_id_map(model_xbrl, filing_id: str):
    return {source_id: make_context_id(filing_id, source_id) for source_id in getattr(model_xbrl, "contexts", {})}
