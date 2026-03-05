'use strict';

const CLOSE_ICON = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M6 18L18 6M6 6l12 12"/>
    </svg>`;

const CLOCK_ICON = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12 6 12 12 16 14"/>
    </svg>`;

function scrollToCard(parameterId) {
    const panel = document.querySelector(`[data-param-id="${parameterId}"]`);
    let found = false;

    if (panel) {
        const slug = panel.closest('[id^="detail-"]')?.id?.replace('detail-', '');
        if (slug) {
            const card = document.querySelector(`[data-slug="${slug}"]`);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.add('card--highlight');
                setTimeout(() => card.classList.remove('card--highlight'), 2000);
                found = true;
            }
        }
    }

    if (!found) {
        const cardByParam = document.querySelector(`.card[data-detail-id*="${parameterId}"]`);
        if (cardByParam) {
            cardByParam.scrollIntoView({ behavior: 'smooth', block: 'center' });
            cardByParam.classList.add('card--highlight');
            setTimeout(() => cardByParam.classList.remove('card--highlight'), 2000);
            found = true;
        }
    }

    if (!found) {
        const currentUrl = new URL(window.location.href);
        const isMonitoring = currentUrl.pathname.includes('/monitoring') ||
            currentUrl.searchParams.get('page') === 'monitoring';

        if (!isMonitoring) {
            const monitoringUrl = new URL(window.location.href);
            monitoringUrl.searchParams.set('page', 'monitoring');
            monitoringUrl.searchParams.set('highlight', parameterId);

            if (monitoringUrl.pathname.endsWith('/dashboard')) {
                monitoringUrl.pathname = monitoringUrl.pathname.replace(/\/dashboard$/, '/monitoring');
            }

            window.location.href = monitoringUrl.toString();
        }
    }
}

const _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
if (_audioCtx.state === 'suspended') {
    document.addEventListener('click', () => _audioCtx.resume(), { once: true });
}

