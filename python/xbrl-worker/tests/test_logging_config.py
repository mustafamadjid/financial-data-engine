import json
import logging

import pytest

from hissa_xbrl_worker.log_context import LogContext, clear_log_context, set_log_context
from hissa_xbrl_worker.logging_config import (
    JsonLogFormatter,
    configure_logging,
    parse_log_level,
    sanitize_log_value,
    sanitize_debug_text,
    utc_timestamp,
)


def test_json_formatter_outputs_required_fields():
    set_log_context(LogContext(correlation_id="corr-01", filing_id="filing_01"))
    try:
        record = logging.LogRecord(
            name="hissa_xbrl_worker.parser",
            level=logging.INFO,
            pathname=__file__,
            lineno=1,
            msg="Parser started.",
            args=(),
            exc_info=None,
        )
        record.event = "parser.started"

        payload = json.loads(JsonLogFormatter().format(record))

        assert payload["schema"] == "hissa_parser_log"
        assert payload["schema_version"] == "1.0.0"
        assert payload["level"] == "INFO"
        assert payload["event"] == "parser.started"
        assert payload["correlation_id"] == "corr-01"
        assert payload["filing_id"] == "filing_01"
        assert payload["worker_version"] == "1.0.0"
        assert payload["timestamp"].endswith("Z")
    finally:
        clear_log_context()


def test_configure_logging_writes_json_to_stderr(capsys):
    configure_logging("INFO")
    logger = logging.getLogger("hissa_xbrl_worker.test")
    logger.info("hello", extra={"event": "test.event"})

    captured = capsys.readouterr()
    assert captured.out == ""
    payload = json.loads(captured.err.strip())
    assert payload["event"] == "test.event"


@pytest.mark.parametrize("value, expected", [("DEBUG", logging.DEBUG), ("info", logging.INFO), ("WARNING", logging.WARNING), ("ERROR", logging.ERROR)])
def test_parse_log_level(value, expected):
    assert parse_log_level(value) == expected


def test_parse_log_level_rejects_unknown_value():
    with pytest.raises(ValueError):
        parse_log_level("verbose")


def test_formatter_uses_fallback_event_and_sanitizes_absolute_path_in_message():
    record = logging.LogRecord(
        name="third.party", level=logging.WARNING, pathname=__file__, lineno=1,
        msg="Could not open C:\\Users\\secret\\filing.xbrl", args=(), exc_info=None,
    )

    payload = json.loads(JsonLogFormatter().format(record))

    assert payload["event"] == "log.message"
    assert "C:\\Users\\secret" not in payload["message"]


def test_utc_timestamp_is_utc_iso8601():
    timestamp = utc_timestamp()

    assert timestamp.endswith("Z")
    assert "T" in timestamp


def test_sanitize_debug_text_redacts_path_like_text():
    assert "C:\\Users\\user" not in sanitize_debug_text("source C:\\Users\\user\\file.xbrl")


def test_sanitize_log_value_converts_tuples_recursively():
    assert sanitize_log_value(("safe", {"token": "secret"})) == ["safe", {"token": "[REDACTED]"}]
