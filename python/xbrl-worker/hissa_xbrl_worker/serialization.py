import hashlib, json
import logging
import time
from pathlib import Path

from .logging_config import log_event

logger = logging.getLogger(__name__)

def serialize_payload(payload: dict[str, object], pretty: bool = False) -> str:
    started = time.perf_counter()
    log_event(logger, logging.DEBUG, "serialization.started", "Parser payload serialization started.")
    serialized = json.dumps(payload, ensure_ascii=False, sort_keys=True, indent=2 if pretty else None, separators=None if pretty else (",", ":"))
    log_event(logger, logging.INFO, "serialization.completed", "Parser payload serialization completed.", duration_ms=round((time.perf_counter() - started) * 1000, 3), payload_bytes=len(serialized.encode("utf-8")))
    return serialized

def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""): digest.update(chunk)
    return digest.hexdigest()
