# Schema/Data Compatibility Matrix (Phase 0)

Context date: 2026-03-27
Scope: future DB refactor path defined in `AGENTS.md`.
Rule: additive-first only until parity checks and rollback rehearsal pass.

| Step | Migration action | Expected input shape | Expected output shape | API contract compatibility | Rollback path (no data loss) | Gate status |
|---|---|---|---|---|---|---|
| C0 | Baseline (current schema) | `patient_data` immutable history, PK `(id_patient, parameter_id, timestamp)`, unique `seq` | No change | 100% current contracts | N/A | Completed |
| C1 | Add `patient_data_latest` table (new object only) | Existing `patient_data` history authoritative | New snapshot table keyed by `(id_patient, parameter_id)` with `seq,timestamp,value,alert_flag,updated_at` | Backward-compatible (read paths unchanged) | `DROP TABLE patient_data_latest` (only if not used yet) | Planned additive |
| C2 | Add supporting indexes for snapshot reads/writes | C1 present | Indexes on snapshot and hot read predicates | Backward-compatible | Drop only newly-added indexes | Planned additive |
| C3 | Backfill snapshot from historical table | C1/C2 present | `patient_data_latest` fully populated with latest row per `(patient,parameter)` | Backward-compatible; no endpoint payload changes | Truncate/drop snapshot table and keep historical reads | Planned additive |
| C4 | Dual-write insert path (history + snapshot upsert) | Current insert path writes `patient_data` only | New inserts maintain history and snapshot atomically | Backward-compatible payloads; faster latest reads | Disable dual-write feature flag / code path; history remains source of truth | Planned additive |
| C5 | Switch latest/alerts reads to snapshot-first with fallback | C4 active, snapshot parity validated | Read load shifted from historical scans to snapshot lookups | Backward-compatible payload contracts | Toggle back to historical read path | Planned additive |
| C6 | Optional add mapping table for numeric parameter surrogate (phase-2) | String `parameter_id` in hot table | Coexisting mapping (`parameter_id` <-> `parameter_key`) | Backward-compatible if both IDs resolved | Disable mapped-read path, keep string-based reads | Planned additive |
| C7 | Destructive cleanup candidates (drop redundant indexes / re-key PK to `seq`) | All prior parity gates green, rollback rehearsed | Lean final schema | Requires full parity proof before execution | Restore from logical+physical artifacts and rollback migration scripts | **Blocked (NO-GO until gates pass)** |

## Non-negotiable invariants
- No rewrite of historical points.
- No lossy aggregation in clinician-facing paths.
- Ordering/reconciliation remains `seq`-first.
- Old and new structures must coexist until verified parity.
