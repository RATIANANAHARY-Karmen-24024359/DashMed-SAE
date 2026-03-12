<div align="center">

  <h1>DashMed-SAE</h1>
  <p><strong>ICU Dashboard (MVC PHP + MariaDB) with a time-series data generator</strong></p>
  <p>University project (SAE) – demonstration environment for intensive care monitoring.</p>

  <p>
    <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" /></a>
    <a href="https://mariadb.org/"><img src="https://img.shields.io/badge/MariaDB-10.11-003545?style=for-the-badge&logo=mariadb&logoColor=white" alt="MariaDB" /></a>
    <a href="https://getcomposer.org"><img src="https://img.shields.io/badge/Composer-2.x-885630?style=for-the-badge&logo=composer&logoColor=white" alt="Composer" /></a>
    <a href="https://www.docker.com/"><img src="https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker" /></a>
  </p>
</div>

---

## Overview

DashMed-SAE is a lightweight ICU dashboard built with **pure PHP (MVC)** and **MariaDB**. It renders patient cards (sparklines) and detailed modal charts for time-series vitals.

Key goals:
- Fast UI (no frontend build step)
- Clear separation: controllers / repositories / services / views
- Efficient handling of large histories (streaming + downsampling)

---

## Quick start (recommended): Docker

### Requirements
- Docker Desktop (or Docker Engine) with Docker Compose

### Run
```bash
docker compose up -d --build
```

Open:
- Web: http://localhost:8000
- DB (optional): localhost:3306

### Stop
```bash
docker compose down
```

### Full reset (replay SQL seeds)
```bash
docker compose down -v
docker compose up -d --build
```

---

## Environment variables

This repository does **not** commit `.env`.

- Copy `.env.example` → `.env`
- Adjust values if needed.

```bash
cp .env.example .env
```

> Note: Docker Compose already injects DB variables into containers, so `.env` is mainly useful for running locally without Docker.

---

## Data model / seeds

SQL initialization scripts live in `database/` and are loaded by Docker in this order:
1. `database/dashmed_dev.sql` (schema)
2. `database/dashmed_inserts.sql` (reference tables)
3. `database/dashmed_consultations.sql` (consultation demo data)
4. `database/dashmed_patient_data.sql` (time-series demo data)

Optional migrations are in `database/migrations/`.

---

## Time-series endpoints (high level)

- **Cards (sparklines):** `GET /api_history_tail` (exact tail points)
- **Modal charts:**
  - for short live windows: `GET /api_history_tail` (exact, avoids downsampling artifacts)
  - for long histories / dated queries: `GET /api_history` (may downsample to keep payload small)

See `README_CHARTS.md` for the full rationale.

---

## Development (without Docker)

1) Install PHP dependencies
```bash
composer install
```

2) Serve
```bash
php -S 0.0.0.0:8000 -t public
```

3) Configure DB access via `.env`.

---

## Quality gates

Run the same checks used during refactors:

```bash
./vendor/bin/phpunit -c phpunit.xml
./vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
./vendor/bin/phpcs -q --standard=phpcs.xml app assets/includes public
```

---

## Security notes (project scope)

This is a teaching project, but the codebase includes basic hardening:
- secure session cookie flags (HttpOnly/SameSite, strict mode)
- CSRF required on auth POST actions
- session regeneration after login
- password reset link contains a token only (no reset code in the URL)

---

## Repository structure

- `public/` – front controller + static assets
- `app/controllers/` – MVC controllers (auth, patient, admin, API)
- `app/models/` – entities + repositories (PDO)
- `app/services/` – domain logic (monitoring, downsampling, layout)
- `database/` – schema, seeds, generator
- `tests/` – PHPUnit tests

---

## License

Educational project (SAE). If you need a license statement for the deliverable, add it explicitly (e.g. `proprietary`).
