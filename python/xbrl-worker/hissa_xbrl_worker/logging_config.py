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
_TERMINAL_FIELD_ALIASES = {
    "worker_version": "version",
    "python_version": "python",
    "arelle_version": "arelle",
    "filing_id": "filing",
    "source_file_name": "file",
    "source_sha256": "sha256",
    "source_context_id": "context",
    "source_unit_id": "unit",
    "error_code": "code",
    "warning_count": "warnings",
    "error_count": "errors",
    "duration_ms": "duration",
    "error_message": "message",
}
_TERMINAL_EVENT_FIELD_ORDER = {
    "started": ("worker_version", "python_version", "arelle_version"),
    "parse_started": ("filing_id", "source_file_name"),
    "source_verified": ("filing_id", "source_sha256"),
    "parse_completed": (
        "filing_id", "facts", "contexts", "units", "dimensions",
        "warning_count", "error_count", "duration_ms",
    ),
    "parse_failed": ("filing_id", "error_code", "error_message", "duration_ms"),
}
_LOG_RECORD_FIELDS = {
    "name", "msg", "args", "levelname", "levelno", "pathname", "filename",
    "module", "exc_info", "exc_text", "stack_info", "lineno", "funcName",
    "created", "msecs", "relativeCreated", "thread", "threadName", "processName",
    "process", "message", "event", "component", "taskName",
}


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
            "event": getattr(record, "event", "log_message"),
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


def _terminal_level(levelname: str) -> str:
    return {"WARNING": "WARN"}.get(levelname, levelname)


def _terminal_value(key: str, value: object) -> str | None:
    if value is None or isinstance(value, (Mapping, list, tuple, set)):
        return None
    if key == "source_sha256":
        value = str(value)
        if len(value) > 12:
            value = f"{value[:12]}..."
    elif key == "duration_ms":
        value = f"{value}ms"
    elif isinstance(value, bool):
        value = str(value).lower()
    elif not isinstance(value, (int, float)):
        value = str(value)
    rendered = str(value)
    if any(character.isspace() for character in rendered) or not rendered:
        return json.dumps(rendered, ensure_ascii=False)
    return rendered


class TerminalLogFormatter(logging.Formatter):
    """Render structured records as compact, human-readable terminal logs."""

    def format(self, record: logging.LogRecord) -> str:
        timestamp = datetime.fromtimestamp(record.created).strftime("%H:%M:%S")
        level = _terminal_level(record.levelname)
        component = getattr(record, "component", record.name.rsplit(".", 1)[-1])
        event = getattr(record, "event", "log_message")
        fields = {
            key: value
            for key, value in record.__dict__.items()
            if key not in _LOG_RECORD_FIELDS and not key.startswith("_")
        }
        ordered_keys = list(_TERMINAL_EVENT_FIELD_ORDER.get(event, ()))
        ordered_keys.extend(key for key in fields if key not in ordered_keys)
        rendered_fields = []
        for key in ordered_keys:
            if key not in fields:
                continue
            rendered = _terminal_value(key, fields[key])
            if rendered is not None:
                rendered_fields.append(f"{_TERMINAL_FIELD_ALIASES.get(key, key)}={rendered}")
        suffix = f" {' '.join(rendered_fields)}" if rendered_fields else ""
        return f"{timestamp} {level:<5} [{component}] {event}{suffix}"


def parse_log_level(value: str) -> int:
    level = logging.getLevelName(value.upper())
    if not isinstance(level, int):
        raise ValueError(f"Unknown log level: {value}")
    return level


def configure_logging(level: str = "INFO", output_format: str = "terminal") -> None:
    logger = logging.getLogger("hissa_xbrl_worker")
    logger.handlers.clear()
    handler = logging.StreamHandler(sys.stderr)
    try:
        formatter = {"terminal": TerminalLogFormatter, "json": JsonLogFormatter}[output_format.lower()]
    except KeyError as exc:
        raise ValueError(f"Unknown log output format: {output_format}") from exc
    handler.setFormatter(formatter())
    logger.addHandler(handler)
    logger.setLevel(parse_log_level(level))
    logger.propagate = False


def log_event(
    logger: logging.Logger,
    level: int,
    event: str,
    message: str | None = None,
    *,
    component: str = "worker",
    **fields: object,
) -> None:
    logger.log(
        level,
        message or event,
        extra={"event": event, "component": component, **sanitize_mapping(fields)},
    )
