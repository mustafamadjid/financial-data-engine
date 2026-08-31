# HISSA XBRL Parser Worker

## Purpose
Parse one local XBRL/iXBRL filing into deterministic raw structured data.

## Scope
The worker extracts contexts, units, dimensions, and raw facts. It does not normalize facts, map concepts, validate financial meaning, persist data, or run a web/queue service.

## Prerequisites
Python 3.13.x is the supported baseline. Arelle is pinned to `arelle-release==2.44.4`.

## Windows installation
```powershell
cd python/xbrl-worker
py -3.13 -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install -r requirements-dev.txt
```

## Linux/macOS installation
```bash
cd python/xbrl-worker
python3.13 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements-dev.txt
```

## Verify Arelle
```bash
python -c "from importlib.metadata import version; assert version('arelle-release') == '2.44.4'"
```

## Run parser
```bash
python -m hissa_xbrl_worker --input tests/fixtures/minimal-valid/minimal-instance.xbrl --filing-id filing_01
```
Use `--pretty` for developer-readable output. Default output is compact deterministic JSON.

## Response contract
The envelope contains `parser_contract`, runtime metadata, source SHA-256, counts, and `contexts`, `units`, `dimensions`, `facts`, `warnings`, and `errors` arrays. Records follow `contracts/v1` field names and preserve source lineage.

## Exit codes
`0` success; `2` invalid CLI arguments; `10` unreadable input; `11` unsupported document; `12` Arelle load failure; `13` extraction failure; `14` serialization failure; `20` unexpected internal error.

## Run tests
```bash
pytest -v
pytest --cov=hissa_xbrl_worker --cov-report=term-missing
```

## Troubleshooting
Ensure the virtual environment is active, Arelle is exactly version 2.44.4, and the input path is readable. Diagnostics go to stderr; stdout remains one JSON document.

## Architecture boundaries
Laravel resolves a local source path, starts this process, enforces timeout, checks the exit code, validates the parser contract version, and persists records transactionally. Future integration should use Symfony Process or a Laravel-compatible process abstraction. This worker has no database, Redis, HTTP, queue, or FastAPI dependency.

## Dependency upgrade policy
Do not upgrade Arelle as part of parser feature work. A version upgrade requires a separate compatibility review and regression/golden-output update.

Laravel caller flow: resolve executable → run `python -m hissa_xbrl_worker --input <path> --filing-id <id>` → enforce timeout → parse stdout → validate contract version → persist records. No Laravel job or database adapter is implemented here.

## Structured Logging
Logs use versioned JSON Lines on `stderr`; `stdout` remains exactly one parser JSON document. Each application event contains `schema`, `schema_version`, UTC `timestamp`, `level`, `logger`, `event`, `message`, `correlation_id`, and `worker_version`. A known `filing_id`, source filename, SHA-256, counts, error code, and duration may be added where relevant.

```powershell
python -m hissa_xbrl_worker `
  --input tests/fixtures/minimal-valid/minimal-instance.xbrl `
  --filing-id filing_01 `
  --correlation-id job-123 `
  --log-level INFO `
  1> result.json `
  2> parser.log
```

Supported levels are `DEBUG`, `INFO` (default), `WARNING`, and `ERROR`. If omitted, the correlation ID is generated for logs only. Logging metadata never enters the parser response and never changes deterministic parser output.

Raw fact values, full XBRL/XML content, secrets, tokens, and credentials are not logged. Absolute source paths are not included at `INFO`; logging is operational telemetry, not audit persistence. Laravel can later capture `stderr` separately from parser `stdout`.

Every non-empty line in `parser.log` is independently valid JSON:

```powershell
Get-Content parser.log | ForEach-Object { $_ | ConvertFrom-Json | Out-Null }
python -m json.tool result.json
```
