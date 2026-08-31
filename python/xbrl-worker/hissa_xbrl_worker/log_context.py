from contextvars import ContextVar
from dataclasses import dataclass


@dataclass(frozen=True)
class LogContext:
    correlation_id: str
    filing_id: str | None = None
    source_file_name: str | None = None
    source_sha256: str | None = None


_DEFAULT_CONTEXT = LogContext(correlation_id="unassigned")
_CURRENT_CONTEXT: ContextVar[LogContext] = ContextVar(
    "hissa_xbrl_worker_log_context", default=_DEFAULT_CONTEXT
)


def set_log_context(context: LogContext) -> None:
    _CURRENT_CONTEXT.set(context)


def get_log_context() -> LogContext:
    return _CURRENT_CONTEXT.get()


def clear_log_context() -> None:
    _CURRENT_CONTEXT.set(_DEFAULT_CONTEXT)

