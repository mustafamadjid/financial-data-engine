#!/usr/bin/env python3
"""Run the DA-3 pilot validator and enforce the reviewed compact parity summary."""

from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
validator = ROOT / "DA-1-3" / "DA-3" / "validate_quality.py"
raw_dir = ROOT / "DA-1-3" / "DA-1" / "data" / "raw"
result = subprocess.run([sys.executable, str(validator), "--raw-dir", str(raw_dir), "--quiet"], cwd=ROOT, text=True, capture_output=True, check=False)
print(result.stdout, end="")
if result.returncode != 0:
    print(result.stderr, file=sys.stderr)
    raise SystemExit(result.returncode)

expected = {
    "VERIFIED": 32,
    "REVIEW_REQUIRED": 8,
    "FAILED": 0,
}
for status, count in expected.items():
    match = re.search(rf"{status}\s+:\s+(\d+)", result.stdout)
    if match is None or int(match.group(1)) != count:
        raise SystemExit(f"DA pilot parity mismatch for {status}")

for identity in ("CPIN", "PWON", "TLKM"):
    if identity not in result.stdout:
        raise SystemExit(f"Expected DA review identity missing: {identity}")

print("DA pilot parity: PASS (32 VERIFIED / 8 REVIEW_REQUIRED / 0 FAILED)")
