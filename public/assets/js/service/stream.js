(function () {
    const patientIdEl = document.getElementById('context-patient-id');
    const patientId = patientIdEl ? patientIdEl.value : '';
    const streamUrl = patientId ? `/api_stream?id_patient=${patientId}` : '/api_stream';

    /** @type {EventSource|null} */
    let source = null;
    let lastMessageAt = Date.now();

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

        source.onmessage = function (event) {
            lastMessageAt = Date.now();
            try {
                const metrics = JSON.parse(event.data);
                const customEvent = new CustomEvent('DashMedMetricsUpdate', { detail: metrics });
                window.dispatchEvent(customEvent);
            } catch (e) {
                console.error('DashMed SSE Global fetch error:', e);
            }
        };

        source.onerror = function () {
            // EventSource will retry automatically, but Safari background throttling can get it stuck.
            console.warn('DashMed Global SSE connection lost. Reconnecting...');
        };
    }

    // Public hook so pages can force a reconnect after tab suspension.
    window.DashMedStream = {
        reconnect: () => connect(),
        close: () => closeSource(),
        getLastMessageAt: () => lastMessageAt,
    };

    connect();

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
})();
