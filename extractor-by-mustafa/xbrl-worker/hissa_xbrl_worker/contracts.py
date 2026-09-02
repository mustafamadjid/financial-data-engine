from dataclasses import dataclass
from pathlib import Path

@dataclass(frozen=True)
class ParseRequest:
    input_path: Path
    filing_id: str
