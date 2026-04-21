# Browser reconnect watch context (2026-04-01)

- Validation mode: authenticated browser watch (headless Chromium/Playwright) on `dashboard` and `monitoring`.
- Session bootstrap: signup with letters-only names (DB constraint `ck_users_last_name_format` rejects digits in `last_name`).
- Test account (non-admin): `phasebrowser.1775029609063525000@dashmed.local`.
- Patient context used: room/patient `8`.
- Capture outputs:
  - `stream_reconnects_browser_10m.json`
  - `stream_reconnects_browser_10m_summary.tsv`
- Notes:
  - account is test-only and does not alter clinical time-series data,
  - this capture validates browser-side reconnect behavior and live card updates,
  - cookie file used for the run was temporary (`/tmp/phase5_signup_cookie_ok.txt`).
