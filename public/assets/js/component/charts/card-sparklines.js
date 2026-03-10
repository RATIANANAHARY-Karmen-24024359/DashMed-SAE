(function () {
    if (!window.echarts) return;

    const cards = document.querySelectorAll("article.card");
    if (!cards.length) return;

    const getCssVar = (name) => {
        let val = getComputedStyle(document.body || document.documentElement).getPropertyValue(name).trim();
        if (!val) return name;
        return val;
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
        const chartLoader = card.querySelector('.card-chart-loader');

        const startFakeProgress = (loader) => {
            if (!loader || loader.classList.contains('hidden')) {
                return () => { if (loader) loader.classList.add('hidden'); };
            }
            const bar = loader.querySelector('.loader-progress-bar');
            const text = loader.querySelector('.loader-progress-text');
            if (!bar || !text) {
                return () => { loader.classList.add('hidden'); };
            }
            let active = true;
            let progress = 0;
            const animate = () => {
                if (!active) return;
                if (progress < 40) progress += Math.random() * 10;
                else if (progress < 85) progress += Math.random() * 3;
                else if (progress < 95) progress += Math.random() * 0.5;
                if (progress > 95) progress = 95;

                bar.style.width = progress + '%';
                text.textContent = Math.floor(progress) + '%';
                setTimeout(animate, 20);
            };
            animate();

            return () => {
                active = false;
                bar.style.width = '100%';
                text.textContent = '100%';
                setTimeout(() => {
                    loader.classList.add('hidden');
                    setTimeout(() => { bar.style.width = '0%'; text.textContent = '0%'; }, 200);
                }, 300);
            };
        };

        const finishLoader = startFakeProgress(chartLoader);
        const hideLoader = () => { if (finishLoader) finishLoader(); };

        if (type === 'value') {
            if (valueOnlyContainer) valueOnlyContainer.style.display = 'flex';
            if (sparkContainer) sparkContainer.style.display = 'none';
            if (headerValue) headerValue.style.display = 'none';
            hideLoader();
            return;
        } else {
            if (valueOnlyContainer) valueOnlyContainer.style.display = 'none';
            if (sparkContainer) sparkContainer.style.display = 'block';
            if (headerValue) headerValue.style.display = 'flex';
        }

        const dataList = card.querySelector("ul[data-spark]");
        const canvas = card.querySelector(".card-spark-canvas");

        if (!canvas || !dataList) { hideLoader(); return; }

        const items = dataList.querySelectorAll("li");
        const noDataPlaceholder = card.querySelector(".no-data-placeholder");

        if (!items.length) {
            if (canvas) canvas.style.display = 'none';
            if (noDataPlaceholder) noDataPlaceholder.style.display = 'flex';
            hideLoader();
            return;
        }

        const rawData = [];

        items.forEach((item) => {
            const time = item.dataset.time || "";
            const val = Number(item.dataset.value);

            if (!time || !Number.isFinite(val)) return;

            const d = new Date(time);
            if (isNaN(d.getTime())) return;

            rawData.push([d.getTime(), val]);
        });

        if (!rawData.length) {
            if (canvas) canvas.style.display = 'none';
            if (noDataPlaceholder) noDataPlaceholder.style.display = 'flex';
            hideLoader();
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

        const eType = (type === 'line' || type === 'step') ? 'line' : (type === 'bar' ? 'bar' : 'scatter');
        const isStep = type === 'step';

        let bMin = view.min;
        let bMax = view.max;

        if (!Number.isFinite(bMin) || !Number.isFinite(bMax) || bMin === bMax) {
            bMin = 0;
            bMax = 250;
            if (rawData.length > 0) {
                const vals = rawData.map(p => p[1]);
                const minVal = Math.min(...vals);
                const maxVal = Math.max(...vals);
                if (maxVal > 250) bMax = Math.ceil(maxVal * 1.1);
                if (minVal < 0) bMin = Math.floor(minVal * 1.1);
                if (bMin === bMax) {
                    bMin -= 10;
                    bMax += 10;
                }
            }
        }

        if (type === 'gauge') {
            const lastVal = rawData.length > 0 ? rawData[rawData.length - 1][1] : 0;
            if (!Number.isFinite(bMax)) bMax = (lastVal > 0 ? lastVal * 1.5 : 100);

            let gaugeColors = [];
            if (Number.isFinite(thresholds.nmin) && Number.isFinite(thresholds.nmax) && bMax > bMin) {
                let nminP = (thresholds.nmin - bMin) / (bMax - bMin);
                let nmaxP = (thresholds.nmax - bMin) / (bMax - bMin);
                nminP = Math.max(0, Math.min(1, nminP));
                nmaxP = Math.max(0, Math.min(1, nmaxP));
                let c_yellow = resolveColor('var(--chart-band-yellow)') || '#f59e0b';
                let c_green = resolveColor('var(--chart-band-green)') || '#10b981';
                let c_red = resolveColor('var(--chart-band-red)') || '#ef4444';

                if (nminP > 0) gaugeColors.push([nminP, c_yellow]);
                if (nmaxP > nminP) gaugeColors.push([nmaxP, c_green]);
                if (nmaxP < 1) gaugeColors.push([1, c_red]);
            } else {
                gaugeColors = [[1, chartColor]];
            }

            options = {
                tooltip: { formatter: '{c}', backgroundColor: tooltipBg, textStyle: { color: tooltipText }, borderColor: tooltipBorder },
                series: [
                    {
                        name: 'Valeur',
                        type: 'gauge',
                        min: bMin,
                        max: bMax,
                        radius: '90%',
                        center: ['50%', '60%'],
                        axisLine: {
                            lineStyle: { width: 10, color: gaugeColors.length > 0 ? gaugeColors : [[1, chartColor]] }
                        },
                        pointer: { itemStyle: { color: chartColor }, width: 4 },
                        axisTick: { distance: -10, length: 8, lineStyle: { color: gridColor, width: 1 } },
                        splitLine: { distance: -10, length: 15, lineStyle: { color: gridColor, width: 2 } },
                        axisLabel: { color: tickColor, distance: 15, fontSize: 10 },
                        detail: { valueAnimation: true, formatter: '{value}', color: tickColor, fontSize: 16, offsetCenter: [0, '60%'] },
                        data: [{ value: lastVal }]
                    }
                ]
            };
        } else {
            const markArea = [];
            const nmin = thresholds.nmin;
            const nmax = thresholds.nmax;
            const cmin = thresholds.cmin;
            const cmax = thresholds.cmax;
            const c_red = resolveColor('var(--chart-band-red)');
            const c_yellow = resolveColor('var(--chart-band-yellow)');
            const c_green = resolveColor('var(--chart-band-green)');

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
                    axisTick: { show: true, lineStyle: { color: gridColor, opacity: 0.3 } },
                    axisLine: { show: true, lineStyle: { color: gridColor, opacity: 0.3 } }
                },
                yAxis: {
                    type: 'value',
                    min: bMin,
                    max: bMax,
                    show: true,
                    z: 5,
                    splitLine: { show: true, lineStyle: { color: gridColor, type: 'solid' } },
                    axisLabel: { show: true, color: tickColor },
                    axisTick: { show: true, lineStyle: { color: gridColor, opacity: 0.3 } },
                    axisLine: { show: true, lineStyle: { color: gridColor, opacity: 0.3 } }
                },
                series: [{
                    data: rawData,
                    type: eType,
                    showSymbol: type === 'scatter',
                    symbolSize: type === 'scatter' ? 4 : 0,
                    smooth: !isStep,
                    step: isStep ? 'end' : false,
                    itemStyle: { color: chartColor },
                    lineStyle: { color: chartColor, width: 2 },
                    areaStyle: undefined,
                    markArea: {
                        silent: true,
                        data: markArea
                    }
                }]
            };

            const durationVal = card.dataset.displayDuration;
            if (durationVal && durationVal !== 'all' && rawData.length > 0) {
                const hours = parseFloat(durationVal);
                if (!isNaN(hours)) {
                    const lastTime = rawData[rawData.length - 1][0];
                    const minTime = lastTime - (hours * 3600 * 1000);
                    options.dataZoom = [
                        { type: 'inside', startValue: minTime, endValue: lastTime, zoomLock: true, zoomOnMouseWheel: false, moveOnMouseMove: false }
                    ];
                }
            } else if (durationVal === 'all') {
                options.dataZoom = [
                    { type: 'inside', start: 0, end: 100, zoomLock: true, zoomOnMouseWheel: false, moveOnMouseMove: false }
                ];
            }

        }
        hideLoader();
        chartInstance.setOption(options, true);

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

        const card = document.querySelector(`article.card[data-slug="${panel.id.replace(/^.*panel-/, '')}"]`);
        if (card) {
            card.dataset.chartType = newType;
            window.renderSparkline(card);
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
        if (e.target.classList.contains('card-interval-select')) {
            const select = e.target;
            const card = select.closest('article.card');
            if (!card) return;

            const val = select.value;
            const slug = card.dataset.slug;

            card.dataset.cardDisplayDuration = val;

            const formData = new FormData();
            formData.append('parameter_id', slug);
            formData.append('chart_type', val);
            formData.append('chart_pref_submit', '1');
            formData.append('preference_type', 'card_duration');
            fetch(window.location.href, { method: 'POST', body: formData }).catch(console.error);

            const canvas = card.querySelector(".card-spark-canvas");
            if (canvas && canvas.chartInstance) {
                const chart = canvas.chartInstance;
                if (val === 'all') {
                    chart.dispatchAction({ type: 'dataZoom', start: 0, end: 100 });
                } else {
                    const hours = parseFloat(val);
                    if (!isNaN(hours)) {
                        const opt = chart.getOption();
                        const data = opt.series[0].data;
                        if (data && data.length > 0) {
                            const lastTime = data[data.length - 1][0];
                            const minTime = lastTime - (hours * 3600 * 1000);
                            chart.dispatchAction({ type: 'dataZoom', startValue: minTime, endValue: lastTime });
                        }
                    }
                }
            }
        }
    });

    window.addEventListener('DashMedDurationChange', function (e) {
    });


    window.addEventListener('DashMedMetricsUpdate', function (event) {
        if (!document.querySelector("article.card")) return;
        try {
            const metrics = event.detail;
            if (metrics.error) return;

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

                if (metric.state_class && metric.state_class.includes('card--alert')) {
                    if (criticalIcon) criticalIcon.style.display = 'flex';
                    if (warningIcon) warningIcon.style.display = 'none';
                    card.classList.add('card--alert');
                    card.classList.remove('card--warn');
                } else if (metric.state_class && metric.state_class.includes('card--warn')) {
                    if (criticalIcon) criticalIcon.style.display = 'none';
                    if (warningIcon) warningIcon.style.display = 'flex';
                    card.classList.add('card--warn');
                    card.classList.remove('card--alert');
                } else {
                    if (criticalIcon) criticalIcon.style.display = 'none';
                    if (warningIcon) warningIcon.style.display = 'none';
                    card.classList.remove('card--alert', 'card--warn');
                }

                const canvas = card.querySelector(".card-spark-canvas");

                const dataList = card.querySelector("ul[data-spark]");
                if (dataList && metric.time_iso && metric.value !== '') {
                    const existing = dataList.querySelector(`li[data-time="${metric.time_iso}"]`);
                    if (!existing) {
                        const li = document.createElement('li');
                        li.dataset.time = metric.time_iso;
                        li.dataset.value = metric.value;
                        li.dataset.flag = metric.is_crit_flag ? '1' : '0';

                        dataList.appendChild(li);

                        const val = Number(metric.value);
                        const timeMs = new Date(metric.time_iso).getTime();

                        if (canvas && canvas.chartInstance) {
                            const chart = canvas.chartInstance;
                            const option = chart.getOption();
                            if (option.series && option.series.length > 0) {
                                const seriesType = option.series[0].type;
                                if (seriesType === 'gauge') {
                                    chart.setOption({ series: [{ data: [{ value: val }] }] });
                                    return;
                                } else {
                                    if (isNaN(timeMs)) return;
                                    const ds = option.series[0].data || [];
                                    const exists = ds.some(p => p[0] === timeMs);
                                    if (!exists) {
                                        ds.push([timeMs, val]);
                                        ds.sort((a, b) => a[0] - b[0]);
                                        if (ds.length > 100) ds.shift();
                                        const updateObj = { series: [{ data: ds }] };
                                        const durationVal = card.dataset.displayDuration;
                                        if (durationVal && durationVal !== 'all') {
                                            const hours = parseFloat(durationVal);
                                            const minTime = timeMs - (hours * 3600 * 1000);
                                            updateObj.dataZoom = [{ type: 'inside', startValue: minTime, endValue: timeMs, zoomLock: true, zoomOnMouseWheel: false, moveOnMouseMove: false }];
                                        }
                                        chart.setOption(updateObj);
                                    }
                                    return;
                                }
                            }
                        }
                        window.renderSparkline(card);
                    }
                }
            });
        } catch (e) {
            console.error('SSE metrics fetch error:', e);
        }
    });

})();
