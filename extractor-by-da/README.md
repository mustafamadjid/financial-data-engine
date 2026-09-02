# IDX XBRL Raw Extraction — DA-1 Handover

Repository ini adalah paket handover DA-1 untuk mengekstrak filing XBRL IDX menjadi data mentah yang dapat diaudit. Extractor membaca `instance.zip` dan menghasilkan facts, contexts, units, serta dimensions tanpa hard-code ticker.

Extractor **tidak** melakukan canonical mapping, memilih nilai final, menghitung metrik, atau mengklasifikasikan syariah.

## Isi yang diunggah

Hanya deliverable minimum DA-1 berikut yang diizinkan oleh `.gitignore`:

| Path | Fungsi |
| --- | --- |
| `docs/idx-taxonomy-notes.md` | Entry point, context, unit/decimals, konvensi periode, dan note node penting. |
| `docs/source-issues.md` | Anomali, gap sumber, serta aturan penanganan. |
| `scripts/extract_xbrl.py` | Extractor XBRL yang dapat dijalankan ulang. |
| `data/extracted/raw_facts.csv` | Semua fact XBRL mentah dari pilot. |
| `data/extracted/contexts.csv` | Context entity, periode, dan dimensions. |
| `data/extracted/units.csv` | Unit mata uang dan per-saham. |
| `data/extracted/dimensions.csv` | Explicit/typed dimensions per context. |

## Prasyarat

- Python 3.12 atau Python 3 yang kompatibel.
- Tidak ada dependency pihak ketiga. `requirements.txt` hanya mendokumentasikan bahwa script memakai Python standard library.

```powershell
python --version
```

## Menjalankan ulang extractor

Siapkan source XBRL dalam struktur berikut:

```text
data/raw/<TICKER>/<PERIODE>/instance.zip
```

Contoh:

```text
data/raw/ANTM/2026Q2/instance.zip
```

Setiap ZIP harus memiliki file instance `.xbrl`, misalnya `instance.xbrl`.

Jalankan dari root repository:

```powershell
python scripts/extract_xbrl.py --input data/raw --output data/extracted
```

Perintah tersebut membuat ulang `raw_facts.csv`, `contexts.csv`, `units.csv`, dan `dimensions.csv`. File output dengan nama sama akan ditimpa.

Untuk menjaga hasil repository yang sudah diunggah, gunakan folder output lain saat pengujian:

```powershell
python scripts/extract_xbrl.py --input data/raw --output data/extracted-rerun
```

### Menjalankan satu filing

Filter ticker dan periode dapat digunakan tanpa membuat folder staging. Gunakan output terpisah agar hasil pilot tidak tertimpa:

```powershell
python scripts/extract_xbrl.py `
  --input data/raw `
  --output data/rerun-output `
  --ticker ANTM `
  --period 2025Q1
```

## Validasi singkat

Extractor menampilkan jumlah filing, contexts, units, facts, dan errors. Exit code `0` berarti seluruh filing berhasil diekstrak; exit code `2` berarti ada filing gagal dan perlu diperiksa.

```powershell
Import-Csv data/extracted/contexts.csv | Select-Object -First 5
Import-Csv data/extracted/units.csv | Select-Object -First 5
```

## Catatan penting

- Pertahankan `source_concept`, `concept_namespace`, `context_id`, `unit_id`, `decimals`, dan `is_nil` saat memakai raw facts.
- `nil` bukan nol.
- Jangan memilih total statement dari fact berdimensi tanpa rule yang disetujui.
- Jangan menebak mapping, scope, atau klasifikasi syariah tanpa evidence filing.
