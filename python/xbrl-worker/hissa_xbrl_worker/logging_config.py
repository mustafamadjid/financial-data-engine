from __future__ import annotations

import json
import logging
import re
import sys
from collections.abc import Mapping
from datetime import datetime, timezone
from typing import Any

from .log_context import get_log_context
from .version import WORKER_VERSION

SCHEMA = "hissa_parser_log"
SCHEMA_VERSION = "1.0.0"
REDACTED = "[REDACTED]"
SENSITIVE_KEYS = {
    "password", "passwd", "secret", "token", "access_token", "refresh_token",
    "authorization", "cookie", "api_key", "client_secret",
}
_PATH_PATTERN = re.compile(r"(?:[A-Za-z]:[\\/]|/)(?:[^\s\\/]+[\\/])+[^\s]*")


def utc_timestamp() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="microseconds").replace("+00:00", "Z")


def sanitize_log_value(value: object) -> object:
    if isinstance(value, Mapping):
        return sanitize_mapping(value)
    if isinstance(value, list):
        return [sanitize_log_value(item) for item in value]
    if isinstance(value, tuple):
        return [sanitize_log_value(item) for item in value]
    return value


def sanitize_mapping(data: Mapping[str, object]) -> dict[str, object]:
    result: dict[str, object] = {}
    for key, value in data.items():
        result[key] = REDACTED if key.lower() in SENSITIVE_KEYS else sanitize_log_value(value)
    return result


def sanitize_debug_text(value: str) -> str:
    return _PATH_PATTERN.sub("[PATH]", value)


class JsonLogFormatter(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        context = get_log_context()
        payload: dict[str, Any] = {
            "schema": SCHEMA,
            "schema_version": SCHEMA_VERSION,
            "timestamp": utc_timestamp(),
            "level": record.levelname,
            "logger": record.name,
            "event": getattr(record, "event", "log.message"),
            "message": sanitize_debug_text(record.getMessage()),
            "correlation_id": context.correlation_id,
            "worker_version": WORKER_VERSION,
        }
        if context.filing_id is not None:
            payload["filing_id"] = context.filing_id
        if context.source_file_name is not None:
            payload["source_file_name"] = context.source_file_name
        if context.source_sha256 is not None:
            payload["source_sha256"] = context.source_sha256
        for key, value in record.__dict__.items():
            if key in {
                "name", "msg", "args", "levelname", "levelno", "pathname", "filename",
                "module", "exc_info", "exc_text", "stack_info", "lineno", "funcName",
                "created", "msecs", "relativeCreated", "thread", "threadName", "processName",
                "process", "message", "event", "taskName",
            } or key.startswith("_"):
                continue
            payload[key] = sanitize_log_value(value)
        return json.dumps(payload, ensure_ascii=False, sort_keys=True, default=str)


def parse_log_level(value: str) -> int:
    level = logging.getLevelName(value.upper())
    if not isinstance(level, int):
        raise ValueError(f"Unknown log level: {value}")
    return level


def configure_logging(level: str = "INFO") -> None:
    logger = logging.getLogger("hissa_xbrl_worker")
    logger.handlers.clear()
    handler = logging.StreamHandler(sys.stderr)
    handler.setFormatter(JsonLogFormatter())
    logger.addHandler(handler)
    logger.setLevel(parse_log_level(level))
    logger.propagate = False


def log_event(logger: logging.Logger, level: int, event: str, message: str, **fields: object) -> None:
    logger.log(level, message, extra={"event": event, **sanitize_mapping(fields)})
