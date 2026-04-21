# Phase 3 Migration Apply Log (2026-03-30)

## Commands executed
1. `2026-03-30_01_create_patient_data_latest.sql` applied on `dashmed_data`.
2. Initial `2026-03-30_02_backfill_patient_data_latest.sql` (monolithic) failed.
3. `2026-03-30_02_backfill_patient_data_latest.sql` updated to chunked-by-patient and reapplied successfully.
4. `2026-03-30_03_create_patient_data_latest_trigger.sql` applied successfully.

## Failure detail (captured)
- Error:
  - `ERROR 1206 (HY000): The total number of locks exceeds the lock table size`
- Context:
  - occurred during monolithic backfill query on large live dataset.
- Mitigation:
  - switched to per-patient cursor backfill.

## Post-apply checks
- `postcheck_snapshot.tsv`: table + trigger present.
- `parity_snapshot_checks_chunked.tsv`: no missing/behind snapshot rows.
