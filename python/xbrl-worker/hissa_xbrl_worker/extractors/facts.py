from ..ids import make_fact_id
from ..numeric import parse_decimal_lexical
import logging
from ..logging_config import log_event

logger = logging.getLogger(__name__)

def _concept(fact):
    q = getattr(fact, "qname", None); ns = getattr(q, "namespaceURI", None); local = getattr(q, "localName", None) or getattr(getattr(fact, "concept", None), "name", "")
    return local, ns

def extract_facts(model_xbrl, filing_id: str, context_id_map: dict[str, str], unit_id_map: dict[str, str]):
    candidates = []
    warning_counts = {}
    for fact in getattr(model_xbrl, "facts", ()):
        context_source = getattr(fact, "contextID", None) or getattr(fact, "contextRef", None)
        if context_source not in context_id_map: continue
        local, ns = _concept(fact); raw = getattr(fact, "value", None); raw = "" if raw is None else str(raw)
        unit_source = getattr(fact, "unitID", None) or getattr(fact, "unitRef", None)
        fp = "\x1f".join((local, ns or "", context_source, unit_source or "", str(getattr(fact, "decimals", None) or ""), str(getattr(fact, "precision", None) or ""), raw))
        candidates.append((fp, fact, local, ns, raw, context_source, unit_source))
    candidates.sort(key=lambda x: x[0]); seen = {}; result = []
    for fp, fact, local, ns, raw, context_source, unit_source in candidates:
        occurrence = seen.get(fp, 0); seen[fp] = occurrence + 1
        nil = bool(getattr(fact, "isNil", False)); numeric_value = None if nil else parse_decimal_lexical(raw)
        is_numeric = bool(getattr(fact, "isNumeric", False))
        status = "EXTRACTED" if nil or numeric_value is not None or not is_numeric else "PARSE_ERROR"
        if status == "PARSE_ERROR":
            warning_counts["FACT_PARSE_ERROR"] = warning_counts.get("FACT_PARSE_ERROR", 0) + 1
            if warning_counts["FACT_PARSE_ERROR"] <= 20:
                log_event(logger, logging.WARNING, "fact.parse_error", "Fact could not be parsed as numeric value.", raw_fact_id=make_fact_id(fp, occurrence), source_concept=local, source_namespace=ns, context_ref=context_source, unit_ref=unit_source, error_code="FACT_PARSE_ERROR")
        result.append({"raw_fact_id": make_fact_id(fp, occurrence), "filing_id": filing_id, "source_concept": local, "source_namespace": ns, "raw_value": raw, "normalized_numeric_value": numeric_value, "context_ref": context_id_map[context_source], "unit_ref": unit_id_map.get(unit_source) if unit_source else None, "decimals": str(getattr(fact, "decimals", "")) if getattr(fact, "decimals", None) is not None else None, "precision": str(getattr(fact, "precision", "")) if getattr(fact, "precision", None) is not None else None, "is_nil": nil, "fact_status": status})
    for warning_type, count in warning_counts.items():
        if count > 20:
            log_event(logger, logging.WARNING, "fact.warning_suppressed", "Repeated fact warnings were suppressed.", warning_type=warning_type, suppressed_count=count - 20)
    return sorted(result, key=lambda x: x["raw_fact_id"])
