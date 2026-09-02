import argparse, json, re, sys
from pathlib import Path
from uuid import uuid4
from .contracts import ParseRequest
from .errors import WorkerError
from .parser import parse_filing, build_failure_payload, _runtime
from .serialization import serialize_payload
from .log_context import LogContext, clear_log_context, set_log_context
from .logging_config import configure_logging, log_event
import logging
import time


logger = logging.getLogger(__name__)
_CORRELATION_ID = re.compile(r"^[A-Za-z0-9_.:-]{1,128}$")


class _ArgumentParser(argparse.ArgumentParser):
    def error(self, message: str) -> None:
        raise ValueError(message)


def _valid_correlation_id(value: str) -> str:
    if not _CORRELATION_ID.fullmatch(value):
        raise ValueError("Invalid correlation ID.")
    return value

def main(argv: list[str] | None = None) -> int:
    started = time.perf_counter()
    configure_logging("INFO")
    parser = _ArgumentParser(prog="hissa_xbrl_worker")
    parser.add_argument("--input", required=True)
    parser.add_argument("--filing-id", required=True)
    parser.add_argument("--pretty", action="store_true")
    parser.add_argument("--correlation-id")
    parser.add_argument("--log-level", default="INFO")
    args = None
    try:
        args = parser.parse_args(argv)
        configure_logging(args.log_level)
        correlation_id = _valid_correlation_id(args.correlation_id or uuid4().hex)
        set_log_context(LogContext(correlation_id=correlation_id, filing_id=args.filing_id, source_file_name=Path(args.input).name))
        runtime = _runtime()
        log_event(
            logger,
            logging.INFO,
            "started",
            component="worker",
            worker_version=runtime["worker_version"],
            python_version=runtime["python_version"],
            arelle_version=runtime["arelle_version"],
        )
        result = parse_filing(ParseRequest(Path(args.input), args.filing_id)); code = 0
    except (ValueError, SystemExit) as exc:
        if isinstance(exc, SystemExit) and exc.code == 0:
            raise
        log_event(logger, logging.ERROR, "argument_error", "Invalid CLI arguments.", component="worker", error_code="CLI_ARGUMENT_ERROR")
        result = build_failure_payload("", Path(""), "CLI_ARGUMENT_ERROR", "Invalid CLI arguments."); code = 2
    except WorkerError as exc:
        log_event(
            logger,
            logging.ERROR,
            "parse_failed",
            "XBRL parser execution failed.",
            component="xbrl",
            filing_id=args.filing_id if args else None,
            error_code=exc.code,
            duration_ms=round((time.perf_counter() - started) * 1000, 3),
        )
        result = build_failure_payload(args.filing_id if args else "", Path(args.input) if args else Path(""), exc.code, exc.message); code = exc.exit_code
    except Exception:
        log_event(
            logger,
            logging.ERROR,
            "parse_failed",
            "XBRL parser execution failed.",
            component="xbrl",
            filing_id=args.filing_id if args else None,
            error_code="UNEXPECTED_ERROR",
            duration_ms=round((time.perf_counter() - started) * 1000, 3),
        )
        result = build_failure_payload(args.filing_id if args else "", Path(args.input) if args else Path(""), "INTERNAL_ERROR", "Unexpected internal parser error."); code = 20
    # Keep stdout machine-readable for pipes and process integrations while
    # keeping direct terminal runs focused on the formatted operational logs.
    try:
        if not sys.stdout.isatty() or bool(args and args.pretty):
            try: sys.stdout.write(serialize_payload(result, bool(args and args.pretty)) + "\n")
            except Exception:
                sys.stdout.write(json.dumps(build_failure_payload("", Path(""), "SERIALIZATION_ERROR", "Unable to serialize parser result."), separators=(",", ":")) + "\n"); code = 14
    finally:
        clear_log_context()
    return code
