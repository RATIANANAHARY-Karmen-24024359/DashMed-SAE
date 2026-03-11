# DashMed Charts & History Pipeline (Technical Notes)

This document explains how DashMed serves and renders **time-series medical data** efficiently, while keeping the UI responsive.

---

## 1) Endpoints and their purpose

### `GET /api_history_tail` (exact tail)
- Returns the latest **N points** for a `(patient_id, parameter_id)`.
- Designed for:
  - card sparklines (fast load)
  - short live windows in modals (e.g. 2 minutes)
- **No downsampling**.

### `GET /api_history` (full history / charting)
- Returns a parameter history formatted as:
  - `{ time_iso, value, flag }[]`
- For very large histories, it may downsample to a fixed budget (e.g. 5000 points) to keep payloads small.
- Also supports raw streaming export (`raw=1`) and CSV export (`format=csv`).

### `GET /api_history_chunk` + `GET /api_history_meta` (robust sync)
- Optional chunk-based sync API.
- Uses a monotonic cursor (`seq`) when available.

---

## 2) Why you may see “missing seconds” in modals

If the modal loads **the full history** via `/api_history` and the backend down-samples the dataset, the returned points are **representative**, not exhaustive.

When you zoom into a short time window (e.g. 2 minutes), downsampling can make it look like “some seconds are missing”, even though the database contains 1 point per second.

### Fix applied in the frontend
For short *live* windows (no `targetDate` + duration is small), the modal now prefers:
- `/api_history_tail` (exact tail)

This ensures:
- perfect sync between cards and modals
- no downsampling artifacts on short windows

---

## 3) Server-side strategy (summary)

1. **Count** rows for `(patient_id, parameter_id)`
2. If small enough → load raw history
3. If too large → stream history and downsample to a fixed budget
4. Return JSON

This keeps:
- memory usage controlled
- response sizes predictable

---

## 4) Client-side strategy (summary)

- Cards fetch tail histories in parallel (limited concurrency) and render sparklines.
- Modals fetch:
  - tail for short live windows
  - full/downsampled history for long windows or dated requests

---

## 5) Code references

- Backend endpoints:
  - `app/controllers/PatientController.php`
- DB access:
  - `app/models/repositories/MonitorRepository.php`
- Downsampling:
  - `app/services/DownsamplingService.php`
- Cards:
  - `public/assets/js/component/charts/sparkline-loader.js`
- Modal charts:
  - `public/assets/js/component/modal/chart.js`
