# Phase 3 Status (2026-03-30)

## Scope
Non-destructive DB structure rollout only.
No destructive migration executed.

## Applied migrations (live dataset)
1. `database/migrations/2026-03-30_01_create_patient_data_latest.sql`
2. `database/migrations/2026-03-30_02_backfill_patient_data_latest.sql` (chunked by patient)
3. `database/migrations/2026-03-30_03_create_patient_data_latest_trigger.sql`

## Incident during rollout
- Monolithic backfill attempt failed with:
  - `ERROR 1206 (HY000): The total number of locks exceeds the lock table size`
- Resolution:
  - switched to chunked backfill (per-patient cursor), then reran successfully.

## Evidence
- Post-check snapshot and trigger:
  - `artifacts/postcheck_snapshot.tsv`
- Chunked parity report:
  - `artifacts/parity_snapshot_checks_chunked.tsv`
- Patient 8 sample parity:
  - `artifacts/parity_sample_patient8.tsv`
- Healthcheck fix verification:
  - `artifacts/healthcheck_verification.txt`

## Key parity outcomes (live dataset)
- `snapshot_rows=930`
- `patients_checked=30`
- `series_checked=930`
- `missing_snapshot_rows=0`
- `snapshot_behind_history=0`
- `snapshot_missing_source_seq=0`

## OPS-001
- `docker-compose.yml` healthcheck now authenticated:
  - `CMD-SHELL mysqladmin ping -h localhost -udashmed -p$${MYSQL_PASSWORD} --silent`
- DB logs after restart: no recurring `Access denied for user 'root'@'localhost' (using password: NO)`.

## Remaining gap before closing Phase 3
- Rerun parity validation on isolated restored dataset with the same additive migration set.
- Blocker: phase-0 physical snapshot payloads are not present in local workspace (`database/safety-gates/2026-03-27_phase0/artifacts/` empty).
