#!/usr/bin/env python3
"""Repository-local DA alignment verification entrypoint."""

from __future__ import annotations

import re
import csv
import subprocess
import sys
import time
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]

env_example = ROOT / "financial-data-engine-sandbox" / ".env.example"
env_text = env_example.read_text(encoding="utf-8")
if re.search(r"BEGIN (?:RSA |EC )?PRIVATE KEY", env_text, re.IGNORECASE):
    raise SystemExit(f"secret-like value detected in {env_example}")
for line in env_text.splitlines():
    if "=" not in line or line.lstrip().startswith("#"):
        continue
    key, value = line.split("=", 1)
    if key.strip() in {"APP_KEY", "DB_PASSWORD", "AWS_ACCESS_KEY_ID", "AWS_SECRET_ACCESS_KEY"}:
        normalized_value = value.strip().strip('"').strip("'")
        if normalized_value and normalized_value.lower() not in {"null", "changeme", "your-secret-here"}:
            raise SystemExit(f"non-placeholder secret-like value detected for {key.strip()} in {env_example}")
for required_placeholder in ("FINANCIAL_PIPELINE_CONTRACT_VERSION", "HISSA_PARSER_CONTRACT_VERSION", "FINANCIAL_PIPELINE_VALIDATION_RULE_SET_VERSION"):
    if required_placeholder not in env_text:
        raise SystemExit(f"release placeholder missing from {env_example}: {required_placeholder}")

with (ROOT / "DA-1-3" / "DA-2" / "concept_mapping.csv").open(newline="", encoding="utf-8") as mapping_file:
    mapping_statuses = {row["status"] for row in csv.DictReader(mapping_file)}
if mapping_statuses != {"PROPOSED"}:
    raise SystemExit(f"Unexpected DA mapping statuses: {sorted(mapping_statuses)}")


def run(label: str, command: list[str], cwd: Path = ROOT) -> None:
    started = time.perf_counter()
    completed = subprocess.run(command, cwd=cwd, text=True, capture_output=True, check=False)
    elapsed = time.perf_counter() - started
    print(f"[{label}] exit={completed.returncode} duration={elapsed:.2f}s")
    if completed.stdout:
        print(completed.stdout[-4000:])
    if completed.returncode != 0:
        if completed.stderr:
            print(completed.stderr[-4000:], file=sys.stderr)
        raise SystemExit(completed.returncode)


run("contracts", [sys.executable, "-m", "pytest", "contracts/tests", "-q"])
run("worker", [sys.executable, "-m", "pytest", "-q"], ROOT / "python" / "xbrl-worker")
run("da2", [sys.executable, "DA-1-3/DA-2/validate_mapping.py"])
run(
    "da-import-dry-run",
    [
        "php",
        "artisan",
        "financial-data:import-da",
        str(ROOT / "DA-1-3"),
        "DA-2-v1",
        "--dry-run",
    ],
    ROOT / "financial-data-engine-sandbox",
)
run("da3", [sys.executable, "DA-1-3/DA-3/validate_quality.py", "--quiet"])
run("pilot", [sys.executable, "scripts/verify_da_pilot.py"])
pint_command = ["cmd", "/c", ".\\vendor\\bin\\pint.bat", "--test", "--format", "agent"] if sys.platform.startswith("win") else ["vendor/bin/pint", "--test", "--format", "agent"]
run("pint", pint_command, ROOT / "financial-data-engine-sandbox")
run("laravel", ["php", "artisan", "test", "--compact"], ROOT / "financial-data-engine-sandbox")

for log in (ROOT / "financial-data-engine-sandbox" / "storage" / "logs").glob("*.log"):
    text = log.read_text(encoding="utf-8", errors="ignore")
    if re.search(r"BEGIN (?:RSA |EC )?PRIVATE KEY|password\s*=\s*[^<\s]+", text, re.IGNORECASE):
        raise SystemExit(f"secret-like content detected in generated log: {log}")

print("DA alignment verification: PASS")
