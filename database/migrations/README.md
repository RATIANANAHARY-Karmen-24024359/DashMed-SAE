# Database migrations

This folder contains **optional** SQL migrations.

## 2026-03-10_add_seq_cursor.sql
Adds a monotonic `seq` cursor to `patient_data`.

### Why
- Enables **robust, resumable chunk pagination** for time-series sync.
- Prevents missing/duplicating points when multiple samples share the same timestamp.

### Notes
- On very large tables (100M+ rows), the ALTER can be **slow** and may lock the table.
- In development, the simplest path is often to recreate the volume and re-seed the database from `database/*.sql`.
