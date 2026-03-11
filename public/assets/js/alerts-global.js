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

const VOL_MUTE = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.0012 8.99984H9.1C8.53995 8.99984 8.25992 8.99984 8.04601 9.10883C7.85785 9.20471 7.70487 9.35769 7.60899 9.54585C7.5 9.75976 7.5 10.0398 7.5 10.5998V13.3998C7.5 13.9599 7.5 14.2399 7.60899 14.4538C7.70487 14.642 7.85785 14.795 8.04601 14.8908C8.25992 14.9998 8.53995 14.9998 9.1 14.9998H10.0012C10.5521 14.9998 10.8276 14.9998 11.0829 15.0685C11.309 15.1294 11.5228 15.2295 11.7143 15.3643C11.9305 15.5164 12.1068 15.728 12.4595 16.1512L15.0854 19.3023C15.5211 19.8252 15.739 20.0866 15.9292 20.1138C16.094 20.1373 16.2597 20.0774 16.3712 19.9538C16.5 19.811 16.5 19.4708 16.5 18.7902V5.20948C16.5 4.52892 16.5 4.18864 16.3712 4.04592C16.2597 3.92233 16.094 3.86234 15.9292 3.8859C15.7389 3.9131 15.5211 4.17451 15.0854 4.69733L12.4595 7.84843C12.1068 8.27166 11.9305 8.48328 11.7143 8.63542C11.5228 8.77021 11.309 8.87032 11.0829 8.93116C10.8276 8.99984 10.5521 8.99984 10.0012 8.99984Z"/>
    </svg>`;

const VOL_LOW = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 9.00009C18.6277 9.83575 18.9996 10.8745 18.9996 12.0001C18.9996 13.1257 18.6277 14.1644 18 15.0001M6.6 9.00009H7.5012C8.05213 9.00009 8.32759 9.00009 8.58285 8.93141C8.80903 8.87056 9.02275 8.77046 9.21429 8.63566C9.43047 8.48353 9.60681 8.27191 9.95951 7.84868L12.5854 4.69758C13.0211 4.17476 13.2389 3.91335 13.4292 3.88614C13.594 3.86258 13.7597 3.92258 13.8712 4.04617C14 4.18889 14 4.52917 14 5.20973V18.7904C14 19.471 14 19.8113 13.8712 19.954C13.7597 20.0776 13.594 20.1376 13.4292 20.114C13.239 20.0868 13.0211 19.8254 12.5854 19.3026L9.95951 16.1515C9.60681 15.7283 9.43047 15.5166 9.21429 15.3645C9.02275 15.2297 8.80903 15.1296 8.58285 15.0688C8.32759 15.0001 8.05213 15.0001 7.5012 15.0001H6.6C6.03995 15.0001 5.75992 15.0001 5.54601 14.8911C5.35785 14.7952 5.20487 14.6422 5.10899 14.4541C5 14.2402 5 13.9601 5 13.4001V10.6001C5 10.04 5 9.76001 5.10899 9.54609C5.20487 9.35793 5.35785 9.20495 5.54601 9.10908C5.75992 9.00009 6.03995 9.00009 6.6 9.00009Z"/>
    </svg>`;

