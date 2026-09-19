from __future__ import annotations


def _uri(value):
    return str(getattr(value, "uri", value) or "").strip()


def extract_taxonomy(model_xbrl):
    """Extract only taxonomy metadata exposed by Arelle; never infer business meaning."""
    target = getattr(model_xbrl, "targetNamespace", None)
    if not target:
        document = getattr(model_xbrl, "modelDocument", None)
        target = getattr(document, "targetNamespace", None)

    url_docs = getattr(model_xbrl, "urlDocs", {}) or {}
    imports = sorted({uri for uri in (_uri(item) for item in (url_docs.values() if hasattr(url_docs, "values") else url_docs)) if uri})
    relationship_sets = getattr(model_xbrl, "relationshipSets", {}) or {}
    roles = sorted(str(role) for role in relationship_sets if str(role).strip())

    return {
        "target_namespace": str(target).strip() if target else None,
        "imports": imports,
        "import_locations": imports,
        "linkbase_roles": roles,
        "linkbase_references": roles,
        "statement_families": [],
    }
