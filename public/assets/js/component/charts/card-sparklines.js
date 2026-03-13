(function () {
    if (!window.echarts) return;

    const cards = document.querySelectorAll("article.card");
    if (!cards.length) return;

    const getCssVar = (name) => {
        let val = getComputedStyle(document.body || document.documentElement).getPropertyValue(name).trim();
        if (!val) return name;
        return val;
    };


    const fetchRemoteHistory = async (parameterId) => {
        try {
            const url = `/?page=api_history&param=${parameterId}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error('API Error');
            return await response.json();
        } catch (e) {
            console.error('Fetch error:', e);
            return [];
        }
    };

    const resolveColor = (color) => {
        if (typeof color === 'string' && color.startsWith('var(')) {
            const match = color.match(/var\((--[^)]+)\)/);
            return match ? getCssVar(match[1]) : color;
        }
        return color;
    };

    window.renderSparkline = function (card) {
        const slug = card.dataset.slug;
        if (!slug) return;

        const type = card.dataset.chartType || 'line';

        const valueOnlyContainer = card.querySelector('.card-value-only-container');
        const sparkContainer = card.querySelector('.card-spark');
        const headerValue = card.querySelector('.card-header .value');
        if (type === 'value') {
            if (valueOnlyContainer) valueOnlyContainer.style.display = 'flex';
            if (sparkContainer) sparkContainer.style.display = 'none';
            if (headerValue) headerValue.style.display = 'none';
            return;
        } else {
            if (valueOnlyContainer) valueOnlyContainer.style.display = 'none';
            if (sparkContainer) sparkContainer.style.display = 'block';
            if (headerValue) headerValue.style.display = 'flex';
        }

        const dataList = card.querySelector("ul[data-spark]");
        const canvas = card.querySelector(".card-spark-canvas");

        if (!canvas || !dataList) return;

        const items = dataList.querySelectorAll("li");
        const noDataPlaceholder = card.querySelector(".no-data-placeholder");

        if (!items.length) {
            if (canvas) canvas.style.display = 'none';
            if (noDataPlaceholder) noDataPlaceholder.style.display = 'flex';
            return;
        }

        const rawData = [];

        items.forEach((item) => {
            const time = item.dataset.time || "";
            const rawVal = item.dataset.value;
            const val = (rawVal === '' || rawVal === undefined || rawVal === 'null') ? null : Number(rawVal);

            if (!time) return;
            if (val !== null && !Number.isFinite(val)) return;

            const d = new Date(time);
            if (!isNaN(d.getTime())) {
                rawData.push([d.getTime(), val]);
            }
        });

        if (!rawData.length) {
            if (canvas) canvas.style.display = 'none';
            if (noDataPlaceholder) noDataPlaceholder.style.display = 'flex';
            return;
        }

        rawData.sort((a, b) => a[0] - b[0]);

        if (canvas) canvas.style.display = 'block';
        if (noDataPlaceholder) noDataPlaceholder.style.display = 'none';

        const chartColor = resolveColor("var(--chart-color)") || '#275afe';
        const gridColor = resolveColor("var(--chart-grid-color)") || '#e5e7eb';
        const tickColor = resolveColor("var(--chart-tick-color)") || '#6b7280';
        const tooltipBg = resolveColor("var(--chart-tooltip-bg)") || '#ffffff';
        const tooltipText = resolveColor("var(--chart-tooltip-text)") || '#111827';
        const tooltipBorder = resolveColor("var(--chart-tooltip-border)") || '#e5e7eb';

        const parseDatasetNumber = (val) => (val !== undefined && val !== null && val !== '') ? Number(val) : NaN;
        const thresholds = {
            nmin: parseDatasetNumber(card.dataset.nmin),
            nmax: parseDatasetNumber(card.dataset.nmax),
            cmin: parseDatasetNumber(card.dataset.cmin),
            cmax: parseDatasetNumber(card.dataset.cmax)
        };
        const view = {
            min: parseDatasetNumber(card.dataset.dmin),
            max: parseDatasetNumber(card.dataset.dmax)
        };

        if (canvas.chartInstance) {
            canvas.chartInstance.dispose();
        }

        const chartInstance = echarts.init(canvas, null, {
            renderer: 'canvas',
            devicePixelRatio: window.devicePixelRatio
        });
        canvas.chartInstance = chartInstance;

        let options = {};

        if (type === 'gauge') {
            const currentVal = rawData[rawData.length - 1][1];
            const gaugeMin = Number.isFinite(view.min) ? view.min : 0;
            const gaugeMax = parseFloat(card.dataset.max) || (Number.isFinite(view.max) ? view.max : 100);
            const nmin = thresholds.nmin, nmax = thresholds.nmax, cmin = thresholds.cmin, cmax = thresholds.cmax;
            const range = gaugeMax - gaugeMin;
            const c_red = resolveColor('var(--chart-band-red)') || 'rgba(239,68,68,0.45)';
            const c_yellow = resolveColor('var(--chart-band-yellow)') || 'rgba(245,158,11,0.45)';
            const c_green = resolveColor('var(--chart-band-green)') || 'rgba(34,197,94,0.45)';
            const axisLineColors = [];
            if (range > 0) {
                const r = (v) => Math.max(0, Math.min(1, (v - gaugeMin) / range));
                if (Number.isFinite(cmin) && Number.isFinite(nmin) && Number.isFinite(nmax) && Number.isFinite(cmax)) {
                    axisLineColors.push([r(cmin), c_red], [r(nmin), c_yellow], [r(nmax), c_green], [r(cmax), c_yellow], [1, c_red]);
                } else if (Number.isFinite(nmin) && Number.isFinite(nmax)) {
                    axisLineColors.push([r(nmin), c_yellow], [r(nmax), c_green], [1, c_yellow]);
                } else { axisLineColors.push([1, chartColor]); }
            } else { axisLineColors.push([1, chartColor]); }

            options = {
                series: [{
                    type: 'gauge', min: gaugeMin, max: gaugeMax,
                    center: ['50%', '58%'], radius: '90%',
                    startAngle: 210, endAngle: -30, splitNumber: 4,
                    axisLine: { lineStyle: { width: 10, color: axisLineColors } },
                    axisTick: { distance: 2, length: 3, lineStyle: { color: 'auto', width: 1 } },
                    splitLine: { distance: 2, length: 8, lineStyle: { color: 'auto', width: 1.5 } },
                    axisLabel: { color: tickColor, distance: 12, fontSize: 9 },
                    pointer: { length: '50%', width: 4, itemStyle: { color: chartColor } },
                    anchor: { show: true, showAbove: true, size: 8, itemStyle: { borderWidth: 2, borderColor: chartColor, color: tooltipBg } },
                    title: { show: false },
                    detail: {
                        valueAnimation: true, fontSize: 16, fontWeight: 700,
                        color: chartColor, offsetCenter: [0, '75%'],
                        formatter: function(v) { return v.toFixed(1); }
                    },
                    data: [{ value: Math.round(currentVal * 100) / 100 }]
                }]
            };
        } else if (type === 'pie' || type === 'doughnut') {
            const currentVal = rawData[0][1];
            const max = parseFloat(card.dataset.max) || 100;
            const remaining = Math.max(0, max - currentVal);
            const radius = type === 'doughnut' ? ['50%', '90%'] : '90%';

            options = {
                tooltip: {
                    trigger: 'item',
                    backgroundColor: tooltipBg,
                    textStyle: { color: tooltipText },
                    borderColor: tooltipBorder,
                },
                series: [
                    {
                        type: 'pie',
                        radius: radius,
                        center: ['50%', '50%'],
                        data: [
                            { value: currentVal, name: 'Mesure', itemStyle: { color: chartColor } },
                            { value: remaining, name: 'Reste', itemStyle: { color: 'rgba(0,0,0,0.1)' } }
                        ],
                        label: { show: false },
                        silent: false
                    }
                ]
            };
        } else {
            const eType = (type === 'step' || type === 'line') ? 'line' : (type === 'bar' ? 'bar' : 'scatter');

            const markArea = [];
            const nmin = thresholds.nmin;
            const nmax = thresholds.nmax;
            const cmin = thresholds.cmin;
            const cmax = thresholds.cmax;
            const c_red = resolveColor('var(--chart-band-red)');
            const c_yellow = resolveColor('var(--chart-band-yellow)');
            const c_green = resolveColor('var(--chart-band-green)');

            const bMin = view.min !== undefined && view.min !== null && !isNaN(view.min) ? view.min : 0;
            const bMax = view.max !== undefined && view.max !== null && !isNaN(view.max) ? view.max : 250;

            if (Number.isFinite(cmax) && Number.isFinite(nmin) && cmax <= nmin) {
                markArea.push([{ yAxis: cmax, itemStyle: { color: c_red } }, { yAxis: bMin }]);
                markArea.push([{ yAxis: nmin, itemStyle: { color: c_yellow } }, { yAxis: cmax }]);
                const greenTop = Number.isFinite(nmax) ? nmax : bMax;
                if (greenTop > nmin) markArea.push([{ yAxis: greenTop, itemStyle: { color: c_green } }, { yAxis: nmin }]);
                if (Number.isFinite(nmax) && bMax > nmax) markArea.push([{ yAxis: bMax, itemStyle: { color: c_yellow } }, { yAxis: nmax }]);
            } else if (Number.isFinite(nmax) && Number.isFinite(cmin) && nmax <= cmin) {
                const greenBottom = Number.isFinite(nmin) ? nmin : bMin;
                if (Number.isFinite(nmin) && greenBottom > bMin) markArea.push([{ yAxis: greenBottom, itemStyle: { color: c_yellow } }, { yAxis: bMin }]);
                if (nmax > greenBottom) markArea.push([{ yAxis: nmax, itemStyle: { color: c_green } }, { yAxis: greenBottom }]);
                markArea.push([{ yAxis: cmin, itemStyle: { color: c_yellow } }, { yAxis: nmax }]);
                markArea.push([{ yAxis: bMax, itemStyle: { color: c_red } }, { yAxis: cmin }]);
            } else {
                if (Number.isFinite(cmin)) markArea.push([{ yAxis: cmin, itemStyle: { color: c_red } }, { yAxis: bMin }]);

                let greenBottom = bMin;
                if (Number.isFinite(nmin)) {
                    let bottomEdge = Number.isFinite(cmin) ? cmin : bMin;
                    greenBottom = nmin;
                    if (nmin > bottomEdge) markArea.push([{ yAxis: nmin, itemStyle: { color: c_yellow } }, { yAxis: bottomEdge }]);
                }

                let greenTop = bMax;
                if (Number.isFinite(nmax)) {
                    let topEdge = Number.isFinite(cmax) ? cmax : bMax;
                    greenTop = nmax;
                    if (topEdge > nmax) markArea.push([{ yAxis: topEdge, itemStyle: { color: c_yellow } }, { yAxis: nmax }]);
                }

                if (greenTop > greenBottom) {
                    markArea.push([{ yAxis: greenTop, itemStyle: { color: c_green } }, { yAxis: greenBottom }]);
                }

                if (Number.isFinite(cmax)) {
                    markArea.push([{ yAxis: bMax, itemStyle: { color: c_red } }, { yAxis: cmax }]);
                }
            }

            options = {
                grid: {
                    top: 10,
                    bottom: 0,
                    left: 0,
                    right: 15,
                    containLabel: true
                },
                tooltip: {
                    trigger: 'axis',
                    backgroundColor: tooltipBg,
                    textStyle: { color: tooltipText },
                    borderColor: tooltipBorder,
                    formatter: function (params) {
                        const date = new Date(parseInt(params[0].value[0]));
                        if (isNaN(date.getTime())) return '';
                        const dateStr = date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
                        const timeStr = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                        const val = params[0].value[1];
                        return `${dateStr} ${timeStr}<br/><b>${val}</b>`;
                    }
                },
                xAxis: {
                    type: 'time',
                    boundaryGap: false,
                    show: true,
                    z: 5,
                    splitLine: { show: true, lineStyle: { color: gridColor, type: 'solid' } },
                    axisLabel: {
                        show: true,
                        color: tickColor,
                        formatter: '{HH}:{mm}',
                        interval: 'auto',
                        hideOverlap: true
                    },
                    axisTick: { show: false },
                    axisLine: { show: false }
                },
                yAxis: {
                    type: 'value',
                    min: view.min,
                    max: view.max,
                    show: true,
                    z: 5,
                    splitLine: { show: true, lineStyle: { color: gridColor, type: 'solid' } },
                    axisLabel: { show: true, color: tickColor },
                    axisTick: { show: false },
                    axisLine: { show: false }
                },
                series: [{
                    data: rawData,
                    type: eType,
                    step: type === 'step' ? 'middle' : false,
                    showSymbol: type === 'scatter',
                    symbolSize: type === 'scatter' ? 4 : 0,
                    smooth: type !== 'step',
                    connectNulls: true,
                    itemStyle: { color: chartColor },
                    lineStyle: { color: chartColor, width: 2 },
                    markArea: {
                        silent: true,
                        data: markArea
                    }
                }]
            };

            const durationVal = card.dataset.cardDisplayDuration || card.dataset.displayDuration;
            if (durationVal && durationVal !== 'all' && rawData.length > 0) {
                const hours = parseFloat(durationVal);
                if (!isNaN(hours)) {
                    const lastTime = rawData[rawData.length - 1][0];
                    const minTime = lastTime - (hours * 3600 * 1000);
                    options.dataZoom = [
                        { type: 'inside', startValue: minTime, endValue: lastTime, filterMode: 'none', zoomLock: true, zoomOnMouseWheel: false, moveOnMouseMove: false }
                    ];
                }
            } else if (durationVal === 'all') {
                options.dataZoom = [
                    { type: 'inside', start: 0, end: 100, filterMode: 'none', zoomLock: true, zoomOnMouseWheel: false, moveOnMouseMove: false }
                ];
            }
        }

        options.animation = localStorage.getItem('dashmed_chart_animation') !== 'false';

        chartInstance.setOption(options);

        const ro = new ResizeObserver(() => {
            chartInstance.resize();
        });
        ro.observe(canvas.parentElement);

    };

    cards.forEach((card) => {
        window.renderSparkline(card);
    });

    document.addEventListener('updateSparkline', function (e) {
        const { slug, type } = e.detail;
        const cards = document.querySelectorAll(`article.card[data-slug="${slug}"]`);
        cards.forEach(card => {
            card.dataset.chartType = type;
            window.renderSparkline(card);
        });
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.card-chart-btn');
        if (!btn || !btn.dataset.cardChartType) return;

        e.preventDefault();

        const panel = btn.closest('.modal-grid');
        if (!panel) return;

        const group = btn.closest('.chart-type-group');
        if (group) {
            group.querySelectorAll('.card-chart-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        const newType = btn.dataset.cardChartType;
        const paramId = panel.dataset.paramId;

        const slug = panel.id.replace(/^.*panel-/, '');
        const card = document.querySelector(`article.card[data-slug="${slug}"]`);
        if (card) {
            card.dataset.chartType = newType;
            window.renderSparkline(card);
        }

        // Sync active state back to the source detail element so reopening the modal preserves it
        const detailId = card ? card.getAttribute('data-detail-id') : null;
        const sourceDetail = detailId ? document.getElementById(detailId) : null;
        if (sourceDetail) {
            sourceDetail.querySelectorAll('.card-chart-btn').forEach(b => b.classList.remove('active'));
            const match = sourceDetail.querySelector(`.card-chart-btn[data-card-chart-type="${newType}"]`);
            if (match) match.classList.add('active');
        }

        if (paramId) {
            const formData = new FormData();
            formData.append('parameter_id', paramId);
            formData.append('chart_type', newType);
            formData.append('chart_pref_submit', '1');
            fetch(window.location.href, { method: 'POST', body: formData }).catch(console.error);
        }
    });

    document.addEventListener('change', function (e) {
        const select = e.target.closest('.card-interval-select');
        if (select) {
            const card = select.closest('article.card');
            if (!card) return;

            const val = select.value;
            const slug = card.dataset.slug;

            card.dataset.cardDisplayDuration = val;

            const formData = new FormData();
            formData.append('parameter_id', card.dataset.parameterId || slug);
            formData.append('chart_type', val);
            formData.append('chart_pref_submit', '1');
            formData.append('preference_type', 'card_duration');
            fetch(window.location.href, { method: 'POST', body: formData }).catch(console.error);

            const canvas = card.querySelector(".card-spark-canvas");
            if (canvas && canvas.chartInstance) {
                const hours = parseFloat(val);
                if (val !== 'all' && !isNaN(hours)) {
                    const dataList = card.querySelector("ul[data-spark]");
                    const items = dataList ? dataList.querySelectorAll("li") : [];
                    let hasEnough = false;

                    if (items.length > 0) {
                        const firstTime = new Date(items[0].dataset.time).getTime();
                        const lastTime = new Date(items[items.length - 1].dataset.time).getTime();
                        if (lastTime - firstTime >= (hours * 3600 * 1000)) {
                            hasEnough = true;
                        }
                    }

                    if (!hasEnough) {
                        const paramId = card.dataset.parameterId;
                        fetchRemoteHistory(paramId).then(data => {
                            if (data && Array.isArray(data)) {
                                if (dataList) {
                                    const existingTimes = new Set();
                                    items.forEach(li => existingTimes.add(li.dataset.time));

                                    data.forEach(item => {
                                        if (!existingTimes.has(item.time_iso)) {
                                            const li = document.createElement('li');
                                            li.dataset.time = item.time_iso;
                                            li.dataset.value = item.value;
                                            li.dataset.flag = item.flag;
                                            dataList.appendChild(li);
                                        }
                                    });
                                }
                                window.renderSparkline(card);
                            }
                        });
                        return;
                    }
                }

                window.renderSparkline(card);
            }
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.card-interval-select')) {
            e.stopPropagation();
        }
    }, true);

    window.addEventListener('DashMedDurationChange', function (e) {
    });


    window.addEventListener('DashMedMetricsUpdate', function (event) {
        if (!document.querySelector("article.card")) return;
        try {
            const metrics = event.detail;
            if (metrics.error) return;

            let requiresAlertCheck = false;

            metrics.forEach(metric => {
                const card = document.querySelector(`article.card[data-slug="${metric.slug}"]`);
                if (!card) return;

                const valueEl = card.querySelector('.value span:first-child');
                if (valueEl && metric.value !== '') valueEl.textContent = metric.value;

                const bigValueEl = card.querySelector('.big-value');
                if (bigValueEl && metric.value !== '') bigValueEl.textContent = metric.value;

                const unitEls = card.querySelectorAll('.unit');
                unitEls.forEach(el => { if (metric.unit) el.textContent = metric.unit; });

                const criticalIcon = card.querySelector('.status-critical');
                const warningIcon = card.querySelector('.status-warning');

                let oldState = 'normal';
                if (card.classList.contains('card--alert')) oldState = 'alert';
                else if (card.classList.contains('card--warn')) oldState = 'warn';
                let newState = 'normal';

                if (metric.state_class && metric.state_class.includes('card--alert')) {
                    newState = 'alert';
                    if (criticalIcon) criticalIcon.style.display = 'flex';
                    if (warningIcon) warningIcon.style.display = 'none';
                    card.classList.add('card--alert');
                    card.classList.remove('card--warn');
                } else if (metric.state_class && metric.state_class.includes('card--warn')) {
                    newState = 'warn';
                    if (criticalIcon) criticalIcon.style.display = 'none';
                    if (warningIcon) warningIcon.style.display = 'flex';
                    card.classList.add('card--warn');
                    card.classList.remove('card--alert');
                } else {
                    if (criticalIcon) criticalIcon.style.display = 'none';
                    if (warningIcon) warningIcon.style.display = 'none';
                    card.classList.remove('card--alert', 'card--warn');
                }

                if (oldState !== newState && newState !== 'normal') {
                    requiresAlertCheck = true;
                }

                const canvas = card.querySelector(".card-spark-canvas");
                const cardChartType = card.dataset.chartType || metric.chart_type;
                if (cardChartType === 'gauge') {
                    if (canvas && canvas.chartInstance && metric.value !== '') {
                        canvas.chartInstance.setOption({
                            series: [{ data: [{ value: Math.round(Number(metric.value) * 100) / 100 }] }]
                        });
                    }
                } else if (metric.chart_type === 'pie' || metric.chart_type === 'doughnut') {
                    if (canvas && canvas.chartInstance && metric.value !== '') {
                        const chart = canvas.chartInstance;
                        const currentVal = Number(metric.value);
                        const max = parseFloat(card.dataset.max) || 100;
                        const remaining = Math.max(0, max - currentVal);

                        chart.setOption({
                            series: [{
                                data: [
                                    { value: currentVal, name: 'Mesure', itemStyle: { color: resolveColor("var(--chart-color)") } },
                                    { value: remaining, name: 'Reste', itemStyle: { color: 'rgba(0,0,0,0.1)' } }
                                ]
                            }]
                        });
                    }
                } else {
                    const dataList = card.querySelector("ul[data-spark]");
                    if (dataList && metric.time_iso && metric.value !== '') {
                        const existing = dataList.querySelector(`li[data-time="${metric.time_iso}"]`);
                        if (!existing) {
                            const li = document.createElement('li');
                            li.dataset.time = metric.time_iso;
                            li.dataset.value = metric.value;
                            li.dataset.flag = metric.is_crit_flag ? '1' : '0';

                            dataList.appendChild(li);

                            if (canvas && canvas.chartInstance) {
                                const chart = canvas.chartInstance;
                                const timeMs = new Date(metric.time_iso).getTime();
                                let val = (metric.value === '' || metric.value === undefined || metric.value === 'null') ? null : Number(metric.value);

                                if (isNaN(timeMs)) return;
                                if (val !== null && !Number.isFinite(val)) return;
                                if (typeof val === 'number') val = Math.round(val * 100) / 100;

                                const option = chart.getOption();
                                if (option.series && option.series.length > 0) {
                                    const ds = option.series[0].data || [];
                                    const exists = ds.some(p => p[0] === timeMs);
                                    if (!exists) {
                                        const maxPoints = parseInt(card.dataset.maxPoints || '300', 10);
                                        if (ds.length === 0 || timeMs >= ds[ds.length - 1][0]) {
                                            ds.push([timeMs, val]);
                                        } else {
                                            ds.push([timeMs, val]);
                                            ds.sort((a, b) => a[0] - b[0]);
                                        }
                                        if (ds.length > maxPoints) ds.splice(0, ds.length - maxPoints);

                                        const updateObj = { series: [{ data: ds }] };

                                        const durationVal = card.dataset.cardDisplayDuration || card.dataset.displayDuration;
                                        if (durationVal && durationVal !== 'all') {
                                            const hours = parseFloat(durationVal);
                                            const minTime = timeMs - (hours * 3600 * 1000);
                                            updateObj.dataZoom = [{ type: 'inside', startValue: minTime, endValue: timeMs, zoomLock: true, zoomOnMouseWheel: false, moveOnMouseMove: false }];
                                        }

                                        chart.setOption(updateObj);
                                    }
                                }
                            }
                        } else {
                            window.renderSparkline(card);
                        }
                    }
                }
            });

            if (requiresAlertCheck) {
                if (typeof DashMedGlobalAlerts !== 'undefined' && typeof DashMedGlobalAlerts.checkNow === 'function') {
                    DashMedGlobalAlerts.checkNow();
                }
            }
        } catch (e) {
            console.error('SSE metrics fetch error:', e);
        }
    });


})();