const VOL_HIGH = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16.0004 9.00009C16.6281 9.83575 17 10.8745 17 12.0001C17 13.1257 16.6281 14.1644 16.0004 15.0001M18 5.29177C19.8412 6.93973 21 9.33459 21 12.0001C21 14.6656 19.8412 17.0604 18 18.7084M4.6 9.00009H5.5012C6.05213 9.00009 6.32759 9.00009 6.58285 8.93141C6.80903 8.87056 7.02275 8.77046 7.21429 8.63566C7.43047 8.48353 7.60681 8.27191 7.95951 7.84868L10.5854 4.69758C11.0211 4.17476 11.2389 3.91335 11.4292 3.88614C11.594 3.86258 11.7597 3.92258 11.8712 4.04617C12 4.18889 12 4.52917 12 5.20973V18.7904C12 19.471 12 19.8113 11.8712 19.954C11.7597 20.0776 11.594 20.1376 11.4292 20.114C11.239 20.0868 11.0211 19.8254 10.5854 19.3026L7.95951 16.1515C7.60681 15.7283 7.43047 15.5166 7.21429 15.3645C7.02275 15.2297 6.80903 15.1296 6.58285 15.0688C6.32759 15.0001 6.05213 15.0001 5.5012 15.0001H4.6C4.03995 15.0001 3.75992 15.0001 3.54601 14.8911C3.35785 14.7952 3.20487 14.6422 3.10899 14.4541C3 14.2402 3 13.9601 3 13.4001V10.6001C3 10.04 3 9.76001 3.10899 9.54609C3.20487 9.35793 3.35785 9.20495 3.54601 9.10908C3.75992 9.00009 4.03995 9.00009 4.6 9.00009Z"/>
    </svg>`;

function scrollToCard(parameterId) {
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
        return;
    }

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

    let lastSyncTime = 0;

    async function syncSettingsWithBackend(settings) {
        lastSyncTime = Date.now();
        try {
            await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_settings', ...settings })
            });
        } catch (err) { console.error('Failed to sync settings:', err); }
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
                let tStr = timestamp.replace(' ', 'T');
                if (!tStr.endsWith('Z')) tStr += 'Z';
                t = new Date(tStr);
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
        iziToast.show({
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
        iziToast.show({ ...baseToastOpts(buildInfoToastHTML(a, timeout), timeout) });
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

        iziToast.show(opts);
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
        if (!a?.type) return;

        if (typeof NotifHistory !== 'undefined') NotifHistory.add(a);

        if (localStorage.getItem('dashmed_dnd') === 'true') return;

        playAlertSound(a.type);

        if (a.type === 'error') showCriticalToast(a);
        else if (a.type === 'info') showInfoToast(a);
        else showWarningToast(a);
    }

    async function fetchAlerts() {
        try {
            const room = new URLSearchParams(location.search).get('room') || '';
            const res = await fetch(room ? `${API_URL}?room=${room}` : API_URL);
            const data = await res.json();
            if (!data.success) return [];

            if (data.settings && (Date.now() - lastSyncTime > 4000)) {
                if (data.settings.alert_volume !== undefined) {
                    localStorage.setItem('dashmed_notif_volume', data.settings.alert_volume.toString());
                }
                if (data.settings.alert_duration !== undefined) {
                    localStorage.setItem('dashmed_notif_timeout', data.settings.alert_duration.toString());
                }
                if (data.settings.alert_dnd !== undefined) {
                    const dnd = data.settings.alert_dnd ? 'true' : 'false';
                    localStorage.setItem('dashmed_dnd', dnd);
                }
                if (typeof NotifHistory !== 'undefined') {
                    if (NotifHistory.syncDnd && data.settings.alert_dnd !== undefined) {
                        NotifHistory.syncDnd(data.settings.alert_dnd);
                    }
                    if (NotifHistory.syncUI) NotifHistory.syncUI(data.settings);
                }
            }

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
        return cookieMatch ? cookieMatch[1] : 'global';
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
                            card.classList.add('card--highlight');
                            setTimeout(() => card.classList.remove('card--highlight'), 2000);
                        }
                    }
                } else {
                    const cardByParam = document.querySelector(`.card[data-detail-id*="${highlight}"]`);
                    if (cardByParam) {
                        cardByParam.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        cardByParam.classList.add('card--highlight');
                        setTimeout(() => cardByParam.classList.remove('card--highlight'), 2000);
                    }
                }
                const url = new URL(window.location.href);
                url.searchParams.delete('highlight');
                window.history.replaceState({}, '', url.toString());
            }, 800);
        }

        setTimeout(check, 100);
        setInterval(check, CHECK_INTERVAL);
    }

    let checkTimeout = null;
    function requestCheck() {
        if (checkTimeout) clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
            check();
            checkTimeout = null;
        }, 150);
    }

    return { init, checkNow: requestCheck, syncSettings: syncSettingsWithBackend };
})();

const NotifHistory = (function () {
    const STORAGE_KEY = 'notif_history_by_room';
    let panel = null, overlay = null;

    const getCurrentRoom = () => {
        const urlRoom = new URLSearchParams(location.search).get('room');
        if (urlRoom) return urlRoom;
        const cookieMatch = document.cookie.match(/room_id=(\d+)/);
        return cookieMatch ? cookieMatch[1] : 'global';
    };

    const getAllHistory = () => {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; }
        catch { return {}; }
    };
    const getHistory = () => {
        const room = getCurrentRoom();
        const all = getAllHistory();
        return all[room] || [];
    };

    const saveHistory = h => {
        const room = getCurrentRoom();
        const all = getAllHistory();
        all[room] = h.slice(0, 500);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    };

    const clearCurrentRoomHistory = () => {
        const room = getCurrentRoom();
        const all = getAllHistory();
        delete all[room];
        localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    };

    function addToHistory(a) {
        const h = getHistory();
        h.unshift({ ...a, timestamp: Date.now() });
        saveHistory(h);
        updateBadge();
        if (panel?.classList.contains('active')) {
            render();
        }
    }

    function removeFromHistory(i) {
        const h = getHistory();
        h.splice(i, 1);
        saveHistory(h);
        updateBadge();
    }


    function updateBadge() {
        const btns = document.querySelectorAll('.action-btn[aria-label="Notifications"]');
        const count = getHistory().length;
        const isDnd = getDndState();

        btns.forEach(btn => {
            let badge = btn.querySelector('.notif-badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notif-badge';
                    btn.style.position = 'relative';
                    btn.appendChild(badge);
                }
                badge.textContent = count > 9 ? '9+' : count;
            } else {
                badge?.remove();
            }

            const iconImg = btn.querySelector('img.icon') || btn.querySelector('img');
            if (iconImg) {
                const iconPath = isDnd ? 'assets/img/icons/bell-off.svg' : 'assets/img/icons/bell.svg';
                iconImg.setAttribute('src', iconPath);
            }
        });
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
        updateBadge();
        DashMedGlobalAlerts.syncSettings({ alert_dnd: enabled ? 1 : 0 });
    }

    function syncProfileToggle(enabled) {
        const profileToggle = document.getElementById('dnd-dev-toggle');
        if (profileToggle) profileToggle.checked = enabled === true || enabled === 1 || enabled === 'true';
    }

    function syncPanelUI(settings) {
        if (!panel) return;
        if (settings.alert_volume !== undefined) {
            const volSlider = panel.querySelector('.notif-volume-slider');
            const volBtn = panel.querySelector('.notif-volume-btn');
            if (volSlider && volBtn) {
                const val = parseFloat(settings.alert_volume) * 100;
                volSlider.value = val;

                if (val === 0) volBtn.innerHTML = VOL_MUTE;
                else if (val <= 50) volBtn.innerHTML = VOL_LOW;
                else volBtn.innerHTML = VOL_HIGH;
            }
        }
        if (settings.alert_duration !== undefined) {
            const timeOptions = panel.querySelectorAll('.notif-time-option');
            timeOptions.forEach(opt => {
                opt.classList.toggle('active', opt.dataset.time === settings.alert_duration.toString());
            });
        }
        if (settings.alert_dnd !== undefined) {
            const isOn = settings.alert_dnd === true || settings.alert_dnd === 1 || settings.alert_dnd === 'true';
            const dndToggle = panel.querySelector('#notif-panel-dnd');
            if (dndToggle) dndToggle.checked = isOn;
        }
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
                            ${currentVol === 0 ? VOL_MUTE : (currentVol <= 50 ? VOL_LOW : VOL_HIGH)}
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

        const notifDnd = panel.querySelector('#notif-panel-dnd');
        notifDnd.checked = getDndState();
        notifDnd.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            setDndState(isChecked);
        });

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
            if (val === 0) volBtn.innerHTML = VOL_MUTE;
            else if (val <= 50) volBtn.innerHTML = VOL_LOW;
            else volBtn.innerHTML = VOL_HIGH;
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
            const floatVal = val / 100;
            localStorage.setItem('dashmed_notif_volume', floatVal.toString());
            updateVolumeIcon(val);
        });

        volSlider.addEventListener('change', (e) => {
            const floatVal = parseInt(e.target.value, 10) / 100;
            DashMedGlobalAlerts.syncSettings({ alert_volume: floatVal });
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
                DashMedGlobalAlerts.syncSettings({ alert_duration: parseInt(val, 10) });
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
        if (dndToggle) dndToggle.checked = getDndState();
        document.body.appendChild(overlay);
        document.body.appendChild(panel);
    }

    let currentIndex = 0;
    const itemsPerPage = 20;
    let observer = null;
    let isDelegated = false;

    function render(append = false) {
        if (!panel) createPanel();
        const body = panel.querySelector('.notif-panel-body'),
            footer = panel.querySelector('.notif-panel-footer'),
            h = getHistory();

        if (!append) {
            currentIndex = 0;
            body.innerHTML = '';
            if (observer) {
                observer.disconnect();
                observer = null;
            }
        }

        const toShow = h.slice(currentIndex, currentIndex + itemsPerPage);

        footer.style.display = h.length ? '' : 'none';
        
        if (!append && !h.length) {
            body.innerHTML = '<div class="notif-panel-empty">Aucune notification</div>';
            return;
        }

        const html = toShow.map((n, i) => {
            const realIdx = currentIndex + i;
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
                        let tStr = n.timestamp.replace(' ', 'T');
                        if (!tStr.endsWith('Z')) tStr += 'Z';
                        t = new Date(tStr);
                    } else {
                        t = new Date(n.timestamp);
                    }
                    if (isNaN(t.getTime())) t = new Date();
                }
                const heures = String(t.getHours()).padStart(2, '0');
                const minutes = String(t.getMinutes()).padStart(2, '0');
                messageStr = `DEPASSEMENT DE ${param.toUpperCase()} À ${heures}H${minutes}.`;
            }

            return `<div class="notif-item ${type} ${hasCard ? 'notif-item--clickable' : ''}" data-idx="${realIdx}" ${hasCard ? `data-param-id="${n.parameterId}"` : ''}>
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

        if (append) {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            while(temp.firstChild) {
                body.appendChild(temp.firstChild);
            }
        } else {
            body.innerHTML = html;
        }

        if (!isDelegated) {
            body.addEventListener('click', (e) => {
                const deleteBtn = e.target.closest('.notif-item-delete');
                if (deleteBtn) {
                    e.stopPropagation();
                    const item = deleteBtn.closest('.notif-item');
                    item.classList.add('removing');
                    setTimeout(() => {
                        const idxToRemove = +item.dataset.idx;
                        removeFromHistory(idxToRemove);
                        
                        item.remove();
                        currentIndex = Math.max(0, currentIndex - 1);

                        const items = body.querySelectorAll('.notif-item[data-idx]');
                        items.forEach(el => {
                            const curIdx = +el.dataset.idx;
                            if (curIdx > idxToRemove) {
                                el.dataset.idx = curIdx - 1;
                            }
                        });

                        const h = getHistory();
                        if (h.length === 0) {
                            body.innerHTML = '<div class="notif-panel-empty">Aucune notification</div>';
                            if (footer) footer.style.display = 'none';
                        }
                    }, 250);
                    return;
                }
                
                const clickableItem = e.target.closest('.notif-item--clickable');
                if (clickableItem) {
                    scrollToCard(clickableItem.dataset.paramId);
                    close();
                }
            });
            isDelegated = true;
        }

        currentIndex += itemsPerPage;

        if (observer) {
            observer.disconnect();
        }

        if (currentIndex < h.length) {
            const skeletonLoader = document.createElement('div');
            skeletonLoader.className = 'notif-skeleton-loader';
            skeletonLoader.style.display = 'flex';
            skeletonLoader.style.flexDirection = 'column';
            skeletonLoader.style.gap = '8px';
            skeletonLoader.style.padding = '12px 16px';
            
            skeletonLoader.innerHTML = Array(3).fill(`
                <div class="notif-item" style="border: none; box-shadow: none; display: flex; align-items: flex-start; gap: 12px; padding: 12px 0;">
                    <div class="notif-item-content" style="flex: 1; display: flex; flex-direction: column; gap: 8px;">
                        <div class="notif-item-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="skeleton skeleton-text skeleton-text--lg" style="margin: 0; width: 60%;"></div>
                            <div class="skeleton skeleton-text skeleton-text--sm" style="margin: 0; width: 40px;"></div>
                        </div>
                        <div class="skeleton skeleton-text" style="width: 80%;"></div>
                    </div>
                </div>
            `).join('');

            body.appendChild(skeletonLoader);

            observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    observer.disconnect();
                    setTimeout(() => {
                        skeletonLoader.remove();
                        render(true);
                    }, 800);
                }
            }, {
                root: body,
                threshold: 0.1
            });
            observer.observe(skeletonLoader);
        }
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
        const btns = document.querySelectorAll('.action-btn[aria-label="Notifications"]');
        btns.forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                open();
            });
        });

        window.addEventListener('storage', (e) => {
            if (e.key === 'dashmed_dnd') {
                updateBadge();
                const dndToggle = panel?.querySelector('#notif-panel-dnd');
                if (dndToggle) dndToggle.checked = (e.newValue === 'true');

                const profileToggle = document.getElementById('dnd-dev-toggle');
                if (profileToggle) profileToggle.checked = (e.newValue === 'true');
            }
        });
        updateBadge();

        const profileToggle = document.getElementById('dnd-dev-toggle');
        if (profileToggle) {
            profileToggle.addEventListener('change', e => setDndState(e.target.checked));
        }
    }

    return { init, add: addToHistory, syncDnd: syncProfileToggle, syncUI: syncPanelUI };
})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', DashMedGlobalAlerts.init);
    document.addEventListener('DOMContentLoaded', NotifHistory.init);
} else {
    DashMedGlobalAlerts.init();
    NotifHistory.init();
}