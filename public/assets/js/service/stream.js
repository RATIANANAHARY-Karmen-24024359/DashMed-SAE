(function () {
    const patientIdEl = document.getElementById('context-patient-id');
    const patientId = patientIdEl ? patientIdEl.value : '';
    const streamUrl = patientId ? `/api_stream?id_patient=${patientId}` : '/api_stream';
    const STALE_TIMEOUT_MS = 12000;
    const WATCHDOG_INTERVAL_MS = 5000;

    /** @type {EventSource|null} */
    let source = null;
    let lastMessageAt = Date.now();
    let watchdogId = null;
    let isRedirectingToLogin = false;

    function handleStreamPayload(metrics) {
        if (Array.isArray(metrics)) {
            const customEvent = new CustomEvent('DashMedMetricsUpdate', { detail: metrics });
            window.dispatchEvent(customEvent);
            return;
        }

        if (!metrics || typeof metrics !== 'object' || !metrics.error) {
            return;
        }

        const errorText = String(metrics.error || '');
        if (/non autoris/i.test(errorText)) {
            console.warn('DashMed SSE unauthorized. Redirecting to login.');
            closeSource();
            if (!isRedirectingToLogin) {
                isRedirectingToLogin = true;
                window.location.href = '/?page=login';
            }
            return;
        }

        console.warn('DashMed SSE server error payload:', errorText);
    }

    function closeSource() {
        if (source) {
            try {
                source.close();
            } catch (_) {
                // ignore
            }
            source = null;
        }
    }

    function connect() {
        closeSource();
        source = new EventSource(streamUrl);
        lastMessageAt = Date.now();

        source.onmessage = function (event) {
            lastMessageAt = Date.now();
            try {
                const metrics = JSON.parse(event.data);
                handleStreamPayload(metrics);
            } catch (e) {
                console.error('DashMed SSE Global fetch error:', e);
            }
        };

        source.onerror = function () {
            // EventSource will retry automatically, but Safari background throttling can get it stuck.
            console.warn('DashMed Global SSE connection lost. Reconnecting...');
        };
    }

    function startWatchdog() {
        if (watchdogId !== null) {
            clearInterval(watchdogId);
        }

        watchdogId = window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            if (!source) {
                connect();
                return;
            }

            const idleMs = Date.now() - lastMessageAt;
            if (idleMs > STALE_TIMEOUT_MS) {
                console.warn('DashMed SSE stale stream detected, forcing reconnect...');
                connect();
            }
        }, WATCHDOG_INTERVAL_MS);
    }

    // Public hook so pages can force a reconnect after tab suspension.
    window.DashMedStream = {
        reconnect: () => connect(),
        close: () => closeSource(),
        getLastMessageAt: () => lastMessageAt,
    };

    connect();
    startWatchdog();

    // Safari can aggressively throttle/suspend background tabs.
    // On visibility restore, force a clean reconnect.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            connect();
        } else {
            // Optional: free resources while hidden
            closeSource();
        }
    });

    window.addEventListener('beforeunload', () => {
        closeSource();
        if (watchdogId !== null) {
            clearInterval(watchdogId);
            watchdogId = null;
        }
    });
})();
