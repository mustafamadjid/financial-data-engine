from dataclasses import dataclass

@dataclass
class WorkerError(Exception):
    code: str
    message: str
    exit_code: int
    def __str__(self) -> str: return self.message

INPUT_FILE_ERROR = "INPUT_FILE_ERROR"
UNSUPPORTED_DOCUMENT = "UNSUPPORTED_DOCUMENT"
XBRL_LOAD_ERROR = "XBRL_LOAD_ERROR"
EXTRACTION_ERROR = "EXTRACTION_ERROR"
SERIALIZATION_ERROR = "SERIALIZATION_ERROR"
INTERNAL_ERROR = "INTERNAL_ERROR"
