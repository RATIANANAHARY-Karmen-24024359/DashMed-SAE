const aside = document.getElementById('aside');
const showAsideBtn = document.getElementById('aside-show-btn');


function toggleAside() {
    if (aside) {
        aside.classList.toggle('active-aside');
    }
}

const restoreBtn = document.getElementById('aside-restore-btn');
const body = document.body;

function toggleDesktopAside() {
    const isCollapsed = body.classList.toggle('aside-collapsed');
    localStorage.setItem('asideCollapsed', isCollapsed);
}

const savedAsideState = localStorage.getItem('asideCollapsed');
if (savedAsideState === 'true') {
    body.classList.add('aside-collapsed');
}


function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
}

const savedTheme = localStorage.getItem('theme') || 'light';
applyTheme(savedTheme);


window.addEventListener('storage', (e) => {
    if (e.key === 'theme') {
        applyTheme(e.newValue);
    }
});

// Safari can suspend background tabs and sometimes leave the page in a blank/render-broken state.
// When we come back to the tab, ensure the dashboard is still present; otherwise recover.
function dashmedIsDashboardRendered() {
    const main = document.querySelector('main');
    if (!main) return false;
    return !!document.querySelector('article.card');
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') return;

    // Force SSE reconnect (defensive)
    try {
        if (window.DashMedStream && typeof window.DashMedStream.reconnect === 'function') {
            window.DashMedStream.reconnect();
        }
    } catch (_) {
        // ignore
    }

    // If the dashboard is blank after returning, do a controlled reload.
    setTimeout(() => {
        if (!dashmedIsDashboardRendered()) {
            console.warn('[DashMed] Dashboard looks blank after tab restore; reloading page.');
            window.location.reload();
        }
    }, 500);
});
