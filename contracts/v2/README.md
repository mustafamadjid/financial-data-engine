# HISSA Financial Data Engine Contract v2

Contract v2 is the DA-1-3 aligned write contract. Contract v1 remains readable
for historical records but must not be used as the writer contract after the
v2 cutover.

## Required DA-1-3 lineage

- Mapping identity is `source_namespace + source_concept + entry_point + rule_version`.
- Raw facts retain the source XML element identifier in `source_element_id`.
- Monetary and decimal values crossing a runtime boundary are strings.
- XBRL `nil` is represented by `is_nil=true`, `raw_value` retained, and no
  numeric zero coercion.
- Normalized availability is independent from workflow quality: `AVAILABLE`,
  `NIL`, `MISSING`, `NOT_APPLICABLE`, and `UNKNOWN` are explicit values.
- `DRAFT` is the runtime equivalent of DA `PROPOSED`; only `APPROVED` mappings
  can normalize facts.

## Compatibility

Readers may accept v1 historical payloads during the migration window. New
parser output, mapping writes, and published records must identify v2. An
unknown major version is a terminal contract error.
