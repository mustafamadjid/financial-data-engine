#!/usr/bin/env python3
"""Extract raw XBRL data from IDX instance.zip archives.

The script deliberately preserves source concepts and context references. It does
not map financial concepts or select a "best" value; those are later DA-2/DA-3
responsibilities.

Example:
    python scripts/extract_xbrl.py --input data/raw --output data/extracted
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import sys
import zipfile
from collections import Counter
from pathlib import Path
from typing import Any, Iterable
from xml.etree import ElementTree as ET


XBRL_INFRASTRUCTURE = {
    "context",
    "unit",
    "schemaRef",
    "linkbaseRef",
    "roleRef",
    "arcroleRef",
    "footnoteLink",
}

FILINGS_FIELDS = [
    "filing_id", "ticker", "period", "source_zip_path", "source_zip_sha256",
    "instance_entry", "instance_sha256", "taxonomy_references", "fact_count",
    "context_count", "unit_count",
]
CONTEXT_FIELDS = [
    "filing_id", "context_id", "entity_identifier", "entity_scheme", "period_type",
    "instant_date", "start_date", "end_date", "segment_json", "scenario_json",
    "dimensions_json",
]
DIMENSION_FIELDS = [
    "filing_id", "context_id", "container", "dimension_kind", "dimension_qname",
    "member_qname", "typed_value",
]
UNIT_FIELDS = ["filing_id", "unit_id", "measures", "divide_numerator", "divide_denominator"]
FACT_FIELDS = [
    "filing_id", "ticker", "period", "source_concept", "concept_namespace",
    "context_id", "unit_id", "decimals", "precision", "language", "is_nil",
    "is_numeric", "value_raw", "source_element_id",
]
ERROR_FIELDS = ["source_zip_path", "ticker", "period", "error_type", "error_message"]


def split_qname(tag: str) -> tuple[str, str]:
    """Return namespace URI and local name from an ElementTree QName."""
    if tag.startswith("{"):
        namespace, local_name = tag[1:].split("}", 1)
        return namespace, local_name
    return "", tag


def local_name(tag: str) -> str:
    return split_qname(tag)[1]


def attr(element: ET.Element, name: str) -> str | None:
    """Read an attribute by local name, independent of its XML namespace."""
    for key, value in element.attrib.items():
        if local_name(key) == name:
            return value
    return None


def text_content(element: ET.Element | None) -> str | None:
    if element is None:
        return None
    value = "".join(element.itertext()).strip()
    return value or None


def find_child(element: ET.Element, name: str) -> ET.Element | None:
    return next((child for child in element if local_name(child.tag) == name), None)


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def element_snapshot(element: ET.Element | None) -> dict[str, Any] | None:
    """Keep a compact, namespace-neutral representation of context content."""
    if element is None:
        return None
    return {
        "name": local_name(element.tag),
        "attributes": {local_name(key): value for key, value in element.attrib.items()},
        "text": text_content(element),
        "children": [element_snapshot(child) for child in element],
    }


def context_rows(filing_id: str, root: ET.Element) -> tuple[list[dict[str, str]], list[dict[str, str]]]:
    contexts: list[dict[str, str]] = []
    dimensions: list[dict[str, str]] = []

    for context in (child for child in root if local_name(child.tag) == "context"):
        context_id = attr(context, "id") or ""
        entity = find_child(context, "entity")
        identifier = find_child(entity, "identifier") if entity is not None else None
        period = find_child(context, "period")
        instant = find_child(period, "instant") if period is not None else None
        start_date = find_child(period, "startDate") if period is not None else None
        end_date = find_child(period, "endDate") if period is not None else None

        if instant is not None:
            period_type = "instant"
        elif start_date is not None or end_date is not None:
            period_type = "duration"
        else:
            period_type = "unknown"

        dimension_items: list[dict[str, str | None]] = []
        containers: dict[str, ET.Element | None] = {
            "segment": find_child(entity, "segment") if entity is not None else None,
            "scenario": find_child(context, "scenario"),
        }
        for container_name, container in containers.items():
            if container is None:
                continue
            for member in container.iter():
                member_kind = local_name(member.tag)
                if member_kind not in {"explicitMember", "typedMember"}:
                    continue
                dimension_qname = attr(member, "dimension")
                member_qname = text_content(member) if member_kind == "explicitMember" else None
                typed_value = text_content(member) if member_kind == "typedMember" else None
                item = {
                    "container": container_name,
                    "dimension_kind": member_kind,
                    "dimension_qname": dimension_qname,
                    "member_qname": member_qname,
                    "typed_value": typed_value,
                }
                dimension_items.append(item)
                dimensions.append({"filing_id": filing_id, "context_id": context_id, **item})

        contexts.append({
            "filing_id": filing_id,
            "context_id": context_id,
            "entity_identifier": text_content(identifier) or "",
            "entity_scheme": attr(identifier, "scheme") or "",
            "period_type": period_type,
            "instant_date": text_content(instant) or "",
            "start_date": text_content(start_date) or "",
            "end_date": text_content(end_date) or "",
            "segment_json": json.dumps(element_snapshot(containers["segment"]), ensure_ascii=False),
            "scenario_json": json.dumps(element_snapshot(containers["scenario"]), ensure_ascii=False),
            "dimensions_json": json.dumps(dimension_items, ensure_ascii=False),
        })
    return contexts, dimensions


def unit_rows(filing_id: str, root: ET.Element) -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []
    for unit in (child for child in root if local_name(child.tag) == "unit"):
        divide = find_child(unit, "divide")
        numerator = find_child(divide, "unitNumerator") if divide is not None else None
        denominator = find_child(divide, "unitDenominator") if divide is not None else None
        direct_measures = [text_content(item) or "" for item in unit if local_name(item.tag) == "measure"]
        numerator_children = list(numerator) if numerator is not None else []
        denominator_children = list(denominator) if denominator is not None else []
        numerator_measures = [text_content(item) or "" for item in numerator_children if local_name(item.tag) == "measure"]
        denominator_measures = [text_content(item) or "" for item in denominator_children if local_name(item.tag) == "measure"]
        rows.append({
            "filing_id": filing_id,
            "unit_id": attr(unit, "id") or "",
            "measures": json.dumps(direct_measures, ensure_ascii=False),
            "divide_numerator": json.dumps(numerator_measures, ensure_ascii=False),
            "divide_denominator": json.dumps(denominator_measures, ensure_ascii=False),
        })
    return rows


def fact_rows(filing_id: str, ticker: str, period: str, root: ET.Element) -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []
    for element in root:
        concept_namespace, concept_name = split_qname(element.tag)
        context_id = attr(element, "contextRef")
        if concept_name in XBRL_INFRASTRUCTURE or context_id is None:
            continue
        unit_id = attr(element, "unitRef")
        decimals = attr(element, "decimals")
        precision = attr(element, "precision")
        nil_value = (attr(element, "nil") or "").lower() in {"true", "1"}
        rows.append({
            "filing_id": filing_id,
            "ticker": ticker,
            "period": period,
            "source_concept": concept_name,
            "concept_namespace": concept_namespace,
            "context_id": context_id,
            "unit_id": unit_id or "",
            "decimals": decimals or "",
            "precision": precision or "",
            "language": attr(element, "lang") or "",
            "is_nil": str(nil_value).lower(),
            "is_numeric": str(bool(unit_id or decimals or precision)).lower(),
            "value_raw": "" if nil_value else (text_content(element) or ""),
            "source_element_id": attr(element, "id") or "",
        })
    return rows


def taxonomy_references(root: ET.Element) -> list[str]:
    references: list[str] = []
    for element in root:
        if local_name(element.tag) == "schemaRef":
            href = attr(element, "href")
            if href:
                references.append(href)
    return references


def write_rows(writer: csv.DictWriter, rows: Iterable[dict[str, str]]) -> None:
    for row in rows:
        writer.writerow(row)


def extract(source_root: Path, output_root: Path, ticker_filter: str | None = None, period_filter: str | None = None) -> Counter:
    archives = sorted(source_root.glob("*/*/instance.zip"))
    if ticker_filter:
        archives = [archive for archive in archives if archive.parent.parent.name.upper() == ticker_filter]
    if period_filter:
        archives = [archive for archive in archives if archive.parent.name.upper() == period_filter]
    if not archives:
        filters = ", ".join(value for value in (ticker_filter, period_filter) if value)
        raise FileNotFoundError(f"Tidak ada instance.zip pada {source_root} untuk filter: {filters or 'tidak ada'}")
    output_root.mkdir(parents=True, exist_ok=True)

    output_files = {
        "filings": (output_root / "filings.csv", FILINGS_FIELDS),
        "contexts": (output_root / "contexts.csv", CONTEXT_FIELDS),
        "dimensions": (output_root / "dimensions.csv", DIMENSION_FIELDS),
        "units": (output_root / "units.csv", UNIT_FIELDS),
        "facts": (output_root / "raw_facts.csv", FACT_FIELDS),
        "errors": (output_root / "extraction_errors.csv", ERROR_FIELDS),
    }
    handles: dict[str, Any] = {}
    writers: dict[str, csv.DictWriter] = {}
    for key, (path, fields) in output_files.items():
        handle = path.open("w", newline="", encoding="utf-8")
        writer = csv.DictWriter(handle, fieldnames=fields, extrasaction="raise")
        writer.writeheader()
        handles[key] = handle
        writers[key] = writer

    counts: Counter = Counter()
    try:
        for archive_path in archives:
            ticker = archive_path.parent.parent.name.upper()
            period = archive_path.parent.name.upper()
            relative_zip_path = archive_path.relative_to(source_root.parent).as_posix()
            try:
                zip_hash = sha256_file(archive_path)
                with zipfile.ZipFile(archive_path) as archive:
                    instance_entry = next(
                        (entry for entry in archive.infolist() if entry.filename.lower().endswith(".xbrl")),
                        None,
                    )
                    if instance_entry is None:
                        raise ValueError("Arsip tidak memiliki file .xbrl")
                    instance_bytes = archive.read(instance_entry)
                root = ET.fromstring(instance_bytes)
                instance_hash = sha256_bytes(instance_bytes)
                filing_id = f"{ticker}-{period}-{zip_hash[:12]}"
                contexts, dimensions = context_rows(filing_id, root)
                units = unit_rows(filing_id, root)
                facts = fact_rows(filing_id, ticker, period, root)

                writers["filings"].writerow({
                    "filing_id": filing_id,
                    "ticker": ticker,
                    "period": period,
                    "source_zip_path": relative_zip_path,
                    "source_zip_sha256": zip_hash,
                    "instance_entry": instance_entry.filename,
                    "instance_sha256": instance_hash,
                    "taxonomy_references": json.dumps(taxonomy_references(root), ensure_ascii=False),
                    "fact_count": len(facts),
                    "context_count": len(contexts),
                    "unit_count": len(units),
                })
                write_rows(writers["contexts"], contexts)
                write_rows(writers["dimensions"], dimensions)
                write_rows(writers["units"], units)
                write_rows(writers["facts"], facts)
                counts.update({"filings": 1, "contexts": len(contexts), "dimensions": len(dimensions), "units": len(units), "facts": len(facts)})
            except Exception as exc:  # Keep processing other filings and retain evidence of failures.
                writers["errors"].writerow({
                    "source_zip_path": relative_zip_path,
                    "ticker": ticker,
                    "period": period,
                    "error_type": type(exc).__name__,
                    "error_message": str(exc),
                })
                counts["errors"] += 1
    finally:
        for handle in handles.values():
            handle.close()
    return counts


def main() -> int:
    parser = argparse.ArgumentParser(description="Extract raw facts, contexts, units, and dimensions from IDX XBRL ZIPs.")
    parser.add_argument("--input", type=Path, default=Path("data/raw"), help="Folder containing TICKER/PERIOD/instance.zip")
    parser.add_argument("--output", type=Path, default=Path("data/extracted"), help="Folder for generated CSV datasets")
    parser.add_argument("--ticker", help="Filter one ticker symbol, e.g. ANTM")
    parser.add_argument("--period", help="Filter one reporting period, e.g. 2025Q1")
    args = parser.parse_args()

    try:
        counts = extract(
            args.input,
            args.output,
            ticker_filter=args.ticker.upper() if args.ticker else None,
            period_filter=args.period.upper() if args.period else None,
        )
    except Exception as exc:
        print(f"Extraction failed: {exc}", file=sys.stderr)
        return 1
    print("Extraction complete:")
    for key in ("filings", "contexts", "dimensions", "units", "facts", "errors"):
        print(f"  {key}: {counts[key]}")
    return 0 if counts["errors"] == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())
