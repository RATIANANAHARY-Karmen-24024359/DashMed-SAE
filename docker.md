# DashMed-SAE – Docker Guide

This project can run fully in Docker using three services:
- `web` (PHP/Apache)
- `db` (MariaDB)
- `generator` (Python time-series inserter)

---

## Requirements

- Docker Desktop (or Docker Engine)
- Docker Compose

---

## Start

From the repository root:

```bash
docker compose up -d --build
```

Open:
- Web UI: http://localhost:8000

---

## Stop

```bash
docker compose down
```

---

## Reset database (re-seed SQL)

```bash
docker compose down -v
docker compose up -d --build
```

---

## Logs

```bash
# all services
docker compose logs -f

# a single service
docker compose logs -f web
docker compose logs -f db
docker compose logs -f generator
```

---

## What the generator does

The `generator` container runs `database/main.py`.

- It inserts measurements continuously.
- The insertion rate is controlled by `INSERT_DELAY_SECONDS` (default 1 second).
- It can also ingest a CSV if present (see `database/README_PYTHON.md`).

---

## Environment

For local runs without Docker, create `.env` from `.env.example`.

```bash
cp .env.example .env
```

Do not commit `.env`.
