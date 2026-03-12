/**
 * Sparkline background loader
 *
 * Loads exact tail history quickly (index-friendly), caches in IndexedDB,
 * then renders the sparkline using the existing card-sparklines renderer.
 */
(function () {
  const MAX_PARALLEL = 4;
  const DEFAULT_LIMIT = 250;
  const CACHE_MAX_POINTS = 2000; // keep some depth, not infinite

  function getPatientId() {
    const el = document.getElementById('context-patient-id');
    if (!el) return null;
    const v = el.value;
    const n = Number(v);
    return Number.isFinite(n) && n > 0 ? n : null;
  }

  function getCards() {
    return Array.from(document.querySelectorAll('article.card[data-slug]'));
  }

  function setPointsInDom(card, points) {
    const ul = card.querySelector('ul[data-spark]');
    if (!ul) return;
    ul.innerHTML = '';
    for (const p of points) {
      const li = document.createElement('li');
      li.dataset.time = p.time_iso;
      li.dataset.value = (p.value === null ? '' : String(p.value));
      li.dataset.flag = (p.flag === undefined ? '0' : String(p.flag));
      ul.appendChild(li);
    }
  }

  async function fetchTail(patientId, param, limit) {
    const url = `${window.location.origin}/api_history_tail?patient_id=${encodeURIComponent(patientId)}&param=${encodeURIComponent(param)}&limit=${encodeURIComponent(limit)}`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error(`api_history_tail ${res.status}`);
    return res.json();
  }

  async function loadCard(card, patientId) {
    const param = card.dataset.paramId || card.dataset.slug;
    if (!param) return;

    // 1) render cached immediately if present
    if (window.DashMedHistoryCache) {
      try {
        const cached = await window.DashMedHistoryCache.get(patientId, param);
        if (Array.isArray(cached) && cached.length) {
          setPointsInDom(card, cached);
          if (window.renderSparkline) window.renderSparkline(card);
        }
      } catch (_) {}
    }

    // 2) fetch fresh tail
    const data = await fetchTail(patientId, param, DEFAULT_LIMIT);
    const points = (data && data.points) ? data.points : [];

    // 3) store in cache (merged)
    let merged = points;
    if (window.DashMedHistoryCache) {
      try {
        merged = await window.DashMedHistoryCache.put(patientId, param, points, { maxPoints: CACHE_MAX_POINTS });
      } catch (_) {}
    }

    // 4) render
    setPointsInDom(card, merged);
    if (window.renderSparkline) window.renderSparkline(card);
  }

  async function runQueue(tasks, parallel) {
    const q = tasks.slice();
    const workers = Array.from({ length: parallel }).map(async () => {
      while (q.length) {
        const t = q.shift();
        if (!t) return;
        try { await t(); } catch (e) { /* silent */ }
      }
    });
    await Promise.all(workers);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const patientId = getPatientId();
    if (!patientId) return;

    const cards = getCards();
    const tasks = cards.map(card => () => loadCard(card, patientId));
    await runQueue(tasks, MAX_PARALLEL);
  });

  // When SSE pushes new points via DashMedMetricsUpdate (already handled by card-sparklines.js),
  // we also persist them in cache.
  window.addEventListener('DashMedMetricsUpdate', async (event) => {
    const patientId = getPatientId();
    if (!patientId || !window.DashMedHistoryCache) return;

    const metrics = event.detail;
    if (!Array.isArray(metrics)) return;

    for (const m of metrics) {
      if (!m || !m.time_iso) continue;
      const param = m.parameter_id || m.slug;
      if (!param) continue;
      const p = { time_iso: m.time_iso, value: m.value ?? null, flag: m.is_crit_flag ? '1' : '0' };
      try {
        await window.DashMedHistoryCache.put(patientId, param, [p], { maxPoints: CACHE_MAX_POINTS });
      } catch (_) {}
    }
  });
})();