const DashMedGlobalAlerts = (function () {
    const API_URL = 'api-alerts.php';
    const CHECK_INTERVAL = 5000;
    let displayedIds = new Set();
    let activeCriticalToasts = new Map();

    function getNotificationTimeout() {
        const saved = localStorage.getItem('dashmed_notif_timeout');
        return saved ? parseInt(saved, 10) : 20000;
    }

    const esc = s => {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    };

    function parseAlertData(a) {
        const param = a.title?.split('—')[1]?.trim() || 'Paramètre';
        const valMatch = a.message?.match(/(\d+[,.]?\d*)\s*([^\(]+)/);
        const val = valMatch ? valMatch[1] : a.value || '—';
        const unit = valMatch ? valMatch[2].trim() : a.unit || '';
        const threshMatch = a.message?.match(/seuil\s+(min|max)\s*:\s*(\d+[,.]?\d*)\s*([^\)]*)/i);
        return {
            param,
            val,
            unit,
            threshType: threshMatch?.[1] || '',
            threshVal: threshMatch?.[2] || '',
            threshUnit: threshMatch?.[3]?.trim() || unit,
            timestamp: a.timestamp
        };
    }

    function buildToastHTML(a, type, timeout) {
        const { param, timestamp } = parseAlertData(a);
        const hasCard = !!a.parameterId;

        let t = new Date();
        if (timestamp) {
            if (typeof timestamp === 'string') {
                t = new Date(timestamp.replace(' ', 'T'));
            } else {
                t = new Date(timestamp);
            }
            if (isNaN(t.getTime())) t = new Date();
        }

        const heures = String(t.getHours()).padStart(2, '0');
        const minutes = String(t.getMinutes()).padStart(2, '0');
        const timeStr = `${heures}H${minutes}`;
        const msg = `DEPASSEMENT DE ${param.toUpperCase()} À ${timeStr}.`;

        return `
            <div class="medical-alert ${type} ${hasCard ? 'medical-alert--clickable' : ''}" ${hasCard ? `data-param-id="${esc(String(a.parameterId))}"` : ''}>
                <div class="medical-alert-body">
                    <div class="medical-alert-param">${esc(msg)}</div>
                </div>
                <button class="medical-alert-close" data-close>${CLOSE_ICON}</button>
                <div class="medical-alert-progress"><div class="medical-alert-progress-bar" style="animation-duration:${timeout}ms"></div></div>
            </div>`;
    }

    function buildInfoToastHTML(a, timeout) {
        const title = a.title?.split('—')[1]?.trim() || 'Rendez-vous';
        return `
            <div class="medical-alert info">
                <div class="medical-alert-body">
                    <div class="medical-alert-param">${esc(title)}</div>
                    <div class="medical-alert-value">${esc(a.rdvTime || '')}</div>
                    <div class="medical-alert-threshold">Dr <strong>${esc(a.doctor || '')}</strong></div>
                </div>
                <button class="medical-alert-close" data-close>${CLOSE_ICON}</button>
                <div class="medical-alert-progress"><div class="medical-alert-progress-bar" style="animation-duration:${timeout}ms"></div></div>
            </div>`;
    }

    const baseToastOpts = (msg, timeout) => ({
        message: msg,
        position: 'topRight',
        progressBar: false,
        close: false,
        timeout: timeout,
        transitionIn: 'fadeInLeft',
        transitionOut: 'fadeOutRight',
        layout: 1,
        backgroundColor: 'transparent',
        onOpening: (_, t) => t.querySelector('[data-close]')?.addEventListener('click', () => {
            iziToast.hide({}, t);
        })
    });

    function showWarningToast(a) {
        const timeout = getNotificationTimeout();
        iziToast.warning({
            ...baseToastOpts(buildToastHTML(a, 'warning', timeout), timeout),
            onOpening: (_, t) => {
                t.querySelector('[data-close]')?.addEventListener('click', () => iziToast.hide({}, t));
                t.querySelector('.medical-alert--clickable')?.addEventListener('click', (e) => {
                    if (!e.target.closest('[data-close]')) {
                        scrollToCard(a.parameterId);
                    }
                });
            }
        });
    }

    function showInfoToast(a) {
        const timeout = getNotificationTimeout();
        iziToast.info({ ...baseToastOpts(buildInfoToastHTML(a, timeout), timeout) });
    }

    function showCriticalToast(a) {
        const id = getAlertId(a);
        if (activeCriticalToasts.has(id)) return;

        const timeout = getNotificationTimeout() * 1.5;
        const opts = {
            ...baseToastOpts(buildToastHTML(a, 'critical', timeout), timeout),
            onOpening: (_, t) => {
                activeCriticalToasts.set(id, t);
                t.querySelector('[data-close]')?.addEventListener('click', () => {
                    activeCriticalToasts.delete(id);
                    iziToast.hide({}, t);
                });
                t.querySelector('.medical-alert--clickable')?.addEventListener('click', (e) => {
                    if (!e.target.closest('[data-close]')) {
                        scrollToCard(a.parameterId);
                    }
                });
            },
            onClosed: () => {
                activeCriticalToasts.delete(id);
            }
        };

        iziToast.error(opts);
    }

    function dismissCriticalToast(parameterId) {
        for (const [id, toastEl] of activeCriticalToasts.entries()) {
            if (id.startsWith(parameterId + '_')) {
                iziToast.hide({}, toastEl);
                activeCriticalToasts.delete(id);
            }
        }
    }

    function getAlertId(a) {
        return `${a.parameterId}_${a.type || a.rdvTime || ''}`;
    }

    function playAlertSound(type) {
        const srcs = {
            error: 'assets/sounds/critical.wav',
            warning: 'assets/sounds/warning.wav',
            info: 'assets/sounds/info.wav',
        };
        const audio = new Audio(srcs[type] || srcs.warning);

        const volVal = localStorage.getItem('dashmed_notif_volume');
        const baseVol = volVal !== null ? parseFloat(volVal) : 0.5;
        audio.volume = type === 'error' ? Math.min(1.0, baseVol * 1.5) : baseVol;

        _audioCtx.resume().then(() => {
            const source = _audioCtx.createMediaElementSource(audio);
            source.connect(_audioCtx.destination);
            audio.play().catch(() => { });
        });
    }

    function showAlert(a) {
        if (localStorage.getItem('dashmed_dnd') === 'true') return;
        if (!a?.type) return;

        playAlertSound(a.type);
        if (typeof NotifHistory !== 'undefined') NotifHistory.add(a);

        if (a.type === 'error') showCriticalToast(a);
        else if (a.type === 'info') showInfoToast(a);
        else showWarningToast(a);
    }

    async function fetchAlerts() {
        if (localStorage.getItem('dashmed_dnd') === 'true') return [];
        try {
            const room = new URLSearchParams(location.search).get('room') || '';
            const res = await fetch(room ? `${API_URL}?room=${room}` : API_URL);
            const data = await res.json();
            if (!data.success) return [];
            const alerts = data.alerts;
            const currentIds = new Set(alerts.map(a => getAlertId(a)));
            for (const [id] of activeCriticalToasts.entries()) {
                const parameterId = id.split('_')[0];
                const stillActive = alerts.some(a => a.type === 'error' && String(a.parameterId) === String(parameterId));
                if (!stillActive) dismissCriticalToast(parameterId);
            }
            return alerts;
        } catch { return []; }
    }

    const ACTIVE_STATES_KEY = 'dashmed_active_states_by_room';

    function getCurrentRoom() {
        const urlRoom = new URLSearchParams(location.search).get('room');
        if (urlRoom) return urlRoom;
        const cookieMatch = document.cookie.match(/room_id=(\d+)/);
        return cookieMatch ? cookieMatch[1] : null;
    }

    function getActiveStates() {
        const room = getCurrentRoom() || 'global';
        const all = JSON.parse(localStorage.getItem(ACTIVE_STATES_KEY) || '{}');
        return all[room] || {};
    }

    function saveActiveStates(states) {
        const room = getCurrentRoom() || 'global';
        const all = JSON.parse(localStorage.getItem(ACTIVE_STATES_KEY) || '{}');
        all[room] = states;
        localStorage.setItem(ACTIVE_STATES_KEY, JSON.stringify(all));
    }

    async function check() {
        if (localStorage.getItem('dashmed_dnd') === 'true') return;

        const alerts = await fetchAlerts();
        const activeStates = getActiveStates();
        const newStates = {};
        const toShow = [];

        alerts.forEach(a => {
            if (a.type === 'info') {
                const id = getAlertId(a);
                if (!displayedIds.has(id)) {
                    displayedIds.add(id);
                    toShow.push(a);
                }
            } else {
                const paramId = String(a.parameterId);
                const type = a.type;
                newStates[paramId] = type;

                if (activeStates[paramId] !== type) {
                    toShow.push(a);
                }
            }
        });

        saveActiveStates(newStates);

        toShow.forEach((a, i) => setTimeout(() => showAlert(a), i * 600));
    }

    function init() {
        if (typeof iziToast === 'undefined') return;

        const params = new URLSearchParams(window.location.search);
        const highlight = params.get('highlight');
        if (highlight) {
            setTimeout(() => {
                const panel = document.querySelector(`[data-param-id="${highlight}"]`);
                if (panel) {
                    const slug = panel.closest('[id^="detail-"]')?.id?.replace('detail-', '');
                    if (slug) {
                        const card = document.querySelector(`[data-slug="${slug}"]`);
                        if (card) {
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            card.click();
                        }
                    }
                } else {
                    const cardByParam = document.querySelector(`.card[data-detail-id*="${highlight}"]`);
                    if (cardByParam) {
                        cardByParam.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        cardByParam.click();
                    }
                }
                // Cleanup URL
                const url = new URL(window.location.href);
                url.searchParams.delete('highlight');
                window.history.replaceState({}, '', url.toString());
            }, 800);
        }

        setTimeout(check, 1500);
        setInterval(check, CHECK_INTERVAL);
    }

    return { init, checkNow: check };
})();

