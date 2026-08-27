# HISSA Financial Data Engine Contract v1

Contract v1 adalah shared data contract JSON untuk pipeline IDX filing sampai publish. Contract ini bukan database schema; setiap record mempertahankan stable identifier dan source lineage.

## Daftar contract

| Layer | Contract | Peran |
|---|---|---|
| Source | `filing_metadata`, `context`, `unit`, `dimension`, `raw_fact` | Identitas filing dan representasi hasil ekstraksi |
| Semantic | `mapping_rule`, `normalized_fact` | Mapping canonical dengan lineage |
| Quality | `validation_result`, `status_enums` | Hasil validasi dan vocabulary status |
| Analytics | `metric` | Metric turunan dan input fact |
| Debt | `debt_record`, `evidence_record` | Structured debt dengan bukti sumber |

Semua contract memakai envelope `contract`, `version`, `description`, `type`, `required`, `properties`, dan `example`. Nilai moneter/decimal dikirim sebagai string untuk menjaga precision. `null` hanya berarti field tidak tersedia; workflow state wajib memakai status eksplisit.

## Relasi lineage

```text
filing_metadata.filing_id
  ├── raw_fact.filing_id
  ├── context.filing_id
  ├── unit.filing_id
  ├── normalized_fact.filing_id
  ├── validation_result.filing_id
  ├── metric.filing_id
  ├── debt_record.filing_id
  └── evidence_record.filing_id
raw_fact.context_ref -> context.context_id
raw_fact.unit_ref -> unit.unit_id
dimension.context_id -> context.context_id
normalized_fact.raw_fact_id -> raw_fact.raw_fact_id
normalized_fact.mapping_rule_id -> mapping_rule.mapping_rule_id
validation_result.normalized_fact_ids[] -> normalized_fact.normalized_fact_id
metric.input_fact_ids[] -> normalized_fact.normalized_fact_id
debt_record.evidence_ids[] -> evidence_record.evidence_id
```

Derived record tidak boleh diterbitkan jika lineage wajibnya tidak dapat di-resolve. Raw fact immutable: revisi sumber dibuat sebagai filing baru dengan `revision_number` dan `supersedes_filing_id`.

## Null dan status

Gunakan `null` untuk nilai yang tidak ada pada record. Gunakan `UNKNOWN` bila domain mengharuskan status ketidaktahuan, `UNMAPPED` bila mapping belum tersedia, dan `REVIEW_REQUIRED` bila perlu keputusan manusia. Jangan memperlakukan `null` sebagai angka nol.

## Versioning

Contract memakai Semantic Versioning. PATCH memperbaiki dokumentasi/example; MINOR menambah optional field secara backward-compatible; MAJOR mengubah nama/tipe/semantics atau required-ness. Breaking change membuat file contract baru dengan major version baru.

## Fixture dan verifikasi

Fixture valid berada di `fixtures/`, negative fixture di `fixtures/invalid/`. Test `tests/test_contracts.py` memeriksa envelope, metadata field, enum, fixture required fields, dan semua relasi lineage. Contract v1 belum mencakup migration, model Laravel/Pydantic, endpoint, OpenAPI, atau backfill produksi.

Contract tetap memerlukan review Full Stack Engineer dan Data Analyst sebelum dinyatakan stable.
