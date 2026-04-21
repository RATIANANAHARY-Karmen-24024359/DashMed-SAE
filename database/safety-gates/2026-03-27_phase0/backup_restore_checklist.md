# Phase 0 Backup/Restore Checklist

Date: 2026-03-27
Status: Completed

## 1) Logical backup (schema+data+triggers+routines+events)
- [x] Dump executed from DB container with `--single-transaction --routines --triggers --events`.
- [x] Artifact produced: `artifacts/dashmed_data_full_20260327_012347.sql.gz`.

## 2) Physical snapshot of MariaDB volume
- [x] DB stopped before snapshot to avoid on-disk inconsistency.
- [x] Artifact produced: `artifacts/db_data_volume_20260327_012347.tar`.

## 3) Source manifest capture (for parity)
- [x] DB identity captured.
- [x] Exact per-table row counts captured.
- [x] Core `patient_data` invariants captured (`min/max seq`, total rows, distinct parameters).
- [x] Sampled window checksums captured.
- [x] Patient-8 top-parameter tail checksums captured.

## 4) Restore rehearsal (isolated DB)
- [x] Isolated MariaDB container created (`dashmed-restore-20260327_012347`).
- [x] Full isolated restore completed from physical snapshot artifact.
- [x] Restore manifests captured.
- [x] Source vs restore parity report generated (`parity_report.md`).
- [x] Prior storage blocker (`Errcode: 28`) resolved via Docker cleanup and retry.

## 5) Signed artifacts
- [x] SHA256 checksums generated for all backup artifacts (`manifests/artifacts_sha256.txt`).

## 6) Explicit go/no-go before any migration
- [x] Current decision for schema migration/index rebuild/destructive actions: **NO-GO**.
- [x] Allowed next scope: **GO for Phase 1 baseline/profiling only** (non-destructive).