const NotifHistory = (function () {
    const STORAGE_KEY = 'notif_history_by_room';
    let panel = null, overlay = null;

    const getCurrentRoom = () => {
        const urlRoom = new URLSearchParams(location.search).get('room');
        if (urlRoom) return urlRoom;
        const cookieMatch = document.cookie.match(/room_id=(\d+)/);
        return cookieMatch ? cookieMatch[1] : null;
    };

    const getAllHistory = () => {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch { return {}; }
    };
    const getHistory = () => {
        const room = getCurrentRoom();
        if (!room) return [];
        const all = getAllHistory();
        return all[room] || [];
    };

    const saveHistory = h => {
        const room = getCurrentRoom();
        if (!room) return;
        const all = getAllHistory();
        all[room] = h.slice(0, 50);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    };

    const clearCurrentRoomHistory = () => {
        const room = getCurrentRoom();
        if (!room) return;
        const all = getAllHistory();
        delete all[room];
        localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    };

    function addToHistory(a) {
        const h = getHistory();
        h.unshift({ ...a, timestamp: Date.now() });
        saveHistory(h);
        updateBadge();
    }

    function removeFromHistory(i) {
        const h = getHistory();
        h.splice(i, 1);
        saveHistory(h);
        updateBadge();
    }


    function updateBadge() {
        const btn = document.querySelector('.action-btn[aria-label="Notifications"]');
        if (!btn) return;
        let badge = btn.querySelector('.notif-badge');
        const count = getHistory().length;
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notif-badge';
                btn.style.position = 'relative';
                btn.appendChild(badge);
            }
            badge.textContent = count > 9 ? '9+' : count;
        } else badge?.remove();
    }

    function formatTime(ts) {
        const d = new Date(ts), diff = Date.now() - d;
        if (diff < 60000) return 'À l\'instant';
        if (diff < 3600000) return `Il y a ${Math.floor(diff / 60000)} min`;
        if (diff < 86400000) return `Il y a ${Math.floor(diff / 3600000)}h`;
        return d.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getDndState() {
        return localStorage.getItem('dashmed_dnd') === 'true';
    }

    function setDndState(enabled) {
        localStorage.setItem('dashmed_dnd', enabled);
        const toggle = panel?.querySelector('#notif-panel-dnd');
        if (toggle) toggle.checked = enabled;
        syncProfileToggle(enabled);
    }

    function syncProfileToggle(enabled) {
        const profileToggle = document.getElementById('dnd-dev-toggle');
        if (profileToggle) profileToggle.checked = enabled;
    }

    function createPanel() {
        overlay = document.createElement('div');
        overlay.className = 'notif-panel-overlay';
        overlay.addEventListener('click', close);
        panel = document.createElement('div');
        panel.className = 'notif-panel';

        const volVal = localStorage.getItem('dashmed_notif_volume');
        const currentVol = volVal !== null ? parseFloat(volVal) * 100 : 50;

        panel.innerHTML = `
            <div class="notif-panel-header">
                <h2>Notifications</h2>
                <div class="notif-header-actions">
                    <div class="notif-volume-container">
                        <button class="notif-volume-btn" title="Volume des notifications">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M11 5L6 9H2v6h4l5 4V5z"/>
                                <path class="vol-wave vol-wave-1" d="M15.54 8.46a5 5 5 0 0 1 0 7.07" style="transition: opacity 0.2s;"/>
                                <path class="vol-wave vol-wave-2" d="M19.07 4.93a10 10 10 0 0 1 0 14.14" style="transition: opacity 0.2s;"/>
                            </svg>
                        </button>
                        <div class="notif-volume-slider-wrapper">
                            <input type="range" class="notif-volume-slider" min="0" max="100" value="${currentVol}" orient="vertical">
                        </div>
                    </div>
                    <div class="notif-time-container">
                        <button class="notif-time-btn" title="Durée des notifications">
                            ${CLOCK_ICON}
                        </button>
                        <div class="notif-time-selector-wrapper">
                            <button class="notif-time-option" data-time="5000">5 secondes</button>
                            <button class="notif-time-option" data-time="10000">10 secondes</button>
                            <button class="notif-time-option" data-time="20000">20 secondes</button>
                            <button class="notif-time-option" data-time="40000">40 secondes</button>
                            <button class="notif-time-option" data-time="60000">1 minute</button>
                        </div>
                    </div>
                    <button class="notif-panel-close">${CLOSE_ICON}</button>
                </div>
            </div>
            <div class="notif-panel-body"></div>
            <div class="notif-panel-dnd">
                <label class="notif-dnd-label">
                    <span>Ne pas déranger</span>
                    <div class="notif-dnd-toggle">
                        <input type="checkbox" id="notif-panel-dnd">
                        <span class="notif-dnd-slider"></span>
                    </div>
                </label>
            </div>
            <div class="notif-panel-footer">
                <button class="notif-clear-all">Tout effacer</button>
            </div>`;
        panel.querySelector('.notif-panel-close').addEventListener('click', close);
        panel.querySelector('.notif-clear-all').addEventListener('click', () => {
            clearCurrentRoomHistory();
            updateBadge();
            render();
        });

        const volBtn = panel.querySelector('.notif-volume-btn');
        const volWrapper = panel.querySelector('.notif-volume-slider-wrapper');
        const volSlider = panel.querySelector('.notif-volume-slider');
        const wave1 = panel.querySelector('.vol-wave-1');
        const wave2 = panel.querySelector('.vol-wave-2');

        const updateVolumeIcon = (val) => {
            if (val === 0) {
                wave1.style.opacity = '0';
                wave2.style.opacity = '0';
            } else if (val < 50) {
                wave1.style.opacity = '1';
                wave2.style.opacity = '0';
            } else {
                wave1.style.opacity = '1';
                wave2.style.opacity = '1';
            }
        };

        updateVolumeIcon(currentVol);

        volBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            volWrapper.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!volWrapper.contains(e.target) && !volBtn.contains(e.target)) {
                volWrapper.classList.remove('active');
            }
        });

        volSlider.addEventListener('input', (e) => {
            const val = parseInt(e.target.value, 10);
            localStorage.setItem('dashmed_notif_volume', (val / 100).toString());
            updateVolumeIcon(val);
        });

        const timeBtn = panel.querySelector('.notif-time-btn');
        const timeWrapper = panel.querySelector('.notif-time-selector-wrapper');
        const timeOptions = panel.querySelectorAll('.notif-time-option');

        const savedTime = localStorage.getItem('dashmed_notif_timeout') || '20000';
        timeOptions.forEach(opt => {
            if (opt.dataset.time === savedTime) opt.classList.add('active');
            opt.addEventListener('click', () => {
                const val = opt.dataset.time;
                localStorage.setItem('dashmed_notif_timeout', val);
                timeOptions.forEach(o => o.classList.remove('active'));
                opt.classList.add('active');
                timeWrapper.classList.remove('active');
            });
        });

        timeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            timeWrapper.classList.toggle('active');
            volWrapper.classList.remove('active');
        });

        document.addEventListener('click', (e) => {
            if (!timeWrapper.contains(e.target) && !timeBtn.contains(e.target)) {
                timeWrapper.classList.remove('active');
            }
        });

        const dndToggle = panel.querySelector('#notif-panel-dnd');
        dndToggle.checked = getDndState();
        dndToggle.addEventListener('change', e => setDndState(e.target.checked));
        document.body.appendChild(overlay);
        document.body.appendChild(panel);
    }

    function render() {
        if (!panel) createPanel();
        const body = panel.querySelector('.notif-panel-body'),
            footer = panel.querySelector('.notif-panel-footer'),
            h = getHistory();
        footer.style.display = h.length ? '' : 'none';
        if (!h.length) {
            body.innerHTML = '<div class="notif-panel-empty">Aucune notification</div>';
            return;
        }
        body.innerHTML = h.map((n, i) => {
            const type = n.type === 'error' ? 'critical' : (n.type === 'info' ? 'info' : 'warning');
            const param = n.title?.split('—')[1]?.trim() || n.rdvTime || 'Alerte';
            const hasCard = type !== 'info' && !!n.parameterId;

            let messageStr = '';
            if (type === 'info') {
                messageStr = n.rdvTime || '—';
            } else {
                let t = new Date();
                if (n.timestamp) {
                    if (typeof n.timestamp === 'string') {
                        t = new Date(n.timestamp.replace(' ', 'T'));
                    } else {
                        t = new Date(n.timestamp);
                    }
                    if (isNaN(t.getTime())) t = new Date();
                }
                const heures = String(t.getHours()).padStart(2, '0');
                const minutes = String(t.getMinutes()).padStart(2, '0');
                messageStr = `DEPASSEMENT DE ${param.toUpperCase()} À ${heures}H${minutes}.`;
            }

            return `<div class="notif-item ${type} ${hasCard ? 'notif-item--clickable' : ''}" data-idx="${i}" ${hasCard ? `data-param-id="${n.parameterId}"` : ''}>
                <div class="notif-item-content">
                    <div class="notif-item-header">
                        <div class="notif-item-param">${type === 'info' ? param : messageStr}</div>
                        <div class="notif-item-time">${formatTime(n.timestamp)}</div>
                    </div>
                    ${type === 'info' ? `<div class="notif-item-value">${messageStr}</div>` : ''}
                </div>
                <button class="notif-item-delete">${CLOSE_ICON}</button>
            </div>`;
        }).join('');
        body.querySelectorAll('.notif-item-delete').forEach(btn => btn.addEventListener('click', e => {
            e.stopPropagation();
            const item = btn.closest('.notif-item');
            item.classList.add('removing');
            setTimeout(() => {
                removeFromHistory(+item.dataset.idx);
                render();
            }, 250);
        }));
        body.querySelectorAll('.notif-item--clickable').forEach(item => item.addEventListener('click', e => {
            if (!e.target.closest('.notif-item-delete')) {
                scrollToCard(item.dataset.paramId);
                close();
            }
        }));
    }

    const open = () => {
        render();
        const dndToggle = panel?.querySelector('#notif-panel-dnd');
        if (dndToggle) dndToggle.checked = getDndState();
        overlay?.classList.add('active');
        panel?.classList.add('active');
        document.body.classList.add('notif-panel-open');
    };

    const close = () => {
        overlay?.classList.remove('active');
        panel?.classList.remove('active');
        document.body.classList.remove('notif-panel-open');
    };

    function init() {
        const btn = document.querySelector('.action-btn[aria-label="Notifications"]');
        btn?.addEventListener('click', e => {
            e.preventDefault();
            open();
        });
        updateBadge();

        const profileToggle = document.getElementById('dnd-dev-toggle');
        if (profileToggle) {
            profileToggle.addEventListener('change', e => setDndState(e.target.checked));
        }
    }

    return { init, add: addToHistory, isInHistory };
})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', DashMedGlobalAlerts.init);
    document.addEventListener('DOMContentLoaded', NotifHistory.init);
} else {
    DashMedGlobalAlerts.init();
    NotifHistory.init();
}