/**
 * DashMed History Sync Engine ("perfect" version)
 *
 * Goals:
 * - Exact sync (chunked) without hurting UX
 * - Robust resume via IndexedDB sync_state
 * - Global backpressure + dynamic chunk sizing
 * - Pauses when tab hidden / user interacting
 *
 * Requires:
 * - window.DashMedHistoryCache (history-cache.js)
 * - Backend endpoints: api_history_meta, api_history_chunk (with seq)
 */
(function () {
  const MAX_PARALLEL_TOTAL = 2;
  const META_REFRESH_MS = 60_000;
  const INITIAL_LIMIT = 2000;
  const LIMIT_MIN = 500;
  const LIMIT_MAX = 20000;

  const BACKOFF_BASE_MS = 250;
  const BACKOFF_MAX_MS = 10_000;

  // Time budgets: do not hog the main thread/DB.
  const WRITE_BUDGET_MS = 25;

  function jitter(ms) {
    const j = ms * 0.2;
    return ms + (Math.random() * 2 - 1) * j;
  }

  function now() { return Date.now(); }

  function getPatientId() {
    const el = document.getElementById('context-patient-id');
    if (!el) return null;
    const n = Number(el.value);
    return Number.isFinite(n) && n > 0 ? n : null;
  }

  function getParamsToSync() {
    // Only parameters present on screen.
    return Array.from(new Set(
      Array.from(document.querySelectorAll('article.card[data-slug]'))
        .map(c => c.dataset.slug)
        .filter(Boolean)
    ));
  }

  async function fetchJson(url) {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) {
      const err = new Error(`HTTP ${res.status}`);
      err.status = res.status;
      throw err;
    }
    return res.json();
  }

  async function fetchMeta(patientId, param) {
    const url = `${location.origin}/api_history_meta?patient_id=${encodeURIComponent(patientId)}&param=${encodeURIComponent(param)}`;
    return fetchJson(url);
  }

  async function fetchChunk(patientId, param, afterSeq, limit) {
    const qs = new URLSearchParams({
      patient_id: String(patientId),
      param,
      limit: String(limit),
    });
    if (afterSeq && afterSeq > 0) qs.set('after_seq', String(afterSeq));
    const url = `${location.origin}/api_history_chunk?${qs.toString()}`;
    return fetchJson(url);
  }

  function shouldPause() {
    return document.hidden;
  }

  class SyncEngine {
    constructor() {
      this.inFlight = 0;
      this.stopped = false;
      this.limit = INITIAL_LIMIT;
      this.backoffMs = 0;
      this.lastUserActivityAt = 0;

      // Simple user activity detection to avoid background thrash during interaction.
      const mark = () => { this.lastUserActivityAt = now(); };
      window.addEventListener('pointerdown', mark, { passive: true });
      window.addEventListener('wheel', mark, { passive: true });
      window.addEventListener('keydown', mark, { passive: true });
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) this.kick();
      });
    }

    async kick() {
      if (this.stopped) return;
      if (!window.DashMedHistoryCache) return;

      const patientId = getPatientId();
      if (!patientId) return;

      const params = getParamsToSync();
      for (const param of params) {
        this.scheduleParam(patientId, param);
      }
    }

    async scheduleParam(patientId, param) {
      if (this.stopped) return;

      // Avoid tight loops: param-level lock via state
      const state = await window.DashMedHistoryCache.getState(patientId, param);
      const lockedUntil = state?.lockedUntil || 0;
      if (lockedUntil && lockedUntil > now()) return;

      // Ensure meta watermark exists and is fresh
      const metaAt = state?.metaAt || 0;
      if (!state?.meta || (now() - metaAt) > META_REFRESH_MS) {
        await this.refreshMeta(patientId, param);
      }

      // Run one chunk step
      await this.step(patientId, param);

      // Reschedule if not up to date
      const st2 = await window.DashMedHistoryCache.getState(patientId, param);
      const upToDate = this.isUpToDate(st2);
      if (!upToDate) {
        // continue progressively, but not aggressively
        setTimeout(() => this.scheduleParam(patientId, param), jitter(this.backoffMs || 150));
      }
    }

    isUpToDate(state) {
      const meta = state?.meta;
      if (!meta) return false;
      const maxSeq = meta.max_seq;
      if (!maxSeq) return false;
      const cursor = state?.cursorSeq || 0;
      return cursor >= maxSeq;
    }

    async refreshMeta(patientId, param) {
      // lock briefly to prevent parallel meta storms
      await window.DashMedHistoryCache.putState(patientId, param, { lockedUntil: now() + 500 });

      try {
        const meta = await fetchMeta(patientId, param);
        await window.DashMedHistoryCache.putState(patientId, param, {
          meta,
          metaAt: now(),
          lastError: null,
        });
      } catch (e) {
        await this.applyBackoff(patientId, param, e);
      }
    }

    async step(patientId, param) {
      if (this.stopped) return;
      if (shouldPause()) return;

      // If user is interacting, slow down.
      const interactive = (now() - this.lastUserActivityAt) < 1500;
      if (interactive) {
        this.backoffMs = Math.max(this.backoffMs, 500);
      }

      if (this.inFlight >= MAX_PARALLEL_TOTAL) return;

      const state = await window.DashMedHistoryCache.getState(patientId, param);
      const meta = state?.meta;
      if (!meta || !meta.max_seq) return;

      const cursorSeq = state?.cursorSeq || 0;
      if (cursorSeq >= meta.max_seq) return;

      // Acquire param lock
      await window.DashMedHistoryCache.putState(patientId, param, { lockedUntil: now() + 2000 });

      this.inFlight++;
      const started = now();

      try {
        const data = await fetchChunk(patientId, param, cursorSeq, this.limit);
        const points = Array.isArray(data?.points) ? data.points : [];

        // Write to cache in a bounded way.
        const writeStart = now();
        await window.DashMedHistoryCache.put(patientId, param, points, { maxPoints: 200_000 });
        const writeMs = now() - writeStart;

        // Advance cursor using next_after_seq if present.
        const nextSeq = (typeof data?.next_after_seq === 'number') ? data.next_after_seq : null;
        const newCursor = nextSeq && nextSeq > cursorSeq ? nextSeq : cursorSeq;

        await window.DashMedHistoryCache.putState(patientId, param, {
          cursorSeq: newCursor,
          lockedUntil: 0,
          lastChunkAt: now(),
          lastLatencyMs: now() - started,
          lastWriteMs: writeMs,
          lastError: null,
        });

        // AIMD throughput tuning
        const latency = now() - started;
        if (latency < 400 && writeMs < WRITE_BUDGET_MS) {
          this.limit = Math.min(LIMIT_MAX, this.limit + 500);
          this.backoffMs = Math.max(0, this.backoffMs - 100);
        } else if (latency > 900 || writeMs > WRITE_BUDGET_MS) {
          this.limit = Math.max(LIMIT_MIN, Math.floor(this.limit * 0.7));
          this.backoffMs = Math.min(BACKOFF_MAX_MS, Math.max(this.backoffMs, 500));
        }

      } catch (e) {
        await this.applyBackoff(patientId, param, e);
      } finally {
        this.inFlight--;
      }
    }

    async applyBackoff(patientId, param, err) {
      const status = err && err.status ? err.status : null;
      const mult = (status === 429 || (status && status >= 500)) ? 2 : 1.3;
      const base = this.backoffMs || BACKOFF_BASE_MS;
      this.backoffMs = Math.min(BACKOFF_MAX_MS, Math.floor(base * mult));
      this.limit = Math.max(LIMIT_MIN, Math.floor(this.limit * 0.5));

      await window.DashMedHistoryCache.putState(patientId, param, {
        lockedUntil: now() + jitter(this.backoffMs),
        lastError: String(err?.message || err),
        lastErrorAt: now(),
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!window.DashMedHistoryCache) return;
    const engine = new SyncEngine();
    window.__dashmedHistorySync = engine;

    // Start after initial paint
    setTimeout(() => engine.kick(), 500);

    // Periodic meta refresh for long sessions
    setInterval(() => engine.kick(), 30_000);
  });
})();
