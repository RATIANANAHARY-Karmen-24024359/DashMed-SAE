/**
 * explorer.js
 * 
 * Logic for the Data Explorer and CSV Viewer.
 * Handles CSV uploads, parameter loading, and interactive ECharts visualization.
 */

(function () {
    let chart = null;
    let currentData = []; // [[timestamp, value], ...]

    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        setupEventListeners();
        loadPatientParameters();
    });

    function initChart() {
        const container = document.getElementById('explorer-chart');
        if (!container) return;
        chart = echarts.init(container);
        window.addEventListener('resize', () => chart && chart.resize());
    }

    function setupEventListeners() {
        const dropZone = document.getElementById('csv-drop-zone');
        const fileInput = document.getElementById('csv-file-input');
        const paramSelector = document.getElementById('param-selector');
        const angleSelector = document.getElementById('analysis-angle');
        const exportBtn = document.getElementById('export-segment-btn');

        // CSV Upload
        dropZone.onclick = () => fileInput.click();
        fileInput.onchange = (e) => handleFiles(e.target.files);

        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.style.background = 'rgba(39, 90, 254, 0.1)'; };
        dropZone.ondragleave = () => { dropZone.style.background = ''; };
        dropZone.ondrop = (e) => {
            e.preventDefault();
            dropZone.style.background = '';
            handleFiles(e.dataTransfer.files);
        };

        // Parameter Selector
        paramSelector.onchange = () => {
            const val = paramSelector.value;
            if (val) loadParameterData(val);
        };

        // Analysis Angle
        angleSelector.onchange = () => {
            if (currentData.length) renderChart();
        };

        // Export Segment
        exportBtn.onclick = () => exportSegment();
    }

    async function loadPatientParameters() {
        const patientId = document.getElementById('context-patient-id')?.value;
        const selector = document.getElementById('param-selector');
        if (!patientId || !selector) return;

        try {
            // Reusing existing API to get slugs/parameters
            // For now we'll assume we know some common ones or fetch from a hypothetical endpoint
            // In a real scenario, we might have a dedicated endpoint for this.
            const params = [
                { id: '1', name: 'Fréquence Cardiaque (FC)', slug: 'frequence-cardiaque' },
                { id: '2', name: 'SpO2', slug: 'spo2' },
                { id: '3', name: 'Température', slug: 'temperature' },
                { id: '4', name: 'Pression Artérielle (Systolique)', slug: 'pression-arterielle-systolique' },
                { id: '5', name: 'Glycémie', slug: 'glycemie' }
            ];

            params.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                selector.appendChild(opt);
            });
        } catch (e) {
            console.error("Failed to load parameters", e);
        }
    }

    async function loadParameterData(paramId) {
        try {
            const res = await fetch(`${window.location.origin}/api_history?param=${paramId}&raw=1`);
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            currentData = data.map(item => [
                new Date(item.time_iso).getTime(),
                item.value === null ? null : parseFloat(item.value)
            ]);

            renderChart();
            updateStats();
        } catch (e) {
            alert("Erreur lors du chargement des données.");
            console.error(e);
        }
    }

    function handleFiles(files) {
        if (!files.length) return;
        const file = files[0];
        if (file.type !== "text/csv" && !file.name.endsWith(".csv")) {
            alert("Veuillez sélectionner un fichier CSV.");
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const text = e.target.result;
            parseCSV(text);
        };
        reader.readAsText(file);
    }

    function parseCSV(text) {
        const lines = text.split('\n');
        const data = [];

        // Simple CSV parser (assuming timestamp, value, flag)
        // Skip header
        for (let i = 1; i < lines.length; i++) {
            const cols = lines[i].split(',');
            if (cols.length < 2) continue;

            const ts = new Date(cols[0].trim()).getTime();
            const val = cols[1].trim() === '' ? null : parseFloat(cols[1]);

            if (!isNaN(ts)) {
                data.push([ts, val]);
            }
        }

        currentData = data.sort((a, b) => a[0] - b[0]);
        renderChart();
        updateStats();
    }

    function renderChart() {
        const angle = document.getElementById('analysis-angle').value;
        let displayData = [...currentData];

        if (angle === 'ma-5') displayData = movingAverage(currentData, 5);
        if (angle === 'ma-20') displayData = movingAverage(currentData, 20);

        const option = {
            animation: false,
            tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
            grid: { left: '3%', right: '4%', bottom: '10%', containLabel: true },
            xAxis: { type: 'time', splitLine: { show: false } },
            yAxis: { type: 'value', scale: true, splitLine: { lineStyle: { type: 'dashed' } } },
            dataZoom: [
                { type: 'inside', start: 0, end: 100 },
                { type: 'slider', start: 0, end: 100 }
            ],
            series: [{
                name: 'Valeur',
                type: 'line',
                smooth: angle.startsWith('ma'),
                symbol: 'none',
                connectNulls: true,
                data: displayData,
                lineStyle: { color: '#275afe', width: 2 },
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(39, 90, 254, 0.2)' },
                        { offset: 1, color: 'rgba(39, 90, 254, 0)' }
                    ])
                }
            }]
        };

        if (angle === 'peaks') {
            const peaks = detectPeaks(currentData);
            option.series[0].markPoint = {
                data: peaks.map(p => ({ coord: p, value: p[1].toFixed(2), symbolSize: 40, itemStyle: { color: '#ef4444' } }))
            };
        }

        chart.setOption(option, true);

        // Listen for zoom to update stats
        chart.on('datazoom', () => updateStats());
    }

    function movingAverage(data, period) {
        return data.map((val, index) => {
            if (index < period - 1) return [val[0], val[1]];
            let sum = 0;
            let count = 0;
            for (let i = 0; i < period; i++) {
                if (data[index - i][1] !== null) {
                    sum += data[index - i][1];
                    count++;
                }
            }
            return [val[0], count > 0 ? sum / count : null];
        });
    }

    function detectPeaks(data) {
        const peaks = [];
        for (let i = 1; i < data.length - 1; i++) {
            const prev = data[i - 1][1];
            const curr = data[i][1];
            const next = data[i + 1][1];
            if (curr !== null && prev !== null && next !== null && curr > prev && curr > next) {
                // Heuristic for "significant" peak
                if (curr > 0) peaks.push(data[i]);
            }
        }
        return peaks.slice(0, 50); // Limit to top 50
    }

    function updateStats() {
        if (!currentData.length) return;

        // Get visible range from chart
        const opt = chart.getOption();
        const dz = opt.dataZoom[0];
        let startTs, endTs;

        const allTs = currentData.map(d => d[0]);
        const minTs = Math.min(...allTs);
        const maxTs = Math.max(...allTs);
        const range = maxTs - minTs;

        startTs = minTs + (range * dz.start / 100);
        endTs = minTs + (range * dz.end / 100);

        const visible = currentData.filter(d => d[0] >= startTs && d[0] <= endTs && d[1] !== null);
        if (!visible.length) return;

        const values = visible.map(d => d[1]);
        const count = values.length;
        const sum = values.reduce((a, b) => a + b, 0);
        const avg = sum / count;
        const max = Math.max(...values);
        const min = Math.min(...values);

        document.getElementById('stat-count').textContent = count;
        document.getElementById('stat-avg').textContent = avg.toFixed(2);
        document.getElementById('stat-max').textContent = max.toFixed(2);
        document.getElementById('stat-min').textContent = min.toFixed(2);
    }

    function exportSegment() {
        if (!currentData.length) return;

        // Similar to interactive export but for visible segment
        const opt = chart.getOption();
        const dz = opt.dataZoom[0];
        const allTs = currentData.map(d => d[0]);
        const minTs = Math.min(...allTs);
        const maxTs = Math.max(...allTs);
        const range = maxTs - minTs;
        const startTs = minTs + (range * dz.start / 100);
        const endTs = minTs + (range * dz.end / 100);

        const visible = currentData.filter(d => d[0] >= startTs && d[0] <= endTs);

        let csv = "timestamp,value\n";
        visible.forEach(d => {
            csv += `${new Date(d[0]).toISOString()},${d[1] === null ? '' : d[1]}\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `segment_export_${new Date().getTime()}.csv`;
        a.click();
    }

})();
