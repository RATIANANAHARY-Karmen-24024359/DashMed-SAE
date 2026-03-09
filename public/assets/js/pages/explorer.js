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
    let currentTheme = 'dark';
    let currentChartType = 'line';

    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        setupEventListeners();
        loadPatientParameters();
    });

    function initChart(theme = 'dark') {
        const container = document.getElementById('explorer-chart');
        if (!container) return;

        if (chart) {
            chart.dispose();
        }

        chart = echarts.init(container, theme);
        window.addEventListener('resize', () => chart && chart.resize());

        if (currentData.length) {
            renderChart();
        }
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

        const typeSelector = document.getElementById('chart-type-selector');
        if (typeSelector) {
            typeSelector.onchange = () => {
                currentChartType = typeSelector.value;
                if (currentData.length) renderChart();
            };
        }

        const themeSelector = document.getElementById('theme-selector');
        if (themeSelector) {
            themeSelector.onchange = () => {
                currentTheme = themeSelector.value;
                initChart(currentTheme);
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

        if (currentChartType === 'heatmap') {
            renderHeatmap();
            return;
        }

        // Capture current zoom before re-rendering
        let savedZoom = [];
        if (chart) {
            const currentOpt = chart.getOption();
            if (currentOpt && currentOpt.dataZoom) {
                // We only care about the values (start, end, startValue, endValue)
                savedZoom = currentOpt.dataZoom.map(dz => ({
                    type: dz.type,
                    start: dz.start,
                    end: dz.end,
                    orient: dz.orient
                }));
            }
        }

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

        let seriesType = 'line';
        let smooth = false;
        let step = false;
        let areaStyle = null;

        if (currentChartType === 'smooth-line') {
            seriesType = 'line';
            smooth = true;
        } else if (currentChartType === 'step-line') {
            seriesType = 'line';
            step = 'end';
        } else if (currentChartType === 'area') {
            seriesType = 'line';
            areaStyle = { opacity: 0.3 };
        } else if (currentChartType === 'smooth-area') {
            seriesType = 'line';
            smooth = true;
            areaStyle = { opacity: 0.3 };
        } else if (currentChartType === 'bar') {
            seriesType = 'bar';
        } else if (currentChartType === 'scatter') {
            seriesType = 'scatter';
        } else if (currentChartType === 'effectScatter') {
            seriesType = 'effectScatter';
        }

        const option = {
            animation: currentChartType === 'effectScatter',
            tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
            grid: { left: '3%', right: '4%', bottom: '10%', containLabel: true },
            xAxis: { type: 'time', splitLine: { show: false } },
            yAxis: { type: 'value', scale: true, splitLine: { lineStyle: { type: 'dashed' } } },
            visualMap: visualMap,
            dataZoom: [
                { type: 'inside', start: 0, end: 100, orient: 'horizontal' },
                { type: 'inside', orient: 'vertical' }, // 2D Zoom
                { type: 'slider', start: 0, end: 100 }
            ],
            series: [{
                name: meta ? meta.display_name : 'Valeur',
                type: seriesType,
                smooth: smooth,
                step: step,
                symbol: seriesType === 'line' ? 'none' : 'circle',
                symbolSize: seriesType === 'scatter' ? 8 : (seriesType === 'effectScatter' ? 12 : 4),
                connectNulls: true,
                data: displayData,
                lineStyle: { width: 3 },
                areaStyle: areaStyle,
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

        // Apply saved zoom if available
        if (savedZoom.length > 0) {
            option.dataZoom = savedZoom;
        }

        chart.setOption(option, true);

        // Listen for zoom to update stats
        chart.off('datazoom');
        chart.on('datazoom', () => updateStats());
    }

    function renderHeatmap() {
        const heatmapData = transformToHeatmapData(currentData);
        const days = heatmapData.days;
        const hours = Array.from({ length: 24 }, (_, i) => i + "h");
        const data = heatmapData.data;

        const option = {
            tooltip: { position: 'top' },
            grid: { height: '80%', top: '10%' },
            xAxis: { type: 'category', data: hours, splitArea: { show: true } },
            yAxis: { type: 'category', data: days, splitArea: { show: true } },
            visualMap: {
                min: heatmapData.min,
                max: heatmapData.max,
                calculable: true,
                orient: 'horizontal',
                left: 'center',
                bottom: '5%',
                inRange: { color: ['#313695', '#4575b4', '#74add1', '#abd9e9', '#e0f3f8', '#ffffbf', '#fee090', '#fdae61', '#f46d43', '#d73027', '#a50026'] }
            },
            series: [{
                name: 'Intensité',
                type: 'heatmap',
                data: data,
                label: { show: false },
                emphasis: { itemStyle: { shadowBlur: 10, shadowColor: 'rgba(0, 0, 0, 0.5)' } }
            }]
        };

        chart.setOption(option, true);
    }

    function transformToHeatmapData(data) {
        if (!data.length) return { days: [], data: [], min: 0, max: 0 };

        const map = new Map(); // "YYYY-MM-DD" -> { hour: sum, count }
        let minVal = Infinity, maxVal = -Infinity;

        data.forEach(([ts, val]) => {
            if (val === null) return;
            const d = new Date(ts);
            const dateStr = d.toISOString().split('T')[0];
            const hour = d.getHours();

            if (!map.has(dateStr)) map.set(dateStr, Array.from({ length: 24 }, () => ({ sum: 0, count: 0 })));

            const dayData = map.get(dateStr);
            dayData[hour].sum += val;
            dayData[hour].count++;
        });

        const dayList = Array.from(map.keySet()).sort();
        const heatmapData = [];

        dayList.forEach((day, dayIndex) => {
            const dayData = map.get(day);
            dayData.forEach((h, hourIndex) => {
                if (h.count > 0) {
                    const avg = h.sum / h.count;
                    heatmapData.push([hourIndex, dayIndex, parseFloat(avg.toFixed(2))]);
                    if (avg < minVal) minVal = avg;
                    if (avg > maxVal) maxVal = avg;
                }
            });
        });

        return {
            days: dayList,
            data: heatmapData,
            min: minVal === Infinity ? 0 : minVal,
            max: maxVal === -Infinity ? 100 : maxVal
        };
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
