# Database migrations

This directory contains SQL migrations for phased, safety-gated evolution of DashMed time-series storage.

## Execution policy
- Apply migrations in phase order only.
- Additive migrations first.
- Destructive migrations are blocked until safety + compatibility + parity gates pass.

## Existing migrations
- `2026-03-10_add_seq_cursor.sql`
  - Adds monotonic `seq` cursor on `patient_data`.
- `2026-03-11_add_chart_animation.sql`
  - Adds user preference `users.chart_animation`.
- `add_patient_alert_threshold.sql`
  - Adds per-patient threshold overrides.

## Phase 3 additive package (safe before read-path cutover)
1. `2026-03-30_01_create_patient_data_latest.sql`
2. `2026-03-30_02_backfill_patient_data_latest.sql`
3. `2026-03-30_03_create_patient_data_latest_trigger.sql`
4. `2026-03-30_04_fix_patient_data_latest_upsert_order.sql`
5. `2026-03-30_05_refresh_patient_data_latest_chunked.sql`

These scripts introduce and maintain `patient_data_latest` without changing existing read queries.
On large datasets, backfill is chunked by patient to avoid lock-table overflow.
The phase-3 hotfix fixes trigger assignment ordering and refreshes snapshot payload fields from immutable history.

## Phase 6 destructive package (strictly gated)
- `2026-03-30_90_phase6_cleanup_patient_data_rekey.sql`

This script rekeys `patient_data` primary key to `seq`, preserves business key uniqueness, and drops legacy redundant index.
Do not run before the gates listed in AGENTS.md are explicitly validated.

## Design reference
- `2026-03-30_phase2_schema_migration_design.md`
  - Compatibility matrix
  - Rollback notes
  - Validation checklist
