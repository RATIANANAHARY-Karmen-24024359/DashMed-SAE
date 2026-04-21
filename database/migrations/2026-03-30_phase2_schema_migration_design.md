# Phase 2 - Schema Target & Migration Design (No Destructive Execution)

Date: 2026-03-30

## Scope
This package finalizes the DB target design and migration sequence for `patient_data` performance work while preserving immutable clinical time-series semantics.

Guardrails:
- No destructive migration is executed in Phase 2.
- Existing API contracts remain stable during additive rollout.
- `seq` is the ordering source of truth for incremental synchronization.
- Full history remains immutable and accessible.

## Target Schema (final state)
- `patient_data`
  - `PRIMARY KEY (seq)` (clustered append-friendly write path)
  - `UNIQUE (id_patient, parameter_id, timestamp)` (business identity preservation)
  - Keep workload indexes:
    - `(id_patient, parameter_id, seq)` for chunk sync by cursor
    - `(id_patient, archived, timestamp)` for timestamp-window stream reads
- `patient_data_latest`
  - `PRIMARY KEY (id_patient, parameter_id)`
  - stores latest `seq`, `timestamp`, `value`, `alert_flag`, `updated_at`
  - maintained on insert path via upsert trigger during coexistence phase

## Migration Sequence (mandatory order)
1. Phase 3 additive migration: create `patient_data_latest`.
2. Phase 3 additive migration: backfill `patient_data_latest` from historical `patient_data` using `MAX(seq)` per key (chunked-by-patient execution on large datasets).
3. Phase 3 additive migration: install insert-trigger to keep snapshot updated.
4. Phase 4 query cutover: switch hottest latest/alerts paths to snapshot-first reads.
5. Phase 6 cleanup (destructive, gated): rekey `patient_data` PK to `seq`, keep business unique key, drop legacy redundant index.

## Compatibility Matrix (per step)

| Step | Input shape | Output shape | API contract impact | Rollback |
|---|---|---|---|---|
| 3.1 create snapshot table | Existing `patient_data` (`seq` unique) | New empty `patient_data_latest` | None (additive only) | `DROP TABLE patient_data_latest` |
| 3.2 backfill snapshot | Historical immutable `patient_data` rows | Latest row per (`id_patient`,`parameter_id`) by `MAX(seq)` | None (reads unchanged) | `TRUNCATE patient_data_latest` then rerun backfill |
| 3.3 trigger maintenance | New inserts on `patient_data` | Upserted latest snapshot row | None (coexistence before read switch) | `DROP TRIGGER trg_patient_data_latest_after_insert` |
| 4.x read-path switch | Snapshot populated + maintained | Latest/alerts SQL reads from snapshot | Endpoint payload unchanged (same fields/semantics) | Feature-flag/SQL fallback to legacy path |
| 6.x PK rekey cleanup | Snapshot path validated + parity pass | `patient_data` PK on `seq`, business key unique | None expected if parity checks pass | Restore from validated backup gate checkpoint |

## Validation Checklist (must pass before cleanup)
- Data parity:
  - For sampled patients/parameters/windows: latest `(seq,timestamp,value,alert_flag)` from legacy query == snapshot query.
  - `COUNT(DISTINCT id_patient, parameter_id)` in snapshot equals active series count in history.
- API parity:
  - `/api_live_metrics` and `/api-alerts` payloads unchanged (keys/types/order-insensitive values).
  - `/api_history_*` exactness unchanged (point count and exact values).
- Performance evidence:
  - `EXPLAIN` plans no longer show large `MAX(timestamp)` grouped scans for latest/alerts.
  - Endpoint p95 improvements recorded against Phase 1 baseline.
- Rollback readiness:
  - Backup/restore gate remains green.
  - Rehearsed rollback SQL documented and executable.

## SQL Scripts Added In This Package
- `database/migrations/2026-03-30_01_create_patient_data_latest.sql`
- `database/migrations/2026-03-30_02_backfill_patient_data_latest.sql`
- `database/migrations/2026-03-30_03_create_patient_data_latest_trigger.sql`
- `database/migrations/2026-03-30_90_phase6_cleanup_patient_data_rekey.sql` (do not run before Phase 6 gates)

## Notes
- The Phase 6 script is intentionally separated and explicitly gated as destructive.
- No clinical data mutation or aggregation is introduced in this design.
