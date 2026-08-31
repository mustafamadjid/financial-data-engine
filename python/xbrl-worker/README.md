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

## Program behavior
This worker is a one-shot CLI process, not a server. It parses one input file for one filing, writes its result, then exits with an exit code. Laravel or another caller can start one worker process per filing.

## Run parser
Run the worker directly to see compact operational logs in the terminal:

```powershell
python -m hissa_xbrl_worker `
  --input tests/fixtures/minimal-valid/minimal-instance.xbrl `
  --filing-id filing_01
```

When stdout is an interactive terminal, the complete parser result is intentionally hidden so the terminal only shows formatted logs. Use `--pretty` to print the full parser result for manual inspection:

```powershell
python -m hissa_xbrl_worker `
  --input tests/fixtures/minimal-valid/minimal-instance.xbrl `
  --filing-id filing_01 `
  --pretty
```

### Save the parser result and logs to files
Redirect stdout to a result file and stderr to a log file. This is the recommended command for local debugging, automation, and Laravel integration:

```powershell
python -m hissa_xbrl_worker `
  --input tests/fixtures/minimal-valid/minimal-instance.xbrl `
  --filing-id filing_01 `
  --correlation-id job-123 `
  --log-level INFO `
  1> result.json `
  2> parser.log
```

`result.json` contains the complete parser contract. `parser.log` contains only compact terminal logs.

Verify both files:

```powershell
Get-Content result.json | ConvertFrom-Json | Format-List
Get-Content parser.log
```

## Response contract
The envelope contains `parser_contract`, runtime metadata, source SHA-256, counts, and `contexts`, `units`, `dimensions`, `facts`, `warnings`, and `errors` arrays. Records follow `contracts/v1` field names and preserve source lineage.

## Exit codes
`0` success; `2` invalid CLI arguments; `10` unreadable input; `11` unsupported document; `12` Arelle load failure; `13` extraction failure; `14` serialization failure; `20` unexpected internal error.

## Run tests
```powershell
python -m pytest -q
python -m pytest --cov=hissa_xbrl_worker --cov-report=term-missing
```

## Troubleshooting
Ensure the virtual environment is active, Arelle is exactly version 2.44.4, and the input path is readable. When stdout is redirected, it contains one JSON parser result; diagnostics always go to stderr.

## Architecture boundaries
Laravel resolves a local source path, starts this process, enforces timeout, checks the exit code, validates the parser contract version, and persists records transactionally. Future integration should use Symfony Process or a Laravel-compatible process abstraction. This worker has no database, Redis, HTTP, queue, or FastAPI dependency.

## Dependency upgrade policy
Do not upgrade Arelle as part of parser feature work. A version upgrade requires a separate compatibility review and regression/golden-output update.

Laravel caller flow: resolve executable → run `python -m hissa_xbrl_worker --input <path> --filing-id <id>` → enforce timeout → parse stdout → validate contract version → persist records. No Laravel job or database adapter is implemented here.

## Logging
Operational logs use a compact terminal format on `stderr`:

```text
HH:mm:ss LEVEL [component] event key=value ...
```

For example:

```text
20:26:14 INFO  [xbrl] parse_completed filing=filing_01 facts=1 contexts=1 units=1 dimensions=0 warnings=0 errors=0 duration=84ms
```

Supported CLI levels are `DEBUG`, `INFO` (default), `WARNING`, and `ERROR`:

```powershell
python -m hissa_xbrl_worker `
  --input tests/fixtures/minimal-valid/minimal-instance.xbrl `
  --filing-id filing_01 `
  --log-level DEBUG
```

`DEBUG` adds internal extraction details. `INFO` records normal lifecycle events. `WARNING` records recoverable conditions, and `ERROR` records failures. The terminal shortens the Python level `WARNING` to `WARN` for readability.

If omitted, the correlation ID is generated for logs only. Logging metadata never enters the parser response and never changes deterministic parser output.

The same log records can be rendered as versioned JSON Lines by Python integrations that call:

```python
configure_logging("INFO", output_format="json")
```

Each JSON event contains `schema`, `schema_version`, UTC `timestamp`, `level`, `logger`, `event`, `message`, `correlation_id`, and `worker_version`. A known `filing_id`, source filename, SHA-256, counts, error code, and duration may be added where relevant.

Raw fact values, full XBRL/XML content, secrets, tokens, and credentials are not logged. Absolute source paths are not included at `INFO`; logging is operational telemetry, not audit persistence. Laravel can capture `stderr` separately from the JSON result on `stdout`.
