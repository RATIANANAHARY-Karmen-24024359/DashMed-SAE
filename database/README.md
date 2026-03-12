# Database (DashMed-SAE)

This folder contains the SQL scripts used to **create** and **seed** the DashMed database.

## Files

- `dashmed_dev.sql` – schema (tables, constraints)
- `dashmed_inserts.sql` – reference data (parameters, chart types, etc.)
- `dashmed_consultations.sql` – demo consultations
- `dashmed_patient_data.sql` – demo time-series data (`patient_data`)

## Docker initialization

When using Docker Compose, the database container loads the SQL scripts in order via:

- `docker-compose.yml` → `db.volumes` → `/docker-entrypoint-initdb.d/*.sql`

If you need to re-run seeds:

```bash
docker compose down -v
docker compose up -d --build
```

## Migrations

Optional migrations live in `database/migrations/`.
