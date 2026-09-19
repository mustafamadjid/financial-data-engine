#!/usr/bin/env python3
"""Validate the DA-2 dictionary, mapping, and source-value test pack."""

import csv
from pathlib import Path


BASE_DIR = Path(__file__).resolve().parent
ROOT = BASE_DIR.parent

def find_data_file(filename: str) -> Path:
    for candidate in [BASE_DIR / filename, ROOT / "data" / filename]:
        if candidate.exists():
            return candidate
    raise FileNotFoundError(f"Could not find {filename}")

def find_output_dir(explicit: Path | None = None) -> Path:
    if explicit is not None:
        if (explicit / "raw_facts.csv").exists():
            return explicit
        raise FileNotFoundError(f"Could not find raw_facts.csv in {explicit}")
    candidates = [
        ROOT / "financial-data-engine" / "DA-1" / "data" / "extracted",
        ROOT / "DA-1" / "data" / "extracted",
        ROOT / "financial-data-engine" / "DA-1" / "data" / "rerun-output",
        ROOT / "data" / "extracted-rerun",
        BASE_DIR / "data" / "extracted-rerun",
    ]
    for c in candidates:
        if (c / "raw_facts.csv").exists():
            return c
    raise FileNotFoundError("Could not find raw_facts.csv in candidate paths")


def read_csv(path: Path) -> list[dict[str, str]]:
    with path.open(encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle))


def validate_columns(path: Path, expected: set[str]) -> None:
    with path.open(encoding="utf-8", newline="") as handle:
        reader = csv.reader(handle)
        header = next(reader, [])
        if set(header) != expected or len(header) != len(expected):
            raise AssertionError(f"{path.name}: unexpected columns {header}")
        for line_number, row in enumerate(reader, start=2):
            if not row or all(not cell.strip() for cell in row):
                continue
            if len(row) != len(header):
                raise AssertionError(f"{path.name}:{line_number}: expected {len(header)} columns, got {len(row)}")


def main() -> int:
    import argparse

    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--extracted-dir", type=Path, default=None)
    args = parser.parse_args()

    output_dir = find_output_dir(args.extracted_dir)
    validate_columns(
        BASE_DIR / "canonical_financial_dictionary.csv",
        {"canonical_concept", "statement", "definition", "period_type", "sign_convention", "allowed_scope", "required", "formula_dependency"},
    )
    validate_columns(
        BASE_DIR / "concept_mapping.csv",
        {"source_concept", "concept_namespace", "entry_point", "canonical_concept", "status", "rationale", "reviewer"},
    )
    validate_columns(
        BASE_DIR / "mapping-test-pack.csv",
        {"test_id", "entry_point", "ticker", "period", "source_concept", "canonical_concept", "current_context", "expected_unit", "expected_is_nil", "expected_value_raw"},
    )
    dictionary = read_csv(find_data_file("canonical_financial_dictionary.csv"))
    mapping = read_csv(find_data_file("concept_mapping.csv"))
    tests = read_csv(find_data_file("mapping-test-pack.csv"))
    facts = read_csv(output_dir / "raw_facts.csv")
    dimensions = read_csv(output_dir / "dimensions.csv")
    entry_point_tickers = {
        ".../ep/E24/general": {"AADI", "ANTM", "ASII", "CPIN", "PWON", "SMGR", "UNTR"},
        ".../ep/E24/financesharia": {"BBCA", "BRIS"},
        ".../ep/E24/infrastructure": {"TLKM"},
    }
    entry_point_namespaces = {
        "http://www.idx.co.id/xbrl/taxonomy/2020-01-01/ep/E24/general",
        "http://www.idx.co.id/xbrl/taxonomy/2020-01-01/ep/E24/financesharia",
        "http://www.idx.co.id/xbrl/taxonomy/2020-01-01/ep/E24/infrastructure",
    }

    canonical = {row["canonical_concept"] for row in dictionary}
    source_keys = {
        (row["source_concept"], row["concept_namespace"])
        for row in facts
    }
    mapping_keys = {
        (row["source_concept"], row["concept_namespace"])
        for row in mapping
    }
    assert len(dictionary) >= 30, "dictionary must contain at least 30 concepts"
    assert all(row["period_type"].strip() in {"instant", "duration"} for row in dictionary), "invalid dictionary period type"
    assert all(row["sign_convention"].strip() == "source sign" for row in dictionary), "dictionary sign convention must preserve source sign"
    assert all(set(part.strip() for part in row["allowed_scope"].split(";")) == {"group", "single"} for row in dictionary), "invalid dictionary scope"
    assert all(row["required"].strip() in {"required", "optional"} for row in dictionary), "invalid dictionary requiredness"
    assert {"income_statement", "balance_sheet", "cash_flow"} <= {
        row["statement"] for row in dictionary
    }
    assert all(row["canonical_concept"] in canonical for row in mapping)
    assert mapping_keys <= source_keys, "mapping contains a source absent from raw facts"
    assert all(
        set(row["entry_point"].split(";")) <= entry_point_namespaces
        for row in mapping
    ), "mapping contains an unknown target entry point namespace"
    assert all(row["status"] in {"PROPOSED", "CONFIRMED", "REVIEW_REQUIRED", "REJECTED"} for row in mapping), "invalid mapping status"
    assert len(mapping_keys) == len(mapping), "duplicate source namespace/concept mapping"
    assert all(
        test["ticker"] in entry_point_tickers[test["entry_point"]]
        for test in tests
    ), "test ticker does not belong to its entry point"

    dimension_contexts = {row["context_id"] for row in dimensions}
    for test in tests:
        candidates = [
            row for row in facts
            if row["source_concept"] == test["source_concept"]
            and row["concept_namespace"].endswith("/cor")
            and row["ticker"] == test["ticker"]
            and row["period"] == test["period"]
            and row["context_id"] == test["current_context"]
        ]
        assert len(candidates) == 1, f"{test['test_id']}: expected one source fact"
        fact = candidates[0]
        assert fact["unit_id"] == test["expected_unit"], test["test_id"]
        assert fact["is_nil"] == test["expected_is_nil"], test["test_id"]
        assert fact["value_raw"] == test["expected_value_raw"], test["test_id"]
        assert test["current_context"] not in dimension_contexts, (
            f"{test['test_id']}: default test fact must be undimensioned"
        )

    print(f"PASS: {len(dictionary)} canonical concepts")
    print(f"PASS: {len(mapping)} source mappings")
    print(f"PASS: {len(tests)} source-value tests")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
