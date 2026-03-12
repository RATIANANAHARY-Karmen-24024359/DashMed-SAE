(function () {
    const aside = document.getElementById('aside');
    const showAsideBtn = document.getElementById('aside-show-btn');

    window.toggleAside = function () {
        if (aside) {
            aside.classList.toggle('active-aside');
        }
    }

    const restoreBtn = document.getElementById('aside-restore-btn');
    const body = document.body;

    window.toggleDesktopAside = function () {
        const isCollapsed = body.classList.toggle('aside-collapsed');
        localStorage.setItem('asideCollapsed', isCollapsed);
    }

    const savedAsideState = localStorage.getItem('asideCollapsed');
    if (savedAsideState === 'true') {
        body.classList.add('aside-collapsed');
    }

    window.applyTheme = function (theme) {
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

    document.addEventListener('DOMContentLoaded', () => { });

    // Safari can suspend background tabs and sometimes leave the page in a blank/render-broken state.
    // When we come back to the tab, ensure the dashboard is still present; otherwise recover.
    function dashmedIsDashboardRendered() {
        const main = document.querySelector('main');
        if (!main) return false;
        return !!document.querySelector('article.card');
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return;

        try {
            if (window.DashMedStream && typeof window.DashMedStream.reconnect === 'function') {
                window.DashMedStream.reconnect();
            }
        } catch (_) {
        }
        setTimeout(() => {
            if (!dashmedIsDashboardRendered()) {
                console.warn('[DashMed] Dashboard looks blank after tab restore; reloading page.');
                window.location.reload();
            }
        }, 500);
    });

})();
