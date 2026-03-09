/**
 * explorer.js
 * 
 * Logic for the Data Explorer and CSV Viewer.
 * Handles CSV uploads, parameter loading, and interactive ECharts visualization.
 */

(function () {
    let chart = null;
    let currentData = []; // [[timestamp, value], ...]
    let indicatorsMetadata = {}; // { parameter_id: { thresholds: {nmin, nmax, cmin, cmax}, unit, display_name } }
    let currentParamId = null;

    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        setupEventListeners();
        loadPatientParameters();
    });

    function initChart() {
        const container = document.getElementById('explorer-chart');
        if (!container) return;

        // Clear placeholder if any
        container.innerHTML = '';

        chart = echarts.init(container);
        window.addEventListener('resize', () => chart && chart.resize());
    }

    function setupEventListeners() {
        const dropZone = document.getElementById('csv-drop-zone');
        const fileInput = document.getElementById('csv-file-input');
        const paramSelector = document.getElementById('param-selector');
        const angleSelector = document.getElementById('analysis-angle');
        const exportBtn = document.getElementById('export-segment-btn');

        if (dropZone && fileInput) {
            dropZone.onclick = () => fileInput.click();
            fileInput.onchange = (e) => handleFiles(e.target.files);

            dropZone.ondragover = (e) => { e.preventDefault(); dropZone.style.background = 'rgba(39, 90, 254, 0.1)'; };
            dropZone.ondragleave = () => { dropZone.style.background = ''; };
            dropZone.ondrop = (e) => {
                e.preventDefault();
                dropZone.style.background = '';
                handleFiles(e.dataTransfer.files);
            };
        }

        if (paramSelector) {
            paramSelector.onchange = () => {
                const val = paramSelector.value;
                if (val) {
                    currentParamId = val;
                    loadParameterData(val);
                }
            };
        }

        if (angleSelector) {
            angleSelector.onchange = () => {
                if (currentData.length) renderChart();
            };
        }

        if (exportBtn) {
            exportBtn.onclick = () => exportSegment();
        }
    }

    async function loadPatientParameters() {
        const patientId = document.getElementById('context-patient-id')?.value;
        const selector = document.getElementById('param-selector');
        if (!patientId || !selector) return;

        try {
            selector.innerHTML = '<option value="">-- Chargement... --</option>';
            
            const res = await fetch(`${window.location.origin}/api_live_metrics?patient_id=${patientId}`);
            const metrics = await res.json();
            
            if (metrics.error) throw new Error(metrics.error);

            selector.innerHTML = '<option value="">-- Charger depuis le patient --</option>';
            
            // Store metadata (thresholds, unit)
            indicatorsMetadata = {};
            metrics.forEach(m => {
                indicatorsMetadata[m.parameter_id] = {
                    thresholds: m.thresholds,
                    unit: m.unit,
                    display_name: m.display_name
                };
            });

            // Sort metrics by display name
            metrics.sort((a, b) => a.display_name.localeCompare(b.display_name));

            metrics.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.parameter_id;
                opt.textContent = m.display_name + (m.unit ? ` (${m.unit})` : '');
                selector.appendChild(opt);
            });
        } catch (e) {
            console.error("Failed to load parameters", e);
            selector.innerHTML = '<option value="">-- Erreur de chargement --</option>';
        }
    }

    async function loadParameterData(paramId) {
        const patientId = document.getElementById('context-patient-id')?.value;
        if (!patientId) return;

        try {
            chart.showLoading();
            const res = await fetch(`${window.location.origin}/api_history?patient_id=${patientId}&param=${paramId}&raw=1`);
            const data = await res.json();
            chart.hideLoading();

            if (data.error) throw new Error(data.error);

            currentData = data.map(item => [
                new Date(item.time_iso).getTime(),
                item.value === null ? null : parseFloat(item.value)
            ]);

            renderChart();
            updateStats();
        } catch (e) {
            chart.hideLoading();
            alert("Erreur lors du chargement des données patient.");
            console.error(e);
        }
    }

    function handleFiles(files) {
        if (!files.length) return;
        const file = files[0];
        
        currentParamId = null; // Reset current param metadata for imported files
        
        const dropText = document.getElementById('drop-text');
        if (dropText) dropText.textContent = file.name;

        const reader = new FileReader();
        reader.onload = (e) => {
            const text = e.target.result;
            parseCSV(text);
        };
        reader.readAsText(file);
    }

    function parseCSV(text) {
        const lines = text.split(/\r?\n/);
        const data = [];
        
        // Detect delimiter: , or ;
        const firstLine = lines[0] || "";
        const delimiter = firstLine.includes(';') ? ';' : ',';

        // Skip header
        for (let i = 1; i < lines.length; i++) {
            const cols = lines[i].split(delimiter);
            if (cols.length < 2) continue;

            const rawTs = cols[0].trim();
            const rawVal = cols[1].trim();

            if (!rawTs) continue;

            // Handle ISO, Unix, or common formats
            let ts = new Date(rawTs).getTime();
            if (isNaN(ts) && /^\d+$/.test(rawTs)) ts = parseInt(rawTs) * 1000; // Assume Unix if numeric

            const val = rawVal === '' ? null : parseFloat(rawVal.replace(',', '.'));

            if (!isNaN(ts) && (val === null || !isNaN(val))) {
                data.push([ts, val]);
            }
        }

        if (data.length === 0) {
            alert("Aucune donnée valide trouvée dans le CSV. Format attendu : timestamp,valeur");
            return;
        }

        currentData = data.sort((a, b) => a[0] - b[0]);
        renderChart();
        updateStats();
    }

    function renderChart() {
        if (!chart) return;
        
        const angle = document.getElementById('analysis-angle').value;
        let displayData = [...currentData];

        if (angle === 'ma-5') displayData = movingAverage(currentData, 5);
        if (angle === 'ma-20') displayData = movingAverage(currentData, 20);

        // Thresholds logic
        const meta = currentParamId ? indicatorsMetadata[currentParamId] : null;
        let visualMap = null;
        let markAreas = [];
        let markLines = [];

        if (meta && meta.thresholds) {
            const { nmin, nmax, cmin, cmax } = meta.thresholds;
            
            // Visual Map for coloring the dynamic line
            visualMap = {
                show: false,
                dimension: 1,
                pieces: [
                    { gt: nmax || 999999, color: '#ef4444' }, // Critical high
                    { gt: nmin || -999999, lte: nmax || 999999, color: '#22c55e' }, // Normal
                    { lte: nmin || -999999, color: '#ef4444' } // Critical low
                ]
            };

            // Add background zones for better visibility
            if (nmin !== null && nmax !== null) {
                markAreas.push({
                    itemStyle: { color: 'rgba(34, 197, 94, 0.05)' },
                    data: [[{ yAxis: nmin }, { yAxis: nmax }]]
                });
            }

            // Reference lines
            if (nmin !== null) markLines.push({ yAxis: nmin, lineStyle: { color: '#fbbf24', type: 'dashed' }, label: { position: 'end', formatter: 'Min Normal' } });
            if (nmax !== null) markLines.push({ yAxis: nmax, lineStyle: { color: '#fbbf24', type: 'dashed' }, label: { position: 'end', formatter: 'Max Normal' } });
        }

        const option = {
            animation: false,
            tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
            grid: { left: '3%', right: '4%', bottom: '10%', containLabel: true },
            xAxis: { type: 'time', splitLine: { show: false } },
            yAxis: { type: 'value', scale: true, splitLine: { lineStyle: { type: 'dashed' } } },
            visualMap: visualMap,
            dataZoom: [
                { type: 'inside', start: 0, end: 100 },
                { type: 'slider', start: 0, end: 100 }
            ],
            series: [{
                name: meta ? meta.display_name : 'Valeur',
                type: 'line',
                smooth: angle.startsWith('ma'),
                symbol: 'none',
                connectNulls: true,
                data: displayData,
                lineStyle: { width: 3 },
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(39, 90, 254, 0.1)' },
                        { offset: 1, color: 'rgba(39, 90, 254, 0)' }
                    ])
                },
                markArea: { data: markAreas[0] ? markAreas[0].data : [] },
                markLine: {
                    silent: true,
                    data: markLines,
                    label: { show: true }
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
        chart.off('datazoom');
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
        if (!currentData.length || !chart) return;

        const opt = chart.getOption();
        if (!opt || !opt.dataZoom) return;

        const dz = opt.dataZoom[0];
        
        const allTs = currentData.map(d => d[0]);
        const minTs = Math.min(...allTs);
        const maxTs = Math.max(...allTs);
        const range = maxTs - minTs;

        const startTs = minTs + (range * dz.start / 100);
        const endTs = minTs + (range * dz.end / 100);

        const visible = currentData.filter(d => d[0] >= startTs && d[0] <= endTs && d[1] !== null);
        if (!visible.length) {
            document.getElementById('stat-count').textContent = '0';
            document.getElementById('stat-avg').textContent = '-';
            document.getElementById('stat-max').textContent = '-';
            document.getElementById('stat-min').textContent = '-';
            return;
        }

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
        if (!currentData.length || !chart) return;

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
