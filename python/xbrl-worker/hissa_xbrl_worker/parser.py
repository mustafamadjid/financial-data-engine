from pathlib import Path
import logging
import time
from .arelle_loader import load_model
from .contracts import ParseRequest
from .errors import WorkerError
from .serialization import sha256_file
from .version import WORKER_VERSION
from .extractors.contexts import extract_contexts, build_context_id_map
from .extractors.units import extract_units, build_unit_id_map
from .extractors.dimensions import extract_dimensions
from .extractors.facts import extract_facts
from .log_context import get_log_context, set_log_context, LogContext
from .logging_config import log_event

logger = logging.getLogger(__name__)

def _runtime():
    try:
        from importlib.metadata import version
        av = version("arelle-release")
    except Exception: av = "unknown"
    import platform
    return {"worker_version": WORKER_VERSION, "arelle_package": "arelle-release", "arelle_version": av, "python_version": platform.python_version()}

def build_failure_payload(filing_id: str, input_path: Path, error_code: str, message: str, source_sha256: str | None = None):
    return {"parser_contract": "xbrl_parser_result", "parser_contract_version": "1.0.0", "status": "FAILED", "filing_id": filing_id, "source": {"path": str(input_path), "sha256": source_sha256}, "runtime": _runtime(), "counts": {"contexts": 0, "units": 0, "dimensions": 0, "facts": 0}, "contexts": [], "units": [], "dimensions": [], "facts": [], "warnings": [], "errors": [{"code": error_code, "message": message}]}

def parse_filing(request: ParseRequest):
    started = time.perf_counter()
    path = Path(request.input_path)
    log_event(
        logger,
        logging.INFO,
        "parse_started",
        component="xbrl",
        filing_id=request.filing_id,
        source_file_name=path.name,
    )
    if not path.is_file() or not path.stat().st_size:
        log_event(logger, logging.ERROR, "source_hash_failed", "Unable to hash source file.", component="io", error_code="INPUT_FILE_ERROR")
        raise WorkerError("INPUT_FILE_ERROR", "Input file does not exist or cannot be read.", 10)
    log_event(logger, logging.DEBUG, "source_hash_started", component="io")
    try:
        source_hash = sha256_file(path)
    except OSError as exc:
        log_event(logger, logging.ERROR, "source_hash_failed", "Unable to hash source file.", component="io", error_code="INPUT_FILE_ERROR", exception_type=type(exc).__name__)
        raise WorkerError("INPUT_FILE_ERROR", "Input file does not exist or cannot be read.", 10) from exc
    context = get_log_context()
    set_log_context(LogContext(context.correlation_id, request.filing_id, path.name, source_hash))
    log_event(logger, logging.INFO, "source_verified", component="xbrl", filing_id=request.filing_id, source_sha256=source_hash)
    with load_model(path) as model:
        try:
            phase_events = {
                "contexts": ("extract_contexts", "Context extraction"),
                "units": ("extract_units", "Unit extraction"),
                "dimensions": ("extract_dimensions", "Dimension extraction"),
                "facts": ("extract_facts", "Fact extraction"),
            }

            def run_phase(name, operation):
                event, message = phase_events[name]
                phase_started = time.perf_counter()
                log_event(logger, logging.DEBUG, f"{event}_started", f"{message} started.", component="xbrl")
                try:
                    value = operation()
                except Exception as exc:
                    log_event(logger, logging.ERROR, f"{event}_failed", f"{message} failed.", component="xbrl", error_code="EXTRACTION_ERROR", exception_type=type(exc).__name__)
                    raise
                count_field = f"{name}_count"
                log_event(logger, logging.DEBUG, f"{event}_completed", f"{message} completed.", component="xbrl", **{count_field: len(value), "duration_ms": round((time.perf_counter() - phase_started) * 1000, 3)})
                return value

            contexts = run_phase("contexts", lambda: extract_contexts(model, request.filing_id)); cmap = build_context_id_map(model, request.filing_id)
            units = run_phase("units", lambda: extract_units(model, request.filing_id)); umap = build_unit_id_map(model, request.filing_id)
            dimensions = run_phase("dimensions", lambda: extract_dimensions(model, request.filing_id, cmap))
            facts = run_phase("facts", lambda: extract_facts(model, request.filing_id, cmap, umap))
        except WorkerError:
            raise
        except Exception as exc:
            log_event(logger, logging.ERROR, "extraction_failed", "XBRL extraction failed.", component="xbrl", error_code="EXTRACTION_ERROR", exception_type=type(exc).__name__)
            raise WorkerError("EXTRACTION_ERROR", "Unable to extract XBRL data.", 13) from exc
    result = {"parser_contract": "xbrl_parser_result", "parser_contract_version": "1.0.0", "status": "SUCCESS", "filing_id": request.filing_id, "source": {"path": str(path), "sha256": source_hash}, "runtime": _runtime(), "counts": {"contexts": len(contexts), "units": len(units), "dimensions": len(dimensions), "facts": len(facts)}, "contexts": contexts, "units": units, "dimensions": dimensions, "facts": facts, "warnings": [], "errors": []}
    log_event(
        logger,
        logging.INFO,
        "parse_completed",
        component="xbrl",
        filing_id=request.filing_id,
        **result["counts"],
        warning_count=0,
        error_count=0,
        duration_ms=round((time.perf_counter() - started) * 1000, 3),
    )
    return result
