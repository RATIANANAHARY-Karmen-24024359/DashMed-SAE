# DashMed Data Generator (Python)

This folder contains a Python generator used to continuously insert **time-series patient measurements** into MariaDB/MySQL.

The generator supports:
- CSV ingestion (bulk insert)
- Continuous synthetic generation (demo mode)
- Referential integrity checks (patients, parameters, users)
- Batch inserts with `executemany()` for performance

---

## Requirements

- Python 3.10+
- Packages:

```bash
pip install aiomysql python-dotenv
```

---

## Configuration

The script reads DB configuration from environment variables:

```env
DB_HOST=db
DB_USER=dashmed
DB_PASS=secret
DB_NAME=dashmed_data
```

In Docker Compose, these variables are provided automatically to the `generator` container.

---

## How it runs (Docker)

The Docker service `generator` executes `run_generator.sh`.

- If the configured CSV does not exist, the script runs in **infinite generation mode**.
- The insertion pace is defined by `INSERT_DELAY_SECONDS` (default: `1.0` second).

> Note: `run_generator.sh` contains a 10-second sleep, but in infinite mode `main.py` does not exit, so the loop does not iterate.

---

## CSV mode

If `patient_data.csv` exists (see `CSV_FILE` in `main.py`), the generator will:
1. Load the CSV
2. Group it by "cycle" (simulated simultaneous measurements)
3. Insert each cycle at the configured pace

### Expected CSV columns

```csv
id_patient,parameter_id,value,timestamp,alert_flag,created_by,archived
1,FC,75.5,2025-12-01 00:00:00,0,1,0
```

Notes:
- The `timestamp` column is ignored and replaced by the current server time.
- Rows that fail referential validation are skipped (and counted).

---

## Synthetic generation mode

If no CSV is found, the generator:
- loads valid patients, parameters and users from the DB
- generates smooth values per patient/parameter with optional alert/monitoring modes
- continuously inserts new samples

---

## Troubleshooting

- If you observe "holes" in the UI, first check whether the database actually missed seconds.
  A quick sanity check is to count rows over the last N seconds for a parameter.
- UI charts may also show downsampling artifacts when requesting very large histories; prefer tail endpoints for short windows.
