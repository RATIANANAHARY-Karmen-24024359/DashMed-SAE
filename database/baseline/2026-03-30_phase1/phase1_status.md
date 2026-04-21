# Phase 1 Status (2026-03-30)

## Scope
Baseline capture on real dataset, no behavior change and no destructive DB actions.

## Completed evidence
- Endpoint timing raw samples: `endpoint_times.tsv`
- Endpoint p50/p95/max aggregates: `endpoint_latency_stats.tsv`
- Stream first-event samples: `api_stream_status.tsv`
- Browser first-30s metrics:
  - `browser_30s_metrics.json`
  - `browser_30s_summary.tsv`
- Hot SQL plans:
  - `explain_latest_metrics.json`
  - `explain_latest_history_specific.json`
  - `explain_history_meta.json`
  - `explain_stream_since_timestamp.json`
  - `explain_alerts_correlated.json`
- Runtime snapshots:
  - `show_full_processlist.tsv`
  - `show_full_processlist_under_alerts.tsv`
  - `show_full_processlist_under_stream.tsv`
  - `innodb_status.txt`

## Baseline highlights
- dashboard p95: 11.212s
- monitoring p95: 5.703s
- api-alerts p95: 5.517s
- api-stream first-event p95: 9.312s
- api-history-tail p95: 7.22ms
- dashboard_30s browser: 151 requests, 855.77 KB transferred, 30 long tasks > 50ms
- monitoring_30s browser: 130 requests, 102.23 KB transferred, 30 long tasks > 50ms

## Remaining gap in Phase 1
- None. Phase 1 baseline package is complete.

## Risks
- DB hotspot ranking is clear and still shows high-cost paths (`latest metrics`, `alerts`, `stream loop`).
- Browser baseline confirms high request fan-out and long-task pressure in first 30s.

## Next action proposed
Move to Phase 2: finalize additive migration design package (`schema target + staged migration + rollback notes`) before any structural rollout.
