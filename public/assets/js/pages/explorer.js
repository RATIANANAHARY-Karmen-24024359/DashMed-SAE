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
        const themeSelector = document.getElementById('theme-selector');
        if (themeSelector) {
            currentTheme = themeSelector.value;
            // Ensure page theme matches selector on load
            if (currentTheme === 'light' || currentTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', currentTheme);
            }
        }
        initChart(currentTheme);
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
        const resetZoomBtn = document.getElementById('reset-zoom-btn');
        const fullScreenBtn = document.getElementById('full-screen-btn');

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
                const candleGroup = document.getElementById('candlestick-granularity-group');
                if (candleGroup) candleGroup.style.display = (currentChartType === 'candlestick') ? 'block' : 'none';
                if (currentData.length) renderChart();
            };
        }

        const candleGranularity = document.getElementById('candlestick-granularity');
        if (candleGranularity) {
            candleGranularity.onchange = () => {
                if (currentData.length) renderCandlestick();
            };
        }

        const themeSelector = document.getElementById('theme-selector');
        if (themeSelector) {
            themeSelector.onchange = () => {
                currentTheme = themeSelector.value;

                // Update page theme if it's light or dark
                if (currentTheme === 'light' || currentTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', currentTheme);
                } else {
                    // For other themes (vintage, etc.), we might want to default to light or dark page base
                    // or keep the current one. Let's default to dark for "vintage" etc. as they are often dark-ish
                    // or just leave it as is if it's already one of them.
                    // For now, let's just ensure we have a valid data-theme for the base UI
                }

                initChart(currentTheme);
            };
        }

        if (paramSelector) {
            paramSelector.onchange = () => {
                const val = paramSelector.value;
                if (val) {
                    currentParamId = val;
                    loadParameterData(val);
                    const patientName = document.getElementById('context-patient-name')?.value;
                    updateContextDisplay(false, patientName);
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

        if (resetZoomBtn) {
            resetZoomBtn.onclick = () => resetZoom();
        }

        if (fullScreenBtn) {
            fullScreenBtn.onclick = () => toggleFullScreen();
        }
    }

    function resetZoom() {
        if (!chart) return;
        chart.dispatchAction({
            type: 'dataZoom',
            start: 0,
            end: 100
        });
        updateStats();
    }

    function toggleFullScreen() {
        const container = document.getElementById('chart-viewer-container');
        if (!container) return;

        const isEntering = !container.classList.contains('full-screen');
        container.classList.toggle('full-screen');
        document.body.classList.toggle('chart-full-screen');

        // Re-init chart or resize with multiple checks to ensure responsiveness
        const resizeChart = () => {
            if (chart) chart.resize();
        };

        // Resize immediately and after transition
        resizeChart();
        setTimeout(resizeChart, 100);
        setTimeout(resizeChart, 300);
        setTimeout(resizeChart, 500); // Matches transition duration

        const btn = document.getElementById('full-screen-btn');
        if (btn) {
            btn.innerHTML = isEntering
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v5H3"></path><path d="M21 8h-5V3"></path><path d="M3 16h5v5"></path><path d="M16 21v-5h5"></path></svg> Quitter'
                : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 3 6 6"></path><path d="m9 21-6-6"></path><path d="M21 3v6h-6"></path><path d="M3 21v-6h6"></path><path d="m21 3-9 9"></path><path d="m3 21 9-9"></path></svg> Plein écran';
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

        updateContextDisplay(true, file.name);

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
        if (angle === 'median-5') displayData = medianFilter(currentData, 5);
        if (angle === 'z-score') displayData = zScoreFilter(currentData);
        if (angle === 'derivative') displayData = derivativeFilter(currentData);
        if (angle === 'savgol') displayData = savitzkyGolayFilter(currentData);

        if (currentChartType === 'histogram') {
            renderHistogram();
            return;
        }

        if (currentChartType === 'boxplot') {
            renderBoxplot();
            return;
        }

        if (currentChartType === 'candlestick') {
            renderCandlestick();
            return;
        }

        if (currentChartType === 'density') {
            renderDensityChart();
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
                    { gt: nmax || 999999, color: '#ff4d4f' }, // Critical high (Vibrant red)
                    { gt: nmin || -999999, lte: nmax || 999999, color: '#00e676' }, // Normal (Vibrant green)
                    { lte: nmin || -999999, color: '#ff4d4f' } // Critical low
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
            if (nmin !== null) markLines.push({ yAxis: nmin, lineStyle: { color: 'rgba(255, 193, 7, 0.4)', type: 'dashed' }, label: { position: 'end', formatter: 'Min', color: '#ffca28', fontSize: 10 } });
            if (nmax !== null) markLines.push({ yAxis: nmax, lineStyle: { color: 'rgba(255, 193, 7, 0.4)', type: 'dashed' }, label: { position: 'end', formatter: 'Max', color: '#ffca28', fontSize: 10 } });
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
            areaStyle = {
                opacity: 0.2,
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: 'rgba(39, 90, 254, 0.4)' },
                    { offset: 1, color: 'rgba(39, 90, 254, 0)' }
                ])
            };
        } else if (currentChartType === 'smooth-area') {
            seriesType = 'line';
            smooth = true;
            areaStyle = {
                opacity: 0.2,
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: 'rgba(39, 90, 254, 0.4)' },
                    { offset: 1, color: 'rgba(39, 90, 254, 0)' }
                ])
            };
        } else if (currentChartType === 'bar') {
            seriesType = 'bar';
        } else if (currentChartType === 'scatter') {
            seriesType = 'scatter';
        } else if (currentChartType === 'effectScatter') {
            seriesType = 'effectScatter';
        }

        const option = {
            animation: localStorage.getItem('dashmed_chart_animation') !== 'false' && currentChartType === 'effectScatter',
            tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
            grid: { left: '3%', right: '4%', bottom: '10%', containLabel: true },
            xAxis: { type: 'time', splitLine: { show: false } },
            yAxis: { type: 'value', scale: true, splitLine: { lineStyle: { type: 'dashed' } } },
            visualMap: visualMap,
            dataZoom: [
                { type: 'inside', start: 0, end: 100, orient: 'horizontal', zoomOnMouseWheel: true },
                { type: 'slider', start: 0, end: 100, height: 25, bottom: 10, borderColor: 'transparent', backgroundColor: 'rgba(0,0,0,0.1)', fillerColor: 'rgba(39, 90, 254, 0.15)', handleSize: '100%' }
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
                lineStyle: { width: 3, cap: 'round' },
                areaStyle: areaStyle,
                markArea: {
                    itemStyle: { color: 'rgba(0, 230, 118, 0.03)' },
                    data: markAreas[0] ? markAreas[0].data : []
                },
                markLine: {
                    silent: true,
                    symbol: ['none', 'none'],
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

    function renderHistogram() {
        const values = currentData.map(d => d[1]).filter(v => v !== null);
        if (!values.length) return;

        const min = Math.min(...values);
        const max = Math.max(...values);
        const binCount = 30;
        const binWidth = (max - min) / binCount;
        const bins = Array.from({ length: binCount }, (_, i) => ({
            min: min + i * binWidth,
            max: min + (i + 1) * binWidth,
            count: 0
        }));

        values.forEach(v => {
            let idx = Math.floor((v - min) / binWidth);
            if (idx >= binCount) idx = binCount - 1;
            if (idx < 0) idx = 0;
            bins[idx].count++;
        });

        const option = {
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
            grid: { left: '3%', right: '4%', bottom: '15%', containLabel: true },
            xAxis: { type: 'category', data: bins.map(b => b.min.toFixed(2)), name: 'Valeur' },
            yAxis: { type: 'value', name: 'Fréquence' },
            dataZoom: [
                { type: 'inside', start: 0, end: 100 },
                { type: 'slider', start: 0, end: 100, bottom: 10 }
            ],
            series: [{
                name: 'Fréquence',
                type: 'bar',
                barWidth: '95%',
                data: bins.map(b => b.count),
                itemStyle: { color: '#275afe' }
            }]
        };
        chart.setOption(option, true);
    }

    function renderBoxplot() {
        const values = currentData.map(d => d[1]).filter(v => v !== null);
        if (!values.length) return;

        const sorted = [...values].sort((a, b) => a - b);
        const q1 = sorted[Math.floor(sorted.length * 0.25)];
        const median = sorted[Math.floor(sorted.length * 0.5)];
        const q3 = sorted[Math.floor(sorted.length * 0.75)];
        const min = sorted[0];
        const max = sorted[sorted.length - 1];

        const option = {
            tooltip: { trigger: 'item', axisPointer: { type: 'shadow' } },
            grid: { left: '3%', right: '4%', bottom: '15%', containLabel: true },
            xAxis: { type: 'category', data: ['Distribution'], splitArea: { show: true } },
            yAxis: { type: 'value', splitLine: { show: true } },
            dataZoom: [
                { type: 'inside', start: 0, end: 100 },
                { type: 'slider', start: 0, end: 100, bottom: 10 }
            ],
            series: [{
                name: 'Boîte à moustaches',
                type: 'boxplot',
                data: [[min, q1, median, q3, max]],
                tooltip: {
                    formatter: (param) => [
                        'Max: ' + param.data[5],
                        'Q3: ' + param.data[4],
                        'Median: ' + param.data[3],
                        'Q1: ' + param.data[2],
                        'Min: ' + param.data[1]
                    ].join('<br/>')
                }
            }]
        };
        chart.setOption(option, true);
    }

    function renderCandlestick() {
        if (!currentData.length) return;

        // Group data by time buckets (e.g., 24 buckets across the whole range)
        const times = currentData.map(d => d[0]);
        const minT = Math.min(...times);
        const maxT = Math.max(...times);
        const span = maxT - minT;

        let bucketWidth;
        const granularityVal = document.getElementById('candlestick-granularity')?.value || 'auto';

        if (granularityVal === 'auto') {
            bucketWidth = span / 24;
        } else {
            // value is in minutes, convert to ms
            bucketWidth = parseInt(granularityVal) * 60 * 1000;
        }

        const bucketCount = Math.ceil(span / bucketWidth) + 1;
        const buckets = Array.from({ length: bucketCount }, () => []);

        currentData.forEach(([ts, val]) => {
            if (val === null) return;
            let idx = Math.floor((ts - minT) / bucketWidth);
            if (idx >= bucketCount) idx = bucketCount - 1;
            buckets[idx].push(val);
        });

        const candleData = [];
        const xAxisData = [];

        buckets.forEach((vals, i) => {
            if (vals.length === 0) return;
            const open = vals[0];
            const close = vals[vals.length - 1];
            const low = Math.min(...vals);
            const high = Math.max(...vals);
            candleData.push([open, close, low, high]);
            xAxisData.push(new Date(minT + i * bucketWidth).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
        });

        const option = {
            tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
            grid: { left: '3%', right: '4%', bottom: '15%', containLabel: true },
            xAxis: { type: 'category', data: xAxisData, boundaryGap: true, axisTick: { alignWithLabel: true } },
            yAxis: { type: 'value', scale: true },
            dataZoom: [
                { type: 'inside', start: 0, end: 100 },
                { type: 'slider', start: 0, end: 100, bottom: 10 }
            ],
            series: [{
                type: 'candlestick',
                data: candleData,
                itemStyle: {
                    color: '#275afe',
                    color0: '#ef4444',
                    borderColor: '#275afe',
                    borderColor0: '#ef4444'
                }
            }]
        };
        chart.setOption(option, true);
    }

    function renderDensityChart() {
        const values = currentData.map(d => d[1]).filter(v => v !== null);
        if (!values.length) return;

        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = max - min;
        const step = range / 100;

        // Simple Histogram + Smoothing for Density estimation
        const binCount = 50;
        const binWidth = range / binCount;
        const bins = Array.from({ length: binCount }, () => 0);

        values.forEach(v => {
            let idx = Math.floor((v - min) / binWidth);
            if (idx >= binCount) idx = binCount - 1;
            bins[idx]++;
        });

        // Convert to percentage density
        const total = values.length;
        const densityData = bins.map((count, i) => [
            min + i * binWidth + binWidth / 2,
            (count / total) / binWidth
        ]);

        const option = {
            tooltip: { trigger: 'axis' },
            grid: { left: '3%', right: '4%', bottom: '15%', containLabel: true },
            xAxis: { type: 'value', name: 'Valeur', scale: true },
            yAxis: { type: 'value', name: 'Densité' },
            dataZoom: [
                { type: 'inside', start: 0, end: 100 },
                { type: 'slider', start: 0, end: 100, bottom: 10 }
            ],
            series: [{
                name: 'Densité',
                type: 'line',
                smooth: true,
                symbol: 'none',
                areaStyle: { opacity: 0.3, color: '#275afe' },
                lineStyle: { color: '#275afe', width: 3 },
                data: densityData
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

    function medianFilter(data, period) {
        return data.map((val, index) => {
            if (index < period - 1) return [val[0], val[1]];
            const window = [];
            for (let i = 0; i < period; i++) {
                if (data[index - i][1] !== null) window.push(data[index - i][1]);
            }
            if (window.length === 0) return [val[0], null];
            window.sort((a, b) => a - b);
            const median = window[Math.floor(window.length / 2)];
            return [val[0], median];
        });
    }

    function zScoreFilter(data) {
        const values = data.map(d => d[1]).filter(v => v !== null);
        if (!values.length) return data;
        const avg = values.reduce((a, b) => a + b, 0) / values.length;
        const std = Math.sqrt(values.reduce((a, b) => a + Math.pow(b - avg, 2), 0) / values.length);
        if (std === 0) return data.map(d => [d[0], 0]);
        return data.map(d => [d[0], d[1] === null ? null : (d[1] - avg) / std]);
    }

    function derivativeFilter(data) {
        return data.map((d, i) => {
            if (i === 0 || d[1] === null || data[i - 1][1] === null) return [d[0], 0];
            const dt = (d[0] - data[i - 1][0]) / 1000; // in seconds
            if (dt === 0) return [d[0], 0];
            return [d[0], (d[1] - data[i - 1][1]) / dt];
        });
    }

    function savitzkyGolayFilter(data) {
        // Simple 5-point SG filter coefficients for smoothing
        const coeffs = [-3, 12, 17, 12, -3];
        const norm = 35;
        return data.map((d, i) => {
            if (i < 2 || i > data.length - 3) return [d[0], d[1]];
            let sum = 0;
            for (let j = -2; j <= 2; j++) {
                if (data[i + j][1] === null) return [d[0], d[1]];
                sum += data[i + j][1] * coeffs[j + 2];
            }
            return [d[0], sum / norm];
        });
    }

    function detectPeaks(data) {
        if (!data.length) return [];

        const values = data.map(d => d[1]);
        const n = values.length;
        if (n < 3) return [];

        // 1. Smoothing (Moving Average to reduce noise)
        const smoothed = movingAverage(data, 3).map(d => d[1]);

        // 2. Statistics for thresholding
        const validValues = smoothed.filter(v => v !== null);
        const avg = validValues.reduce((a, b) => a + b, 0) / validValues.length;
        const std = Math.sqrt(validValues.reduce((a, b) => a + Math.pow(b - avg, 2), 0) / validValues.length);
        const threshold = avg + 0.5 * std; // Dynamic threshold

        const peaks = [];

        // 3. Find local maxima and calculate prominence
        for (let i = 1; i < n - 1; i++) {
            const curr = smoothed[i];
            const prev = smoothed[i - 1];
            const next = smoothed[i + 1];

            if (curr !== null && prev !== null && next !== null && curr > prev && curr > next) {
                // Potential peak
                if (curr > threshold) {
                    // Calculate prominence (simple version: height above neighboring valleys)
                    let leftValley = curr;
                    for (let j = i - 1; j >= 0 && smoothed[j] !== null; j--) {
                        if (smoothed[j] < leftValley) leftValley = smoothed[j];
                        else if (smoothed[j] > curr) break;
                    }

                    let rightValley = curr;
                    for (let j = i + 1; j < n && smoothed[j] !== null; j++) {
                        if (smoothed[j] < rightValley) rightValley = smoothed[j];
                        else if (smoothed[j] > curr) break;
                    }

                    const prominence = curr - Math.max(leftValley, rightValley);

                    // Significant peak if prominence > some heuristic (e.g., 10% of value)
                    if (prominence > curr * 0.05) {
                        peaks.push({
                            data: data[i],
                            prominence: prominence
                        });
                    }
                }
            }
        }

        // 4. Sort by prominence and limit
        return peaks
            .sort((a, b) => b.prominence - a.prominence)
            .slice(0, 30)
            .map(p => p.data);
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

    async function updateContextDisplay(isImported, name) {
        const container = document.getElementById('explorer-context-display');
        const nameEl = document.getElementById('current-context-name');
        if (!container || !nameEl) return;

        container.style.display = name ? 'block' : 'none';

        if (isImported) {
            // Try to extract patient ID from filename (e.g., export_patient_8_...)
            const match = name.match(/export_patient_(\d+)_/);
            let displayValue = `<span style="display:block; opacity:0.6; font-size:0.75rem; font-weight:400;">Fichier importé</span><span style="display:block; margin-bottom:6px; color:var(--text-main); font-size:0.85rem;">${name}</span>`;

            if (match && match[1]) {
                const patientId = match[1];
                try {
                    const res = await fetch(`${window.location.origin}/api_patient_name?id=${patientId}`);
                    const data = await res.json();
                    if (data && !data.error) {
                        displayValue += `<span style="display:block; padding-top:6px; border-top:1px solid rgba(255,255,255,0.08); font-weight:400; opacity:0.8; font-size:0.8rem;">Patient détecté : <strong style="color:var(--explorer-accent); font-weight:600;">${data.first_name} ${data.last_name}</strong></span>`;
                    }
                } catch (e) {
                    console.error("Failed to fetch patient name", e);
                }
            }

            nameEl.innerHTML = displayValue;
        } else {
            nameEl.innerHTML = `<span style="display:block; opacity:0.6; font-size:0.75rem; font-weight:400;">Patient sélectionné</span><span style="display:block; font-weight:600; color:var(--explorer-accent); font-size:1rem;">${name}</span>`;
        }
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

        const patientId = document.getElementById('context-patient-id')?.value || 'unknown';
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `export_patient_${patientId}_segment_${timestamp}.csv`;
        a.click();
    }

})();
