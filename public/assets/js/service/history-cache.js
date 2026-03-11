/**
 * DashMedHistoryCache (IndexedDB)
 *
 * Senior-friendly goals:
 * - Exact point storage (no approximation)
 * - Idempotent merges (dedupe by time_iso)
 * - Bounded storage (optional maxPoints)
 *
 * Safari supports IndexedDB.
 */
(function () {
  const DB_NAME = 'dashmed_history';
  const DB_VERSION = 1;
  const STORE = 'series';
  const STATE_STORE = 'sync_state';

  function openDb() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = () => {
        const db = req.result;
        if (!db.objectStoreNames.contains(STORE)) {
          db.createObjectStore(STORE, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(STATE_STORE)) {
          db.createObjectStore(STATE_STORE, { keyPath: 'key' });
        }
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  async function withStore(storeName, mode, fn) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, mode);
      const store = tx.objectStore(storeName);
      let res;
      tx.oncomplete = () => resolve(res);
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error);
      res = fn(store);
    });
  }

  function seriesKey(patientId, param) {
    return `${patientId}:${param}`;
  }

  function normalizePoints(points) {
    if (!Array.isArray(points)) return [];
    return points
      .filter(p => p && typeof p.time_iso === 'string' && p.time_iso)
      .map(p => ({
        time_iso: p.time_iso,
        value: (p.value === undefined ? null : p.value),
        flag: (p.flag === undefined ? '0' : String(p.flag)),
      }));
  }

  function mergePoints(existing, incoming, maxPoints) {
    const map = new Map();
    normalizePoints(existing).forEach(p => map.set(p.time_iso, p));
    normalizePoints(incoming).forEach(p => map.set(p.time_iso, p));

    const merged = Array.from(map.values());
    merged.sort((a, b) => new Date(a.time_iso).getTime() - new Date(b.time_iso).getTime());

    if (typeof maxPoints === 'number' && maxPoints > 0 && merged.length > maxPoints) {
      return merged.slice(merged.length - maxPoints);
    }
    return merged;
  }

  window.DashMedHistoryCache = {
    async get(patientId, param) {
      const key = seriesKey(patientId, param);
      return withStore(STORE, 'readonly', (store) => {
        return new Promise((resolve) => {
          const req = store.get(key);
          req.onsuccess = () => resolve(req.result ? req.result.points || [] : []);
          req.onerror = () => resolve([]);
        });
      });
    },

    async put(patientId, param, points, opts = {}) {
      const key = seriesKey(patientId, param);
      const maxPoints = opts.maxPoints;

      const existing = await this.get(patientId, param);
      const merged = mergePoints(existing, points, maxPoints);

      await withStore(STORE, 'readwrite', (store) => {
        store.put({
          key,
          patientId,
          param,
          points: merged,
          updatedAt: Date.now(),
        });
      });

      return merged;
    },

    async clear(patientId, param) {
      const key = seriesKey(patientId, param);
      await withStore(STORE, 'readwrite', (store) => store.delete(key));
    },

    // Sync state (cursor/backoff/meta watermark)
    async getState(patientId, param) {
      const key = seriesKey(patientId, param);
      return withStore(STATE_STORE, 'readonly', (store) => {
        return new Promise((resolve) => {
          const req = store.get(key);
          req.onsuccess = () => resolve(req.result || null);
          req.onerror = () => resolve(null);
        });
      });
    },

    async putState(patientId, param, patch) {
      const key = seriesKey(patientId, param);
      const existing = await this.getState(patientId, param);
      const next = Object.assign({}, existing || { key, patientId, param }, patch || {}, { updatedAt: Date.now() });
      await withStore(STATE_STORE, 'readwrite', (store) => {
        store.put(next);
      });
      return next;
    }
  };
})();
