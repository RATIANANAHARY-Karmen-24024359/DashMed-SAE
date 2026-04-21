# Realtime validation context (2026-03-30)

- Validation mode: authenticated session created via `/?page=signup` then SSE probe on `/?api_stream`.
- Test account (non-admin): `realtime.bot.1774902100@dashmed.local`.
- Patient context used for probes: `id_patient=8`.
- Notes:
  - account is test-only and does not alter clinical time-series data,
  - this context file is for reproducibility of the stability artifacts generated in this folder.
