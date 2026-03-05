(function () {
    let source = new EventSource('/api_stream');

    source.onmessage = function (event) {
        try {
            const metrics = JSON.parse(event.data);

            // Dispatch a custom event on the window object with the metrics data
            const customEvent = new CustomEvent('DashMedMetricsUpdate', {
                detail: metrics
            });
            window.dispatchEvent(customEvent);

        } catch (e) {
            console.error('DashMed SSE Global fetch error:', e);
        }
    };

    source.onerror = function () {
        console.warn("DashMed Global SSE connection lost. Reconnecting...");
        // EventSource automatically attempts to reconnect, so we mostly just log it.
    };
})();
