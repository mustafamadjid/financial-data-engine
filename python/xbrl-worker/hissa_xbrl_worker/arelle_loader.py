from contextlib import contextmanager
from pathlib import Path
from typing import Any, Iterator
from .errors import WorkerError
import logging
import time
from .logging_config import log_event

logger = logging.getLogger(__name__)

@contextmanager
def load_model(path: Path) -> Iterator[Any]:
    started = time.perf_counter()
    log_event(logger, logging.INFO, "arelle_load_started", component="arelle", source_file_name=path.name)
    if not path.is_file() or not path.stat().st_size:
        raise WorkerError("INPUT_FILE_ERROR", "Input file does not exist or cannot be read.", 10)
    try:
        from arelle.Cntlr import Cntlr
        from arelle.FileSource import openFileSource
        from arelle.ModelManager import initialize
        
        cntlr = Cntlr(logFileName="logToBuffer", disable_persistent_config=True)
        # Keep Arelle's web cache inside the worker workspace so CLI execution
        # does not depend on a writable user profile or network access.
        cache_dir = path.parent / ".arelle-cache"
        cache_dir.mkdir(exist_ok=True)
        cntlr.webCache.cacheDir = str(cache_dir)
        cntlr.webCache.workOffline = True
        model_manager = initialize(cntlr)
        file_source = openFileSource(str(path), cntlr)
        model = model_manager.load(file_source)
        if model is None or not getattr(model, "contexts", None) and not getattr(model, "facts", None):
            raise WorkerError("UNSUPPORTED_DOCUMENT", "Document is not a supported XBRL document.", 11)
        log_event(logger, logging.DEBUG, "arelle_load_completed", component="arelle", duration_ms=round((time.perf_counter() - started) * 1000, 3))
        yield model
    except WorkerError as exc:
        log_event(logger, logging.ERROR, "arelle_load_failed", component="arelle", error_code=exc.code, exception_type=type(exc).__name__)
        raise
    except Exception as exc:
        log_event(logger, logging.ERROR, "arelle_load_failed", component="arelle", error_code="XBRL_LOAD_ERROR", exception_type=type(exc).__name__)
        raise WorkerError("XBRL_LOAD_ERROR", "Unable to load XBRL document.", 12) from exc
    finally:
        try:
            if 'file_source' in locals(): file_source.close()
        except Exception as exc:
            log_event(logger, logging.WARNING, "arelle_close_failed", component="arelle", exception_type=type(exc).__name__)
        try:
            if 'model_manager' in locals(): model_manager.close()
        except Exception as exc:
            log_event(logger, logging.WARNING, "arelle_close_failed", component="arelle", exception_type=type(exc).__name__)
        try:
            if 'cntlr' in locals(): cntlr.close()
        except Exception as exc:
            log_event(logger, logging.WARNING, "arelle_close_failed", component="arelle", exception_type=type(exc).__name__)
        else:
            log_event(logger, logging.DEBUG, "arelle_close_completed", component="arelle")
