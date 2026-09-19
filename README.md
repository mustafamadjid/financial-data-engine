# HISSA Financial Data Engine

Financial Data Engine adalah pipeline untuk mengambil filing keuangan berbasis XBRL/iXBRL, mengekstrak fakta mentah, menormalisasi konsep ke kamus finansial kanonikal, memvalidasi kualitasnya, lalu menerbitkan data yang sudah memiliki lineage dan siap dikonsumsi HISSA Core.

Repositori ini berbentuk monorepo. Aplikasi web dan orkestrasi pipeline berada di [`financial-data-engine-sandbox/`](financial-data-engine-sandbox/), parser XBRL berada di [`python/xbrl-worker/`](python/xbrl-worker/), dan shared data contract berada di [`contracts/`](contracts/).

> Status: development/sandbox. Kontrak integration API saat ini masih draft `0.1.0` dan belum dimaksudkan untuk langsung dianggap sebagai kontrak production.

## Daftar Isi

- [Tujuan](#tujuan)
- [Siapa yang menggunakan](#siapa-yang-menggunakan)
- [Alur pipeline](#alur-pipeline)
- [Data yang diambil dan diolah](#data-yang-diambil-dan-diolah)
- [Output pipeline](#output-pipeline)
- [Fitur utama](#fitur-utama)
- [Tech stack](#tech-stack)
- [Prasyarat](#prasyarat)
- [Instalasi lokal](#instalasi-lokal)
- [Menjalankan dashboard](#menjalankan-dashboard)
- [Perintah operasional](#perintah-operasional)
- [Testing](#testing)
- [Struktur repositori](#struktur-repositori)
- [Kontrak dan data quality](#kontrak-dan-data-quality)
- [Troubleshooting](#troubleshooting)

## Tujuan

Pipeline ini dibuat untuk menyediakan proses pengolahan data keuangan yang:

- deterministic dan dapat diulang;
- mempertahankan source lineage dari filing sampai data terbit;
- memisahkan fakta mentah, mapping konsep, normalisasi, validasi, dan publikasi;
- mencegah data yang belum terverifikasi masuk ke output publik;
- menyediakan audit trail, status pipeline, retry, dan reprocess yang eksplisit;
- menyediakan workspace operasional bagi Data Analyst dan engineer.

Prinsip data quality yang digunakan antara lain **zero silent guessing**, immutable source record, versioned mapping, dan quality gate. Nilai `null` tidak boleh diperlakukan sebagai angka nol, dan fakta XBRL `nil` harus tetap dipertahankan sebagai kondisi tidak tersedia.

## Siapa yang menggunakan

| Pengguna | Kebutuhan |
| --- | --- |
| Data Analyst / Data Quality Analyst | Meninjau fakta, hasil validasi, warning, restatement, dan bukti sumber. |
| Data Engineer / Full-stack Engineer | Menjalankan pipeline, memperbaiki kegagalan, memelihara mapping, queue, dan integrasi parser. |
| Pipeline Operator | Memantau status filing, retry job, dan memulai reprocess dengan alasan yang tercatat. |
| HISSA Core / consumer API | Mengambil published filing atau snapshot yang sudah lolos quality gate. |
| System Owner / reviewer | Memeriksa audit trail, contract version, dan kesiapan publikasi. |

## Alur pipeline

```text
Discovery
   |
   v
Download artifact
   |
   v
Parse XBRL/iXBRL dengan Python + Arelle
   |
   v
Normalize fakta ke konsep kanonikal
   |
   v
Validate aturan data quality dan rekonsiliasi
   |------------------------------+
   |                              |
   | VERIFIED                     | REVIEW_REQUIRED / FAILED
   v                              v
Publish snapshot             Tahan publikasi, review,
dan expose API               retry, atau reprocess
```

Tahapan executable yang dikonfigurasi aplikasi adalah:

1. **DISCOVER** — menemukan kandidat filing berdasarkan source adapter dan discovery window.
2. **DOWNLOAD** — mengambil artifact yang diizinkan dan menyimpannya sebagai artifact pipeline.
3. **PARSE** — memanggil worker Python untuk membaca dokumen XBRL/iXBRL.
4. **NORMALIZE** — mencocokkan source concept dengan mapping dan menghasilkan normalized fact.
5. **VALIDATE** — menjalankan rule akuntansi, konteks, unit, skala, scope, dan rekonsiliasi.
6. **PUBLISH** — membuat published snapshot hanya ketika filing eligible untuk dipublikasikan.

Status kualitas utama:

- `VERIFIED`: lolos quality gate dan dapat dipublikasikan.
- `REVIEW_REQUIRED`: memiliki warning atau memerlukan keputusan analis.
- `FAILED`: memiliki error blocking atau pipeline stage gagal.

Setiap stage memiliki queue, retry policy, timeout, execution record, correlation ID, dan error handling sendiri. `ANALYTICS` dan `ENRICHMENT` tersedia sebagai reserved workload, tetapi belum menjadi stage pipeline aktif.

## Data yang diambil dan diolah

### Input

- metadata filing dan identitas issuer;
- URL atau lokasi artifact filing;
- file ZIP, XML, XBRL, iXBRL/HTML, spreadsheet, atau PDF sesuai konfigurasi downloader;
- XBRL contexts, units, dimensions, dan facts;
- source concept, namespace, entry point, periode, scope, currency, dan atribut `nil`;
- kamus konsep kanonikal dan mapping rule dari artefak DA-1-3;
- aturan validasi dan parameter rekonsiliasi.

### Record internal

Contract internal mendefinisikan record berikut:

| Layer | Record | Fungsi |
| --- | --- | --- |
| Source | `filing_metadata`, `context`, `unit`, `dimension`, `raw_fact` | Menyimpan representasi sumber dan hasil ekstraksi. |
| Semantic | `mapping_rule`, `normalized_fact` | Menyimpan mapping dan fakta yang sudah dinormalisasi. |
| Quality | `validation_result`, `status_enums` | Menyimpan hasil rule dan status kualitas. |
| Analytics | `metric` | Menyimpan metric turunan beserta input fact. |
| Debt | `debt_record`, `evidence_record` | Menyimpan structured debt dan buktinya. |

Lineage menghubungkan `filing_id` ke semua record turunan. `raw_fact` bersifat immutable; perubahan sumber dibuat sebagai filing/revisi baru dan tidak menimpa histori.

## Output pipeline

Output yang dihasilkan meliputi:

- record filing dan status pemrosesannya;
- parser result berupa JSON yang berisi contexts, units, dimensions, facts, warnings, errors, counts, contract version, dan source SHA-256;
- normalized facts dengan canonical concept, value, currency, periode, scope, mapping version, dan source lineage;
- validation results dengan severity, rule code, evidence, dan quality status;
- pipeline run, job run, audit log, retry metadata, dan correlation ID;
- filing artifacts dan temporary/published artifacts pada private storage;
- `PublishedSnapshot` yang hanya dibuat untuk data eligible;
- endpoint read-only API v1 untuk filing, snapshot, daftar filing issuer, dan export.

Endpoint API yang tersedia:

```text
GET /api/v1/filings/{filing_id}
GET /api/v1/snapshots/{snapshot_id}
GET /api/v1/issuers/{issuer_code}/filings
GET /api/v1/filings/{filing_id}/export
```

Detail kontrak API ada di [`contracts/api/v1/`](contracts/api/v1/), sedangkan kontrak pipeline ada di [`contracts/v1/`](contracts/v1/) dan [`contracts/v2/`](contracts/v2/).

## Fitur utama

### Pipeline operations dashboard

Tersedia di `/ops/pipeline` untuk:

- melihat ringkasan dan daftar filing;
- memfilter status, stage, issuer, periode, dan error;
- membuka detail filing dan riwayat pipeline;
- melihat artifact serta error terakhir;
- melakukan retry untuk job yang gagal;
- melakukan reprocess mulai dari stage tertentu dengan alasan eksplisit.

### Review workspace

- `/ops/financial-review` untuk meninjau normalized financial facts;
- `/ops/concept-mappings` untuk melihat mapping, history, impact, membuat versi mapping, dan memproses ulang filing terdampak;
- `/ops/data-quality` untuk meninjau validation result, summary, severity, dan detail rule;
- `/ops/debt-review` untuk review debt record dan coverage.

### Reliability dan governance

- queue terpisah untuk discovery, download, XBRL, normalize, validate, dan publish;
- timeout dan backoff per stage;
- idempotency key dan overlap protection;
- retry serta reprocess versioned;
- audit trail dan correlation ID;
- private storage untuk artifact pipeline;
- publish eligibility policy berbasis quality status;
- rate limit API dan canonical error response;
- contract versioning untuk menjaga kompatibilitas consumer.

## Tech stack

| Area | Teknologi |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Web UI | Inertia.js, Vue 3, TypeScript, Vue Query |
| Frontend tooling | Bun 1.3.14, Vite, Tailwind CSS |
| Database | MySQL secara default pada `.env.example`; SQLite tersedia untuk test |
| Queue dan cache | Redis melalui Predis dan Laravel Queue |
| XBRL parser | Python 3.13 + `arelle-release==2.44.4` |
| Contract | JSON Schema, OpenAPI, versioned JSON contracts |
| Testing | Pest/PHPUnit, Vitest, pytest |
| Storage | Laravel private local disk; dapat dioverride ke disk lain |

## Prasyarat

Pastikan tool berikut tersedia:

- Git;
- PHP `8.3` atau lebih baru dengan Composer;
- ekstensi PHP yang dibutuhkan Laravel, termasuk PDO driver MySQL;
- Bun `1.3.14`;
- Python `3.13`;
- MySQL atau MariaDB;
- Redis.

Untuk menjalankan pipeline penuh, MySQL dan Redis harus aktif sebelum worker dijalankan. Frontend dapat dibuild tanpa Redis, tetapi job pipeline tidak akan berjalan tanpa queue backend.

## Instalasi lokal

Langkah berikut ditulis untuk Windows PowerShell. Perintah Bash dapat disesuaikan dengan path dan sintaks shell masing-masing.

### 1. Siapkan database dan Redis

Buat database kosong sesuai nilai default `.env.example`:

```sql
CREATE DATABASE financial_data_engine_sandbox
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Pastikan Redis tersedia di `127.0.0.1:6379`, atau ubah `REDIS_HOST`, `REDIS_PORT`, dan kredensialnya di `.env`.

### 2. Install dependency aplikasi

Dari root repository:

```powershell
cd financial-data-engine-sandbox
composer install
bun install --frozen-lockfile
Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env` bila koneksi MySQL atau Redis berbeda dari default. Minimal konfigurasi lokal yang relevan:

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=financial_data_engine_sandbox
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 3. Install parser Python

Buka terminal PowerShell lain atau jalankan dari terminal yang sama setelah menyelesaikan langkah berikut:

```powershell
cd ..\python\xbrl-worker
py -3.13 -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
python -m pip install -r requirements-dev.txt
python -c "from importlib.metadata import version; print(version('arelle-release'))"
```

Output versi yang diharapkan adalah `2.44.4`.

Secara default Laravel menjalankan command `python -m hissa_xbrl_worker` dari direktori `../python/xbrl-worker`. Jika executable Python tidak ditemukan, override konfigurasi berikut di `.env`:

```dotenv
FINANCIAL_PIPELINE_PARSER_EXECUTABLE=C:/path/to/repository/python/xbrl-worker/.venv/Scripts/python.exe
FINANCIAL_PIPELINE_PARSER_PATH=C:/path/to/repository/python/xbrl-worker
```

### 4. Jalankan migration dan seed rule

Kembali ke direktori aplikasi:

```powershell
cd ..\..\financial-data-engine-sandbox
php artisan migrate
php artisan db:seed --class=ValidationRuleSeeder
```

`ValidationRuleSeeder` menginstal rule DA yang dikonfigurasi, termasuk rule `ACC`, `HRY`, `CTX`, `SCL`, `CRX`, dan `SCP`. Jalankan seeder setelah artefak/rule version yang sesuai sudah disetujui untuk environment tersebut.

### 5. Build asset frontend

```powershell
bun run build
```

Untuk alur otomatis yang menggabungkan sebagian langkah setup di atas, aplikasi juga menyediakan:

```powershell
composer run setup
```

Perintah tersebut menjalankan install dependency, membuat `.env` jika belum ada, membuat app key, migration, install Bun, dan production asset build. Database dan Redis tetap harus disiapkan terlebih dahulu; setup tidak membuat database MySQL.

## Menjalankan dashboard

Jalankan dari `financial-data-engine-sandbox`:

```powershell
composer run dev
```

Script ini menjalankan tiga proses secara bersamaan:

1. Laravel development server;
2. queue worker Redis dengan prioritas `publish`, `validate`, `normalize`, `xbrl`, `downloads`, `discovery`, `analytics`, dan `enrichment`;
3. Vite development server.

Buka dashboard di:

```text
http://127.0.0.1:8000/ops/pipeline
```

Halaman workspace lain:

```text
http://127.0.0.1:8000/ops/financial-review
http://127.0.0.1:8000/ops/concept-mappings
http://127.0.0.1:8000/ops/data-quality
http://127.0.0.1:8000/ops/debt-review
```

Health check Laravel tersedia di `http://127.0.0.1:8000/up`.

Jika ingin menjalankan proses secara terpisah:

```powershell
# Terminal 1: web server
php artisan serve

# Terminal 2: queue worker
php artisan queue:work redis-xbrl --queue=publish,validate,normalize,xbrl,downloads,discovery,analytics,enrichment --sleep=1 --max-time=3600

# Terminal 3: Vite
bun run dev
```

## Perintah operasional

Semua command berikut dijalankan dari `financial-data-engine-sandbox`.

### Menemukan filing

```powershell
php artisan financial-data:discover <sourceAdapter> <discoveryWindow> --page-size=10
```

Command ini melakukan dispatch discovery job ke queue `discovery`.

### Melihat status filing

```powershell
php artisan financial-data:status <filing-id>
```

Output menampilkan processing stage, quality status, pipeline run, job terakhir, error terakhir, dan published snapshot.

### Reprocess filing

```powershell
php artisan financial-data:reprocess <filing-id> <DOWNLOAD|PARSE|NORMALIZE|VALIDATE|PUBLISH> --reason="<alasan>"
```

Alasan reprocess wajib diisi agar perubahan dapat diaudit.

### Import artefak DA-1-3

```powershell
php artisan financial-data:import-da <path> <version> --dry-run
php artisan financial-data:import-da <path> <version>
```

Gunakan `--dry-run` untuk memvalidasi tanpa menyimpan perubahan.

### Command Laravel umum

```powershell
php artisan route:list --path=ops
php artisan migrate:status
php artisan queue:failed
php artisan queue:retry all
php artisan optimize:clear
```

## Testing

### Backend

```powershell
cd financial-data-engine-sandbox
composer test
```

Test backend menggunakan SQLite in-memory sesuai konfigurasi `phpunit.xml`.

### Frontend

```powershell
bun run test:frontend
```

### Parser Python

```powershell
cd ..\python\xbrl-worker
\.venv\Scripts\Activate.ps1
python -m pytest -q
python -m pytest --cov=hissa_xbrl_worker --cov-report=term-missing
```

Smoke test parser menggunakan fixture minimal di `python/xbrl-worker/tests/fixtures/`.

### Contract

Contract API dan contract pipeline memiliki fixture valid/invalid serta test kompatibilitas. Lihat:

- [`contracts/tests/test_v2_contracts.py`](contracts/tests/test_v2_contracts.py)
- [`contracts/api/v1/README.md`](contracts/api/v1/README.md)
- [`contracts/v1/README.md`](contracts/v1/README.md)
- [`contracts/v2/README.md`](contracts/v2/README.md)

## Struktur repositori

```text
.
├── contracts/                         # Shared JSON/OpenAPI data contracts
├── DA-1-3/                            # Dictionary, mapping, dan validation artifacts
├── financial-data-engine-sandbox/     # Laravel + Inertia/Vue application
│   ├── app/                           # Domain, application, jobs, models, controllers
│   ├── database/                      # Migrations dan seeders
│   ├── resources/js/                  # Vue pages, features, dan ops components
│   ├── routes/                        # Web dan API routes
│   ├── storage/                       # Local runtime storage
│   └── tests/                         # PHP dan frontend tests
├── python/xbrl-worker/                # One-shot XBRL parser worker
├── extractor-by-mustafa/              # Eksperimen/implementasi extractor terkait
└── *.md, *.pdf                        # Brief dan catatan desain proyek
```

## Kontrak dan data quality

### Versioning

Contract menggunakan semantic versioning. Perubahan breaking harus menggunakan major version baru dan, untuk HTTP API, namespace baru seperti `/api/v2`. Nilai monetary/decimal yang melewati runtime boundary dikirim sebagai string untuk menjaga precision.

### Validasi

Rule validation mencakup:

- accounting equation dan subtotal laporan;
- period, context, dan dimensional consistency;
- currency, unit, scale, dan plausibility;
- cross-period consistency dan restatement;
- reporting scope dan preservation untuk nilai `nil`.

Filing dengan error blocking menjadi `FAILED`, filing dengan warning menjadi `REVIEW_REQUIRED`, dan hanya filing `VERIFIED` yang boleh masuk proses publish otomatis.

Dokumentasi terkait:

- [`DA-1-3/DA-1/README.md`](DA-1-3/DA-1/README.md) — extraction dan profiling XBRL;
- [`DA-1-3/DA-2/mapping-rules.md`](DA-1-3/DA-2/mapping-rules.md) — mapping konsep kanonikal;
- [`DA-1-3/DA-3/validation-rulebook.md`](DA-1-3/DA-3/validation-rulebook.md) — rulebook data quality;
- [`python/xbrl-worker/README.md`](python/xbrl-worker/README.md) — parser contract dan CLI.

## Troubleshooting

### Dashboard tidak bisa dibuka

Pastikan `php artisan serve` masih berjalan dan port `8000` belum digunakan. Jalankan manual dengan port lain jika diperlukan:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

Sesuaikan `APP_URL` jika menggunakan port berbeda.

### Queue job tidak diproses

Periksa Redis, `.env`, dan worker:

```powershell
php artisan config:clear
php artisan queue:work redis-xbrl --queue=publish,validate,normalize,xbrl,downloads,discovery --verbose
```

Pastikan `QUEUE_CONNECTION=redis`, `REDIS_HOST`, dan `REDIS_PORT` sesuai.

### Parser XBRL gagal dijalankan

Periksa virtual environment dan jalankan parser secara langsung:

```powershell
cd python/xbrl-worker
\.venv\Scripts\Activate.ps1
python -c "from importlib.metadata import version; assert version('arelle-release') == '2.44.4'"
python -m hissa_xbrl_worker --input tests/fixtures/minimal-valid/minimal-instance.xbrl --filing-id filing_01 --pretty
```

Jika Laravel menggunakan Python global, set `FINANCIAL_PIPELINE_PARSER_EXECUTABLE` ke `python.exe` di virtual environment.

### Migration gagal

Periksa database dan kredensial di `.env`, lalu bersihkan cache konfigurasi:

```powershell
php artisan config:clear
php artisan migrate:status
```

Jangan menggunakan `migrate:fresh` pada database yang berisi data penting karena command tersebut menghapus seluruh tabel.

## Lisensi dan kontribusi

Ikuti aturan kontribusi dan review yang berlaku di proyek. Setiap perubahan pada contract, mapping, validation rule, atau publish payload harus disertai pembaruan fixture/test dan review dari pihak yang memiliki domain tersebut.
