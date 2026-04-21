# Phase 5 status (2026-04-01)

## 1) Actions réalisées
- Replaced unstable cache-busting (`?v=time()`) with stable asset version token (`APP_ASSET_VERSION`) on key views/partials:
  - `app/views/patient/DashboardView.php`
  - `app/views/patient/MonitoringView.php`
  - `app/views/patient/ExplorerView.php`
  - `app/views/partials/_global-alerts.php`
- Reduced frontend request-path fan-out:
  - `public/assets/js/component/charts/sparkline-loader.js` now avoids duplicate tail fetch when DOM/cache seed already exists.
  - `public/assets/js/service/history-sync.js` now syncs visible cards first and limits initial scope.
  - `public/assets/js/alerts-global.js` now pauses polling work while tab is hidden.
- Reduced dashboard main-thread loop pressure:
  - urgent loop throttled and guarded in `app/views/patient/DashboardView.php`.
- Reduced initial embedded history payload:
  - `app/controllers/PatientController.php` prefetch from `250` to `120` points per parameter.
- Removed unused Chart.js/moment/hammer payload on dashboard/monitoring pages:
  - `app/views/patient/DashboardView.php`
  - `app/views/patient/MonitoringView.php`
- Stream first-event responsiveness hardening:
  - immediate SSE bootstrap event added in `PatientController::apiStream`.
- Realtime regression fix (same phase):
  - fixed fatal in `PatientController::apiStream` bootstrap path:
    - replaced invalid `Indicator::toViewData()` call with
      `MonitoringService::prepareViewData($indicator)`.
  - endpoint now emits valid SSE payload shape after bootstrap/update.
- Realtime frontend resilience hardening:
  - `public/assets/js/service/stream.js` now:
    - handles SSE error payloads explicitly (`{error: ...}`),
    - redirects to login on `Non autorisé` instead of silently freezing,
    - runs a watchdog (12s stale threshold, 5s check cadence) to force reconnect on stalled stream.
- Authenticated stability run (API-level, 10 minutes):
  - created a dedicated non-admin test account for validation context,
  - executed 60 successive authenticated `/api_stream?id_patient=8` probes over ~10 minutes,
  - captured per-sample status/body evidence and summary artifacts.
- Authenticated stability run (API-level, 60 minutes):
  - executed 240 successive authenticated `/api_stream?id_patient=8` probes over ~60 minutes,
  - kept the same per-sample evidence format for comparability with 10-minute run.
- Authenticated browser watch (UI-level, 10 minutes total):
  - executed a headless browser watch with a valid authenticated session across `dashboard` and `monitoring` (~5 minutes each),
  - captured SSE request/reconnect counts directly from browser network events,
  - captured live-update evidence from in-page metric updates (`DashMedMetricsUpdate` + card value changes).

## 2) Mesures / preuves
- Endpoint timings (authenticated, room/patient 8):
  - `database/baseline/2026-03-30_phase5/endpoint_times.tsv`
  - `database/baseline/2026-03-30_phase5/endpoint_latency_stats.tsv`
- Browser 30s instrumentation:
  - `database/baseline/2026-03-30_phase5/browser_30s_metrics.json`
  - `database/baseline/2026-03-30_phase5/browser_30s_summary.tsv`
- Incident evidence (`/api_stream` 500 -> 200):
  - `database/baseline/2026-03-30_phase5/api_stream_incident_2026-03-30.tsv`
  - source command: `docker compose logs --since=3h web | rg "GET /api_stream"`
- Frontend syntax validation:
  - `node --check public/assets/js/service/stream.js` (OK)
- Authenticated stability evidence (10 minutes):
  - validation context: `database/baseline/2026-03-30_phase5/realtime_validation_context.md`
  - per-sample probe table: `database/baseline/2026-03-30_phase5/realtime_stream_stability_10m.tsv`
  - aggregate summary: `database/baseline/2026-03-30_phase5/realtime_stream_stability_10m_summary.tsv`
  - `/api_stream` logs window (20m): `database/baseline/2026-03-30_phase5/realtime_stream_logs_20m.txt`
  - `/api_stream` status counts (20m): `database/baseline/2026-03-30_phase5/realtime_stream_logs_20m_summary.tsv`
- Authenticated stability evidence (60 minutes):
  - per-sample probe table: `database/baseline/2026-03-30_phase5/realtime_stream_stability_60m.tsv`
  - aggregate summary: `database/baseline/2026-03-30_phase5/realtime_stream_stability_60m_summary.tsv`
  - log corroboration window: `database/baseline/2026-03-30_phase5/realtime_stream_logs_window.txt`
  - log status counts: `database/baseline/2026-03-30_phase5/realtime_stream_logs_window_summary.tsv`
- Browser reconnect evidence (authenticated, 10 minutes):
  - validation context: `database/baseline/2026-03-30_phase5/browser_reconnect_watch_context.md`
  - detailed capture: `database/baseline/2026-03-30_phase5/stream_reconnects_browser_10m.json`
  - summary table: `database/baseline/2026-03-30_phase5/stream_reconnects_browser_10m_summary.tsv`
- Latest measured summary:
  - `dashboard_30s`: `66` requests, `1310.28 KB`, `3` long tasks (`797ms` total).
  - `monitoring_30s`: `56` requests, `45.51 KB`, `3` long tasks (`285ms` total).
  - Endpoint p95: `dashboard=0.161875s`, `monitoring=0.039707s`, `api_stream_first_event=0.042284s`.
- Authenticated stream stability (10m): `60/60` probes HTTP `200`, `0` probe with `Non autorisé`, `0` probe with `Erreur interne`, `0` HTTP `5xx`.
- Authenticated stream stability (60m): `240/240` probes HTTP `200`, `0` probe with `Non autorisé`, `0` probe with `Erreur interne`, `0` HTTP `5xx`.
- `/api_stream` log corroboration on validation window: observed statuses `200=229`, `302=0`, `500=0`.
- Browser reconnect watch (10m total):
  - dashboard: `stream_requests=1`, `reconnect_count=0`, `reconnects_per_hour=0.000`, `value_changes=279`, `dashMedEvents=283`.
  - monitoring: `stream_requests=1`, `reconnect_count=0`, `reconnects_per_hour=0.000`, `value_changes=289`, `dashMedEvents=291`.
  - aggregate: `0` reconnect in `601641ms` observed (`0.000 reconnect/hour` extrapolated), no SSE warning/reconnect logs captured in browser console.

## 3) Risques restants
- Dashboard transfer-size metric remains above Phase 1 baseline due heavy media and chart payload composition.
- Browser capture methodology differs from initial Phase 1 tooling details (cross-origin/CDN byte accounting sensitivity), so byte-level comparison must be interpreted carefully.
- Phase 3 isolated restored-dataset parity rerun remains blocked until Phase 0 physical artifacts are available locally.
- Browser reconnect proof currently covers `10` minutes total (`~5` minutes per screen); the acceptance target for long-session UI stability still expects a `60+` minute browser scenario.

## 4) Prochaine action proposée
- Continue Phase 5 on payload strategy with explicit product validation:
  - decide whether notification audio payload policy should be optimized (lazy/preload/format) for dashboard transfer target,
  - then re-measure 30s browser payload with the same capture setup.
- Immediate short follow-up:
  - extend browser watch to `60+` minutes (single continuous authenticated session) to align with acceptance stability criterion.
  - then finalize Phase 5 closure decision and hand over to Phase 7 correctness package.
