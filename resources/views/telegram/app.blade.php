<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Performix</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <style>
        /*
         * Palette matches the web Performix mini-app (resources/views/
         * mini-app/index.blade.php) -- warm cream/gold/brown instead of the
         * original navy/blue, so the Telegram Mini App and the web version
         * read as the same product. --accent/--accent2/--bg stay runtime-
         * overridable (see applyTheme() below, unchanged) since a signed-in
         * employee's own Account Settings > Appearance colours still win;
         * these are just the pre-fetch/no-custom-theme defaults.
         *
         * [data-theme] is set by the colour-scheme sync right after `tg` is
         * defined below, from Telegram's own colorScheme (falling back to
         * the OS's prefers-color-scheme outside Telegram) -- "appearance
         * follows system" without a manual toggle anywhere in the UI.
         */
        :root {
            --navy: #6B3F2A;
            --accent: #D4AF37;
            --accent2: #6B9080;
            --accent-soft: #FBF0D1;
            --bg: #F5F5F3;
            --card-bg: #FFFCF4;
            --card-border: #D9C4A0;
            --input-bg: #FFFFFF;
            --track-bg: #EFE3C7;
            --text-strong: #241B12;
            --text-secondary: #6B5D4A;
            --text-muted: #9C8E77;
        }
        :root[data-theme="dark"] {
            --bg: #1C1712;
            --card-bg: #241D15;
            --card-border: #4A3B28;
            --input-bg: #2A2318;
            --track-bg: #3A2F20;
            --text-strong: #F3EAD9;
            --text-secondary: #C9BBA3;
            --text-muted: #8A7D68;
        }
        body { font-family: 'Inter', sans-serif; }
        .tap-card { transition: border-color .15s, background .15s; }
        .sticky-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
        .nav-tab { transition: color .15s; }
        input[type="range"] { height: 6px; border-radius: 999px; background: var(--track-bg); }
    </style>
</head>
<body class="bg-[var(--bg)] min-h-screen text-[var(--text-strong)]">

<div class="max-w-md mx-auto min-h-screen flex flex-col">
    <div id="topbar" class="hidden bg-[var(--accent2)] text-white px-4 py-3.5 flex items-center gap-3 shrink-0">
        <button id="backBtn" onclick="goBack()" class="hidden text-white/80 text-lg leading-none">←</button>
        <h1 id="topbarTitle" class="text-[15px] font-black">Performix</h1>
    </div>

    <div id="toast" class="hidden mx-4 mt-3 px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-semibold"></div>

    <div id="app" class="flex-1 p-4 space-y-3 overflow-y-auto">
        <p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>
    </div>

    <div id="bottomNav" class="hidden shrink-0 bg-[var(--card-bg)] border-t-2 border-[var(--card-border)] grid grid-cols-4 sticky-bottom">
        <button data-tab="home" onclick="switchRootTab('home')" class="nav-tab flex flex-col items-center gap-0.5 py-2.5 text-[var(--accent)]">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 0 1 2.122 0l8.954 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
            </svg>
            <span class="text-[10px] font-bold">Home</span>
        </button>
        <button data-tab="tasks" onclick="switchRootTab('tasks')" class="nav-tab flex flex-col items-center gap-0.5 py-2.5 text-[var(--text-muted)]">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-8 4h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2.28a2 2 0 0 0-1.4.58l-.32.32a2 2 0 0 1-1.4.6H9.4a2 2 0 0 1-1.4-.6l-.32-.32A2 2 0 0 0 6.28 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>
            </svg>
            <span class="text-[10px] font-bold">Tasks</span>
        </button>
        <button data-tab="kpi" onclick="switchRootTab('kpi')" class="nav-tab flex flex-col items-center gap-0.5 py-2.5 text-[var(--text-muted)]">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.48 3.5 2.4 4.87 5.37.78-3.88 3.79.91 5.35-4.8-2.53-4.8 2.53.91-5.35-3.88-3.79 5.37-.78 2.4-4.87Z"/>
            </svg>
            <span class="text-[10px] font-bold">KPI</span>
        </button>
        <button data-tab="profile" onclick="switchRootTab('profile')" class="nav-tab flex flex-col items-center gap-0.5 py-2.5 text-[var(--text-muted)]">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.98 18.73A7.49 7.49 0 0 0 12 15.75a7.49 7.49 0 0 0-5.98 2.98m11.96 0a9 9 0 1 0-11.96 0m11.96 0A8.97 8.97 0 0 1 12 21a8.97 8.97 0 0 1-5.98-2.27M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
            </svg>
            <span class="text-[10px] font-bold">Profile</span>
        </button>
    </div>
</div>

<script>
    const tg = window.Telegram?.WebApp;
    tg?.ready();
    tg?.expand();
    // Telegram's own hardware/native back gesture is otherwise unwired and
    // would just close the whole Mini App from any screen — route it through
    // the same goBack() our in-app arrow uses instead.
    tg?.BackButton?.onClick(() => goBack());

    // "Appearance follows system" — Telegram exposes its own light/dark
    // colorScheme (which itself follows the device's system theme unless the
    // user picked a specific Telegram theme), so mirror it onto the page via
    // [data-theme] rather than adding a manual toggle anywhere in this UI.
    // Falls back to the OS's own prefers-color-scheme when opened outside
    // Telegram (e.g. testing this page directly in a browser).
    function applyColorScheme() {
        const scheme = tg?.colorScheme
            || (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', scheme);
    }
    applyColorScheme();
    tg?.onEvent?.('themeChanged', applyColorScheme);

    const BOT_USERNAME = '{{ $botUsername }}';
    const initData = tg?.initData || '';
    const params = new URLSearchParams(window.location.search);
    const deepLinkScreen = params.get('screen') || 'home';

    const state = {
        employeeId: sessionStorage.getItem('tg_employee_id') || null,
        companyCode: sessionStorage.getItem('tg_company_code') || null,
        employeeName: sessionStorage.getItem('tg_employee_name') || '',
    };

    function showToast(message) {
        const t = document.getElementById('toast');
        t.textContent = message;
        t.classList.remove('hidden');
        setTimeout(() => t.classList.add('hidden'), 4000);
    }

    async function api(path, opts = {}) {
        const res = await fetch('/api/telegram' + path, {
            ...opts,
            headers: {
                'Content-Type': 'application/json',
                'X-Telegram-Init-Data': initData,
                ...(opts.headers || {}),
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const err = new Error(data.message || 'Request failed');
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    function formatUnit(value, unit) {
        const n = Number(value || 0);
        if (unit === 'currency') return 'RM ' + n.toLocaleString(undefined, { maximumFractionDigits: 0 });
        if (unit === 'percentage') return n.toLocaleString(undefined, { maximumFractionDigits: 2 }) + '%';
        return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    function formatDateTime(iso) {
        return new Date(iso).toLocaleString('en-MY', {
            timeZone: 'Asia/Kuala_Lumpur', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
        });
    }

    function todayStr() {
        return new Date().toISOString().slice(0, 10);
    }

    function initials(name) {
        return (name || '?').trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    // Same category order/colors used on the web dashboard, so the Mini App
    // matches the system rather than inventing its own palette.
    const CATEGORY_ORDER = ['Financial', 'Growth & Customer', 'Initiatives', 'People'];

    const CATEGORY_COLORS = {
        'Financial':         { catPill: 'bg-emerald-700 text-white', subPill: 'bg-emerald-100 text-emerald-700' },
        'Growth & Customer': { catPill: 'bg-indigo-700 text-white',  subPill: 'bg-indigo-100 text-indigo-700' },
        'Initiatives':       { catPill: 'bg-amber-600 text-white',   subPill: 'bg-amber-100 text-amber-700' },
        'People':            { catPill: 'bg-pink-700 text-white',    subPill: 'bg-pink-100 text-pink-700' },
    };
    const DEFAULT_CATEGORY_COLOR = { catPill: 'bg-slate-600 text-white', subPill: 'bg-[var(--track-bg)] text-[var(--text-secondary)]' };

    function sortByCategoryAndSub(items) {
        return [...items].sort((a, b) => {
            const ai = CATEGORY_ORDER.indexOf(a.category); const bi = CATEGORY_ORDER.indexOf(b.category);
            const catDiff = (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
            if (catDiff !== 0) return catDiff;
            return (a.sub_category || '').localeCompare(b.sub_category || '');
        });
    }

    const STATUS_LABELS = {
        completed:   { label: 'Completed',   color: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
        on_track:    { label: 'On Track',    color: 'bg-blue-100 text-blue-700',       dot: 'bg-blue-600' },
        at_risk:     { label: 'At Risk',     color: 'bg-amber-100 text-amber-700',     dot: 'bg-amber-500' },
        in_trouble:  { label: 'In Trouble',  color: 'bg-red-100 text-red-700',         dot: 'bg-red-500' },
        not_started: { label: 'Not Started', color: 'bg-[var(--track-bg)] text-[var(--text-secondary)]',     dot: 'bg-slate-400' },
    };

    const TASK_STATUS_PILL = {
        not_started: { label: 'Not Started', color: 'bg-[var(--track-bg)] text-[var(--text-secondary)]' },
        in_progress: { label: 'In Progress', color: 'bg-amber-100 text-amber-700' },
        done:        { label: 'Done',        color: 'bg-emerald-100 text-emerald-700' },
        blocked:     { label: 'Blocked',     color: 'bg-red-100 text-red-700' },
        cancelled:   { label: 'Cancelled',   color: 'bg-[var(--track-bg)] text-[var(--text-muted)]' },
    };
    const PRIORITY_LABELS = {
        low:      { label: 'Low',      color: 'bg-[var(--track-bg)] text-[var(--text-secondary)]' },
        medium:   { label: 'Medium',   color: 'bg-[var(--accent-soft)] text-blue-700' },
        high:     { label: 'High',     color: 'bg-amber-100 text-amber-700' },
        critical: { label: 'Critical', color: 'bg-red-100 text-red-700' },
    };

    function dueDateBadge(dueDate) {
        if (!dueDate) return '';
        const isOverdue = dueDate < todayStr();
        return `<span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${isOverdue ? 'bg-red-100 text-red-700' : 'bg-[var(--track-bg)] text-[var(--text-secondary)]'}">${isOverdue ? '⚠ ' : ''}Due ${dueDate}</span>`;
    }

    function achvBadge(score) {
        if (score >= 90) return { label: 'Excellent', color: 'bg-emerald-100 text-emerald-700', bar: 'from-emerald-400 to-emerald-500', ring: '#10B981' };
        if (score >= 75) return { label: 'Good',      color: 'bg-blue-100 text-blue-700',        bar: 'from-blue-400 to-blue-500',       ring: '#2563EB' };
        if (score >= 50) return { label: 'Watch',     color: 'bg-amber-100 text-amber-700',      bar: 'from-amber-400 to-amber-500',     ring: '#F59E0B' };
        return              { label: 'Critical', color: 'bg-red-100 text-red-700',          bar: 'from-red-400 to-rose-500',        ring: '#EF4444' };
    }

    // A circular "at a glance" ring of a KPI's achievement score.
    function progressRing(scoreRaw) {
        const badge = achvBadge(scoreRaw);
        const score = Math.max(0, Math.min(100, scoreRaw));
        const r = 24, c = 2 * Math.PI * r;
        const offset = c - (score / 100) * c;
        return `
            <svg width="60" height="60" viewBox="0 0 60 60" class="shrink-0">
                <circle cx="30" cy="30" r="${r}" fill="none" stroke="var(--track-bg)" stroke-width="6"/>
                <circle cx="30" cy="30" r="${r}" fill="none" stroke="${badge.ring}" stroke-width="6"
                    stroke-linecap="round" stroke-dasharray="${c}" stroke-dashoffset="${offset}"
                    transform="rotate(-90 30 30)"/>
                <text x="30" y="35" text-anchor="middle" font-size="13" font-weight="900" fill="var(--text-strong)">${Math.round(scoreRaw)}%</text>
            </svg>
        `;
    }

    /* ---------------------------------------------------------------- */
    /* CHROME — a persistent bottom tab bar (Home/Tasks/KPI/Profile) for  */
    /* the app's 4 root screens, and a navy back-button header for every  */
    /* screen reached by drilling into one of them. currentRootTab is     */
    /* only ever updated by showRootChrome(), so goBack() always lands    */
    /* back on whichever root tab the user actually came from.            */
    /* ---------------------------------------------------------------- */

    let currentRootTab = 'home';
    // Whether the screen on screen right now is one of the 4 bottom-nav
    // roots (true) or something drilled into from one of them (false) —
    // goBack() uses this to decide whether "back" means "go to Home" or
    // "go to whichever root tab I drilled in from".
    let isRootScreen = true;

    const ROOT_TAB_TITLES = { tasks: 'Tasks', kpi: 'My KPIs', profile: 'Profile' };

    // Toggles both the in-app "←" arrow and Telegram's own native/hardware
    // back button together, so neither one is ever left out of sync with
    // the other and the native back gesture never silently closes the app.
    function setBackVisible(visible) {
        document.getElementById('backBtn').classList.toggle('hidden', !visible);
        if (visible) {
            tg?.BackButton?.show();
        } else {
            tg?.BackButton?.hide();
        }
    }

    function showRootChrome(tab) {
        currentRootTab = tab;
        isRootScreen = true;
        document.getElementById('bottomNav').classList.remove('hidden');
        document.querySelectorAll('.nav-tab').forEach(btn => {
            const active = btn.dataset.tab === tab;
            btn.classList.toggle('text-[var(--accent)]', active);
            btn.classList.toggle('text-[var(--text-muted)]', !active);
        });

        if (tab === 'home') {
            document.getElementById('topbar').classList.add('hidden');
            setBackVisible(false);
        } else {
            document.getElementById('topbar').classList.remove('hidden');
            document.getElementById('topbarTitle').textContent = ROOT_TAB_TITLES[tab] || 'Performix';
            setBackVisible(true);
        }
    }

    function showSubScreenChrome(title) {
        isRootScreen = false;
        document.getElementById('bottomNav').classList.add('hidden');
        document.getElementById('topbar').classList.remove('hidden');
        setBackVisible(true);
        document.getElementById('topbarTitle').textContent = title;
    }

    function showBootChrome(title) {
        isRootScreen = false;
        document.getElementById('bottomNav').classList.add('hidden');
        document.getElementById('topbar').classList.remove('hidden');
        setBackVisible(false);
        document.getElementById('topbarTitle').textContent = title;
    }

    function switchRootTab(tab) {
        if (tab === 'home') renderHome();
        if (tab === 'tasks') renderTasksTab();
        if (tab === 'kpi') renderMyKpis();
        if (tab === 'profile') renderProfile();
    }

    function goBack() {
        if (isRootScreen && currentRootTab !== 'home') {
            switchRootTab('home');
        } else {
            switchRootTab(currentRootTab);
        }
    }

    function card(inner, extraClasses = '') {
        return `<div class="bg-[var(--card-bg)] rounded-2xl border-2 border-[var(--card-border)] p-4 ${extraClasses}">${inner}</div>`;
    }

    /* ---------------------------------------------------------------- */
    /* BOOT                                                              */
    /* ---------------------------------------------------------------- */

    async function boot() {
        let status;
        try {
            status = await api('/link/status');
        } catch (e) {
            if (e.status === 401) {
                renderNotInTelegram();
            } else {
                renderError('Something went wrong. Pull to refresh and try again.');
            }
            return;
        }

        if (!status.linked) {
            renderNotLinked();
            return;
        }

        const dashboards = status.dashboards || [];
        window.__dashboards = dashboards;

        if (dashboards.length === 0) {
            renderError('No active dashboard found for your account. Please contact your admin.');
            return;
        }

        if (dashboards.length === 1 || (state.employeeId && dashboards.some(d => d.employee_id === state.employeeId))) {
            if (!state.employeeId) selectDashboard(dashboards[0], false);
            routeToScreen();
            return;
        }

        renderChooseDashboard(dashboards);
    }

    function selectDashboard(d, thenRoute = true) {
        state.employeeId = d.employee_id;
        state.companyCode = d.company_code;
        state.employeeName = d.short_name || '';
        sessionStorage.setItem('tg_employee_id', d.employee_id);
        sessionStorage.setItem('tg_company_code', d.company_code);
        sessionStorage.setItem('tg_employee_name', d.short_name || '');
        if (thenRoute) routeToScreen();
    }

    function routeToScreen() {
        applyTheme();
        if (deepLinkScreen === 'tasks-today') { renderCreateTask(); return; }
        if (deepLinkScreen === 'tasks-update') { switchRootTab('tasks'); return; }
        if (deepLinkScreen === 'review') { switchRootTab('profile'); renderPerformanceReview('weekly'); return; }
        switchRootTab('home');
    }

    // Mirrors the employee's Account Settings > Appearance colours here too,
    // via CSS custom properties every themed element already reads from —
    // falls back silently to the default palette if nothing was ever
    // customised or the fetch fails.
    //
    // A custom theme_bg is always a LIGHT colour (Account Settings, and the
    // web mini-app it's shared with, only ever offer light backgrounds to
    // pick from) — but it's applied as an inline style, which beats any CSS
    // rule including the [data-theme="dark"] block below. Left alone, a dark
    // system colorScheme would still swap --card-bg/--text-strong/etc to
    // their dark values while --bg stayed pinned to the light custom color
    // via that inline style, producing a broken half-dark/half-light mix
    // (pale text on a light background, dark cards floating on it). Forcing
    // data-theme to "light" whenever a custom background is set keeps the
    // whole palette consistent with the choice the employee actually made.
    let _themeApplied = false;
    async function applyTheme() {
        if (_themeApplied) return;
        _themeApplied = true;
        try {
            const t = await api('/theme?employee_id=' + encodeURIComponent(state.employeeId) + '&company_code=' + encodeURIComponent(state.companyCode));
            if (t.theme_accent) document.documentElement.style.setProperty('--accent', t.theme_accent);
            if (t.theme_accent2) document.documentElement.style.setProperty('--accent2', t.theme_accent2);
            if (t.theme_bg) {
                document.documentElement.style.setProperty('--bg', t.theme_bg);
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {
            // Not linked in this context, or a transient error — keep defaults.
        }
    }

    /* ---------------------------------------------------------------- */
    /* BOOT-TIME SCREENS                                                 */
    /* ---------------------------------------------------------------- */

    function renderError(message) {
        showBootChrome('Performix');
        document.getElementById('app').innerHTML = card(`
            <p class="text-[13px] text-[var(--text-secondary)] text-center py-6">${message}</p>
        `);
    }

    function renderNotLinked() {
        showBootChrome('Not Connected');
        document.getElementById('app').innerHTML = card(`
            <p class="text-[13px] font-black text-[var(--text-strong)] mb-1">Not connected yet</p>
            <p class="text-[12px] text-[var(--text-secondary)] leading-relaxed">
                Open the KPI Dashboard on the web, go to <b>My Profile</b>, and tap
                <b>Connect Telegram</b> to link this account.
            </p>
        `);
    }

    function renderNotInTelegram() {
        showBootChrome('Performix');
        const botLine = BOT_USERNAME ? ` Open <b>@${BOT_USERNAME}</b> in Telegram and` : ' Open the bot in Telegram and';
        document.getElementById('app').innerHTML = card(`
            <p class="text-[13px] font-black text-[var(--text-strong)] mb-1">Open this from Telegram</p>
            <p class="text-[12px] text-[var(--text-secondary)] leading-relaxed">
                This page only works when opened inside the Telegram app.${botLine}
                tap the Performix button there.
            </p>
        `);
    }

    function renderChooseDashboard(dashboards) {
        showBootChrome('Choose Dashboard');
        const rows = dashboards.map(d => `
            <button onclick='pickDashboard(${JSON.stringify(d)})' class="w-full text-left tap-card">
                ${card(`
                    <p class="text-[13px] font-black text-[var(--text-strong)]">${d.company_display_name}</p>
                    <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">${d.short_name} · <span class="uppercase font-semibold">${d.role || ''}</span></p>
                `, 'hover:border-[var(--accent)]')}
            </button>
        `).join('');
        document.getElementById('app').innerHTML = `<div class="space-y-2">${rows}</div>`;
    }

    function pickDashboard(d) {
        selectDashboard(d, true);
    }

    /**
     * Re-lets someone with more than one company/employee dashboard (e.g.
     * they work at two companies under this same Telegram account) jump
     * between them after the initial boot-time pick, from Profile — same
     * dashboards list renderChooseDashboard() uses, refetched so a role
     * change since boot is picked up too.
     */
    async function renderSwitchCompany() {
        showSubScreenChrome('Switch Company');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        let status;
        try {
            status = await api('/link/status');
        } catch (e) {
            app.innerHTML = card(`
                <p class="text-[13px] text-[var(--text-secondary)] text-center py-4">Could not load your companies.${e.status ? ` (${e.status})` : ''}</p>
                <button onclick="renderSwitchCompany()" class="w-full text-center text-[12px] font-bold text-[var(--accent)] py-2">Try again</button>
            `);
            return;
        }

        const dashboards = status.dashboards || [];
        window.__dashboards = dashboards;

        if (dashboards.length <= 1) {
            app.innerHTML = card(`<p class="text-[13px] text-[var(--text-secondary)] text-center py-6">You only have one company linked.</p>`);
            return;
        }

        app.innerHTML = `<div class="space-y-2">${dashboards.map(d => {
            const isCurrent = d.employee_id === state.employeeId && d.company_code === state.companyCode;
            return `
                <button onclick='pickSwitchedDashboard(${JSON.stringify(d)})' class="w-full text-left tap-card">
                    ${card(`
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[13px] font-black text-[var(--text-strong)]">${d.company_display_name}</p>
                                <p class="text-[11px] text-[var(--text-secondary)] mt-0.5">${d.short_name} · <span class="uppercase font-semibold">${d.role || ''}</span></p>
                            </div>
                            ${isCurrent ? '<span class="text-[9px] font-black text-emerald-700 px-2 py-0.5 rounded-full bg-emerald-50 shrink-0">Current</span>' : ''}
                        </div>
                    `, isCurrent ? 'border-emerald-300' : 'hover:border-[var(--accent)]')}
                </button>
            `;
        }).join('')}</div>`;
    }

    function pickSwitchedDashboard(d) {
        if (d.employee_id === state.employeeId && d.company_code === state.companyCode) {
            switchRootTab('home');
            return;
        }
        selectDashboard(d, false);
        _themeApplied = false;
        switchRootTab('home');
        applyTheme();
    }

    async function confirmDisconnect() {
        const doDisconnect = () => api('/link/disconnect', { method: 'POST' }).then(() => tg?.close());
        if (tg?.showConfirm) {
            tg.showConfirm('Disconnect Telegram from your KPI account?', (ok) => { if (ok) doDisconnect(); });
        } else if (confirm('Disconnect Telegram from your KPI account?')) {
            doDisconnect();
        }
    }

    /* ================================================================ */
    /* HOME                                                                */
    /* ================================================================ */

    function greetingWord() {
        const h = new Date().getHours();
        if (h < 12) return 'Good morning';
        if (h < 18) return 'Good afternoon';
        return 'Good evening';
    }

    function buildDailyInsight(tasks) {
        const today = todayStr();
        const open = tasks.filter(t => !['done', 'cancelled'].includes(t.status));
        const overdue = open.filter(t => t.due_date && t.due_date < today);
        const dueToday = open.filter(t => t.due_date === today);

        if (overdue.length) return `You have ${overdue.length} overdue task${overdue.length > 1 ? 's' : ''}. "${overdue[0].title}" needs attention first.`;
        if (dueToday.length) return `You're on track. Complete "${dueToday[0].title}" today.`;
        if (open.length) return `Nothing due today — good time to get ahead on "${open[0].title}".`;
        return `All caught up! Tap "Create Task" to plan your next move.`;
    }

    function homeProgressRing(pct) {
        const r = 38, c = 2 * Math.PI * r;
        const offset = c - (Math.max(0, Math.min(100, pct)) / 100) * c;
        return `
            <svg width="92" height="92" viewBox="0 0 92 92" class="shrink-0">
                <circle cx="46" cy="46" r="${r}" fill="none" stroke="var(--track-bg)" stroke-width="8"/>
                <circle cx="46" cy="46" r="${r}" fill="none" stroke="#10B981" stroke-width="8" stroke-linecap="round"
                    stroke-dasharray="${c}" stroke-dashoffset="${offset}" transform="rotate(-90 46 46)"/>
                <text x="46" y="52" text-anchor="middle" font-size="20" font-weight="900" fill="var(--text-strong)">${Math.round(pct)}%</text>
            </svg>
        `;
    }

    function statTile(icon, value, label) {
        return `
            <div class="bg-[var(--card-bg)] rounded-2xl border-2 border-[var(--card-border)] p-3 text-center flex-1">
                <div class="w-8 h-8 rounded-full bg-[var(--accent-soft)] text-[var(--accent)] flex items-center justify-center mx-auto text-[14px]">${icon}</div>
                <p class="text-[19px] font-black text-[var(--text-strong)] leading-none mt-2">${value}</p>
                <p class="text-[8px] text-[var(--text-muted)] font-bold uppercase tracking-wide mt-1">${label}</p>
            </div>
        `;
    }

    async function renderHome() {
        showRootChrome('home');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        let tasksData, scoreData;
        try {
            [tasksData, scoreData] = await Promise.all([
                api(`/project-tasks?employee_id=${state.employeeId}&company_code=${state.companyCode}`),
                api(`/tasks/score?employee_id=${state.employeeId}&company_code=${state.companyCode}&period=weekly`),
            ]);
        } catch (e) {
            app.innerHTML = card(`<p class="text-[13px] text-[var(--text-secondary)] text-center py-6">Could not load your dashboard.</p>`);
            return;
        }

        window.__myTasks = tasksData.tasks || [];
        const tasks = window.__myTasks;
        const today = todayStr();

        const activeTasks = tasks.filter(t => !['done', 'cancelled'].includes(t.status));
        const dueToday = tasks.filter(t => t.due_date === today && !['done', 'cancelled'].includes(t.status));
        const todaysSet = tasks.filter(t => t.due_date === today || t.start_date === today);
        const dailyProgressPct = todaysSet.length
            ? Math.round(todaysSet.filter(t => t.status === 'done').length / todaysSet.length * 100)
            : (tasks.length ? Math.round(tasks.filter(t => t.status === 'done').length / tasks.length * 100) : 0);

        const dateLabel = new Date().toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });

        app.innerHTML = `
            <div>
                <p class="text-[18px] font-black text-[var(--text-strong)]">${greetingWord()}, ${state.employeeName}</p>
                <p class="text-[12px] text-[var(--text-muted)] mt-0.5">${dateLabel}</p>
            </div>

            ${card(`
                <div class="flex items-center gap-4">
                    ${homeProgressRing(dailyProgressPct)}
                    <p class="text-[13px] font-bold text-[var(--text-secondary)]">Daily<br>Progress</p>
                </div>
            `)}

            <div class="flex gap-2">
                ${statTile('📋', activeTasks.length, 'Active Tasks')}
                ${statTile('📅', dueToday.length, 'Due Today')}
                ${statTile('⭐', scoreData.score !== null ? Math.round(scoreData.score) : '—', 'Task Score')}
            </div>

            ${card(`
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center shrink-0 text-[15px]">✨</div>
                    <div class="min-w-0">
                        <p class="text-[12px] font-black text-[var(--text-strong)]">Daily Insight</p>
                        <p class="text-[12px] text-[var(--text-secondary)] mt-0.5 leading-relaxed">${buildDailyInsight(tasks)}</p>
                    </div>
                </div>
            `)}

            <button onclick="renderCreateTask()" class="w-full py-3.5 rounded-2xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[13px] font-black shadow-lg shadow-amber-400/20 flex items-center justify-center gap-1.5">
                <span class="text-[16px] leading-none">+</span> Create Task
            </button>
        `;
    }

    /* ================================================================ */
    /* TASKS TAB — a flat, priority/due-sorted list. A task may optionally */
    /* align to a KPI purely for visibility — it never writes that KPI's   */
    /* actual (only the KPI tab's inline Update box does that).            */
    /* ================================================================ */

    function taskCard(t, primaryKpiId) {
        const statusPill = TASK_STATUS_PILL[t.status] || TASK_STATUS_PILL.not_started;
        const priorityPill = PRIORITY_LABELS[t.priority] || PRIORITY_LABELS.medium;
        const extraKpis = (t.linked_kpis || []).filter(k => k.kpi_id !== primaryKpiId);
        const kpiChips = (primaryKpiId ? (t.linked_kpis || []).filter(k => k.kpi_id === primaryKpiId) : []).concat(extraKpis);
        const chipsHtml = kpiChips.length
            ? `<div class="flex flex-wrap gap-1.5 mt-2">${kpiChips.map(k => `<span class="px-2 py-0.5 rounded-full bg-[var(--accent-soft)] text-[var(--accent)] text-[8px] font-black">${k.kpi_title}</span>`).join('')}</div>`
            : '';

        return card(`
            <div class="flex items-center justify-between gap-2">
                <p class="text-[13px] font-black text-[var(--text-strong)] leading-snug min-w-0">${t.title}</p>
                <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full shrink-0 ${statusPill.color}">${statusPill.label}</span>
            </div>
            <div class="flex flex-wrap gap-1.5 mt-2">
                <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${priorityPill.color}">${priorityPill.label}</span>
                ${dueDateBadge(t.due_date)}
            </div>
            <div class="w-full h-1.5 bg-[var(--track-bg)] rounded-full mt-2 overflow-hidden">
                <div class="h-full rounded-full bg-[var(--accent)]" style="width:${t.progress_percentage || 0}%"></div>
            </div>
            ${chipsHtml}
            <div class="flex items-center gap-2 mt-3">
                <button onclick="renderDailyUpdate('${t.id}')" class="flex-1 py-2 rounded-xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[11px] font-black">Update</button>
                <button onclick="renderTaskDetail('${t.id}')" class="flex-1 py-2 rounded-xl bg-[var(--card-bg)] border-2 border-[var(--card-border)] text-[var(--text-secondary)] text-[11px] font-black">Details</button>
            </div>
        `);
    }

    async function renderTasksTab() {
        showRootChrome('tasks');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading your tasks…</p>`;

        let data;
        try {
            data = await api(`/project-tasks?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
        } catch (e) {
            renderError('Could not load your tasks.');
            return;
        }

        window.__myTasks = data.tasks || [];

        const header = `
            <div class="flex items-center justify-between">
                <p class="text-[16px] font-black text-[var(--text-strong)]">My Tasks</p>
                <button onclick="renderCreateTask()" class="w-9 h-9 rounded-full bg-[var(--accent)] text-[#1a1408] text-[18px] font-black flex items-center justify-center leading-none shadow-lg shadow-amber-400/20">+</button>
            </div>
        `;

        if (!window.__myTasks.length) {
            app.innerHTML = header + `<div class="mt-3">${card(`<p class="text-[13px] text-[var(--text-secondary)] text-center py-6">No tasks yet — tap "+" to create one.</p>`)}</div>`;
            return;
        }

        const statusOrder = { blocked: 0, in_progress: 1, not_started: 2, done: 3, cancelled: 4 };
        const sorted = [...window.__myTasks].sort((a, b) => {
            const so = (statusOrder[a.status] ?? 9) - (statusOrder[b.status] ?? 9);
            if (so !== 0) return so;
            if (a.due_date && b.due_date) return a.due_date < b.due_date ? -1 : 1;
            if (a.due_date) return -1;
            if (b.due_date) return 1;
            return 0;
        });

        const rows = sorted.map(t => taskCard(t, (t.linked_kpis || [])[0]?.kpi_id || null)).join('<div class="h-2"></div>');
        app.innerHTML = header + `<div class="mt-3">${rows}</div>`;
    }

    /* ================================================================ */
    /* CREATE TASK — one screen: name, due date, priority, and an         */
    /* optional AI-assisted KPI alignment. No unit/target up front — a    */
    /* task's own progress is tracked via status/progress% (Daily         */
    /* Update), not a numeric target (that stays the KPI tab's job).      */
    /* ================================================================ */

    let __defaultProjectId = null;
    async function ensureDefaultProject() {
        if (__defaultProjectId) return __defaultProjectId;
        const data = await api(`/projects?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
        const projects = data.projects || [];
        if (projects.length) {
            __defaultProjectId = projects[0].id;
            return __defaultProjectId;
        }
        const created = await api('/projects', {
            method: 'POST',
            body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode, name: 'My Tasks' }),
        });
        __defaultProjectId = created.project.id;
        return __defaultProjectId;
    }

    async function renderCreateTask(isUnplanned = false) {
        showSubScreenChrome('Create Task');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        let kpiOptions = [];
        try {
            const data = await api(`/project-tasks/kpi-options?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
            kpiOptions = data.kpis || [];
        } catch (e) {
            // Non-fatal — the form still works without KPI options loaded.
        }

        app.innerHTML = card(`
            <p class="text-[10px] font-bold text-[var(--text-secondary)] mb-1">Task Name</p>
            <input type="text" id="ctTitle" placeholder="Enter task name" oninput="debounceKpiSuggestion()"
                class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)]">

            <p class="text-[10px] font-bold text-[var(--text-secondary)] mt-3 mb-1">Due Date</p>
            <input type="date" id="ctDueDate" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)]">

            <p class="text-[10px] font-bold text-[var(--text-secondary)] mt-3 mb-1">Priority</p>
            <select id="ctPriority" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)]">
                ${Object.entries(PRIORITY_LABELS).map(([key, p]) => `<option value="${key}" ${key === 'medium' ? 'selected' : ''}>${p.label}</option>`).join('')}
            </select>

            <div class="flex items-center justify-between mt-3 mb-1">
                <p class="text-[10px] font-bold text-[var(--text-secondary)]">Align to KPI <span class="text-[var(--text-muted)] font-normal">(optional, pick any that apply)</span></p>
                <button type="button" onclick="renderCreateTask(${isUnplanned ? 'true' : 'false'})" class="text-[9px] font-black text-[var(--accent)] shrink-0">🔄 Refresh</button>
            </div>
            <div id="ctKpiOptions" class="space-y-1.5">
                ${kpiOptions.length
                    ? kpiOptions.map(k => `
                        <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-[var(--card-border)] bg-[var(--input-bg)] cursor-pointer">
                            <input type="checkbox" value="${k.kpi_id}" onchange="clearKpiSuggestionPill()" class="ct-kpi-checkbox w-4 h-4 accent-[var(--accent)] shrink-0">
                            <span class="text-[12px] font-bold text-[var(--text-secondary)] min-w-0">${k.kpi_title}</span>
                        </label>
                    `).join('')
                    : `<p class="text-[11px] text-[var(--text-muted)]">No open KPIs right now.</p>`}
            </div>
            <p id="ctKpiSuggestPill" class="hidden mt-1.5"></p>

            <div class="flex items-start gap-2 mt-3 px-1">
                <span class="text-[var(--text-muted)] text-[12px]">ⓘ</span>
                <p class="text-[10px] text-[var(--text-muted)] leading-relaxed">Task activity will be calculated later. It will not update the KPI actual automatically.</p>
            </div>

            <button onclick="saveNewTaskSimple(false)" class="w-full mt-4 py-3 rounded-2xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[13px] font-black">Save Task</button>
            <button onclick="saveNewTaskSimple(true)" class="w-full mt-2 py-3 rounded-2xl bg-white border-2 border-[var(--card-border)] text-[var(--text-secondary)] text-[13px] font-black">Save & Add Another</button>
            <p id="ctFeedback" class="hidden text-[10px] font-bold text-red-600 mt-2 text-center"></p>
        `);

        if (isUnplanned) {
            document.getElementById('ctDueDate').value = todayStr();
        }
    }

    let __kpiSuggestTimer = null;
    function debounceKpiSuggestion() {
        clearTimeout(__kpiSuggestTimer);
        const title = document.getElementById('ctTitle').value.trim();
        if (title.length < 6) return;
        __kpiSuggestTimer = setTimeout(() => fetchDraftKpiSuggestion(title), 600);
    }

    async function fetchDraftKpiSuggestion(title) {
        const pill = document.getElementById('ctKpiSuggestPill');
        if (!pill) return;
        try {
            const data = await api('/project-tasks/kpi-suggestion-draft', {
                method: 'POST',
                body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode, title }),
            });
            if (!data.suggestion) { pill.classList.add('hidden'); return; }
            const s = data.suggestion;
            const checkbox = document.querySelector(`.ct-kpi-checkbox[value="${s.kpi_id}"]`);
            if (checkbox) checkbox.checked = true;
            pill.classList.remove('hidden');
            pill.innerHTML = `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-black">✨ AI Suggested · ${s.confidence}% match</span>`;
        } catch (e) {
            // Silent — a missed suggestion just leaves the dropdown as-is.
        }
    }

    function clearKpiSuggestionPill() {
        const pill = document.getElementById('ctKpiSuggestPill');
        if (pill) pill.classList.add('hidden');
    }

    async function saveNewTaskSimple(addAnother) {
        const feedback = document.getElementById('ctFeedback');
        const title = document.getElementById('ctTitle').value.trim();
        const dueDate = document.getElementById('ctDueDate').value || null;
        const priority = document.getElementById('ctPriority').value;
        const kpiIds = [...document.querySelectorAll('.ct-kpi-checkbox:checked')].map(el => el.value);

        if (!title) {
            feedback.textContent = 'Enter a task name.';
            feedback.classList.remove('hidden');
            return;
        }
        feedback.classList.add('hidden');

        try {
            const projectId = await ensureDefaultProject();
            await api('/project-tasks', {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: state.employeeId, company_code: state.companyCode,
                    project_id: projectId, title, unit: 'number', target: 0,
                    due_date: dueDate, priority, kpi_ids: kpiIds,
                }),
            });
            if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
            if (tg?.showPopup) tg.showPopup({ message: 'Task saved!' });
            if (addAnother) {
                renderCreateTask();
            } else {
                switchRootTab('tasks');
            }
        } catch (e) {
            feedback.textContent = e.data?.message || "Couldn't save — please try again.";
            feedback.classList.remove('hidden');
        }
    }

    /* ================================================================ */
    /* EDIT / DELETE — same simplified field set as Create Task (title,   */
    /* due date, priority); KPI links stay a separate step via the        */
    /* existing "Edit KPI Links" screen, same as on Task Details.         */
    /* ================================================================ */

    async function renderEditTask(taskId) {
        showSubScreenChrome('Edit Task');
        const app = document.getElementById('app');
        const t = window.__taskDetail?.id === taskId ? window.__taskDetail : null;

        if (!t) {
            renderError('Could not load this task.');
            return;
        }

        app.innerHTML = card(`
            <p class="text-[10px] font-bold text-[var(--text-secondary)] mb-1">Task Name</p>
            <input type="text" id="etTitle" value="${(t.title || '').replace(/"/g, '&quot;')}"
                class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)]">

            <p class="text-[10px] font-bold text-[var(--text-secondary)] mt-3 mb-1">Due Date</p>
            <input type="date" id="etDueDate" value="${t.due_date || ''}" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)]">

            <p class="text-[10px] font-bold text-[var(--text-secondary)] mt-3 mb-1">Priority</p>
            <select id="etPriority" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)]">
                ${Object.entries(PRIORITY_LABELS).map(([key, p]) => `<option value="${key}" ${key === t.priority ? 'selected' : ''}>${p.label}</option>`).join('')}
            </select>

            <button onclick="saveEditTask('${taskId}')" class="w-full mt-4 py-3 rounded-2xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[13px] font-black">Save Changes</button>
            <p id="etFeedback" class="hidden text-[10px] font-bold text-red-600 mt-2 text-center"></p>
        `);
    }

    async function saveEditTask(taskId) {
        const t = window.__taskDetail;
        const feedback = document.getElementById('etFeedback');
        const title = document.getElementById('etTitle').value.trim();
        const dueDate = document.getElementById('etDueDate').value || null;
        const priority = document.getElementById('etPriority').value;

        if (!title) {
            feedback.textContent = 'Enter a task name.';
            feedback.classList.remove('hidden');
            return;
        }
        feedback.classList.add('hidden');

        try {
            await api(`/project-tasks/${taskId}`, {
                method: 'PATCH',
                body: JSON.stringify({
                    employee_id: state.employeeId, company_code: state.companyCode,
                    title, unit: t.unit, target: t.target,
                    due_date: dueDate, priority,
                }),
            });
            if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
            if (tg?.showPopup) tg.showPopup({ message: 'Task updated!' });
            renderTaskDetail(taskId);
        } catch (e) {
            feedback.textContent = e.data?.message || "Couldn't save — please try again.";
            feedback.classList.remove('hidden');
        }
    }

    function confirmDeleteTask(taskId) {
        const doDelete = async () => {
            try {
                await api(`/project-tasks/${taskId}`, {
                    method: 'DELETE',
                    body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode }),
                });
                if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
                switchRootTab('tasks');
            } catch (e) {
                showToast(e.data?.message || "Couldn't delete — please try again.");
            }
        };
        if (tg?.showConfirm) {
            tg.showConfirm('Delete this task? This cannot be undone.', (ok) => { if (ok) doDelete(); });
        } else if (confirm('Delete this task? This cannot be undone.')) {
            doDelete();
        }
    }

    /* ================================================================ */
    /* DAILY UPDATE — status (radio-style), progress slider, a note, and  */
    /* a quick path to log an unplanned task. Never touches any linked    */
    /* KPI's actual (that stays the KPI tab's job).                       */
    /* ================================================================ */

    function statusRadioBox(key, label, isSelected) {
        return `
            <button type="button" onclick="selectDailyStatus('${key}')" data-status-option="${key}"
                class="daily-status-btn flex-1 py-3 rounded-xl border-2 text-[11px] font-bold text-center transition ${isSelected ? 'border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--accent)]' : 'border-[var(--card-border)] text-[var(--text-secondary)]'}">
                ${label}
            </button>
        `;
    }

    async function renderDailyUpdate(taskId) {
        showSubScreenChrome('5:30 PM Task Update');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        let data;
        try {
            data = await api(`/project-tasks/${taskId}?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
        } catch (e) {
            renderError('Could not load this task.');
            return;
        }

        window.__taskDetail = data.task;
        const t = data.task;
        const otherOpenCount = (window.__myTasks || []).filter(x => x.id !== taskId && !['done', 'cancelled'].includes(x.status)).length;

        app.innerHTML = `
            ${card(`
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[var(--accent-soft)] flex items-center justify-center shrink-0 text-[16px]">📄</div>
                    <div class="min-w-0">
                        <p class="text-[13px] font-black text-[var(--text-strong)] leading-snug">${t.title}</p>
                        ${t.description ? `<p class="text-[11px] text-[var(--text-muted)] mt-0.5">${t.description}</p>` : ''}
                    </div>
                </div>
            `)}

            <div class="h-2"></div>
            ${card(`
                <p class="text-[11px] font-black text-[var(--text-secondary)] mb-2">Status</p>
                <div class="flex gap-2">
                    ${statusRadioBox('not_started', 'Not Started', t.status === 'not_started')}
                    ${statusRadioBox('in_progress', 'In Progress', t.status === 'in_progress')}
                    ${statusRadioBox('done', 'Done', t.status === 'done')}
                </div>
                <div class="flex gap-2 mt-2">
                    ${statusRadioBox('blocked', 'Blocked', t.status === 'blocked')}
                    ${statusRadioBox('cancelled', 'Cancelled', t.status === 'cancelled')}
                </div>
                <input type="hidden" id="dailyStatusInput" value="${t.status}">

                <div class="flex items-center justify-between mt-4 mb-1">
                    <p class="text-[11px] font-black text-[var(--text-secondary)]">Progress</p>
                    <p class="text-[11px] font-black text-[var(--accent)]"><span id="dailyProgressValue">${t.progress_percentage ?? 0}</span>%</p>
                </div>
                <input type="range" min="0" max="100" id="dailyProgressInput" value="${t.progress_percentage ?? 0}"
                    oninput="document.getElementById('dailyProgressValue').textContent = this.value"
                    class="w-full accent-[var(--accent)]">
                <div class="flex items-center justify-between text-[9px] text-[var(--text-muted)]"><span>0%</span><span>100%</span></div>

                <p class="text-[11px] font-black text-[var(--text-secondary)] mt-4 mb-1">Today's Update</p>
                <textarea id="dailyNoteInput" rows="3" maxlength="500" oninput="updateNoteCounter()" placeholder="Share what you worked on today…"
                    class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)] resize-none"></textarea>
                <p class="text-[9px] text-[var(--text-muted)] text-right mt-0.5"><span id="dailyNoteCount">0</span>/500</p>

                <button onclick="renderCreateTask(true)" class="w-full mt-2 py-2.5 rounded-xl border-2 border-dashed border-slate-300 text-[var(--text-secondary)] text-[11px] font-bold">+ Add an unplanned task</button>

                <button onclick="submitDailyUpdate('${t.id}')" class="w-full mt-3 py-3 rounded-2xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[13px] font-black">Submit Daily Update</button>

                ${otherOpenCount > 0 ? `
                    <div class="flex items-center gap-2 mt-3 px-3 py-2 rounded-xl bg-red-50 border border-red-100">
                        <span class="text-red-500 text-[13px]">⚠</span>
                        <p class="text-[10px] text-red-600 font-bold">${otherOpenCount} other task${otherOpenCount > 1 ? 's' : ''} still need${otherOpenCount > 1 ? '' : 's'} an update</p>
                    </div>
                ` : ''}
                <p id="dailyUpdateFeedback" class="hidden text-[10px] font-bold text-red-600 mt-2 text-center"></p>
            `)}
        `;
    }

    function selectDailyStatus(key) {
        document.getElementById('dailyStatusInput').value = key;
        document.querySelectorAll('.daily-status-btn').forEach(btn => {
            const active = btn.dataset.statusOption === key;
            btn.classList.toggle('border-[var(--accent)]', active);
            btn.classList.toggle('bg-[var(--accent-soft)]', active);
            btn.classList.toggle('text-[var(--accent)]', active);
            btn.classList.toggle('border-[var(--card-border)]', !active);
            btn.classList.toggle('text-[var(--text-secondary)]', !active);
        });
    }

    function updateNoteCounter() {
        document.getElementById('dailyNoteCount').textContent = document.getElementById('dailyNoteInput').value.length;
    }

    async function submitDailyUpdate(taskId) {
        const feedback = document.getElementById('dailyUpdateFeedback');
        const status = document.getElementById('dailyStatusInput').value;
        const progress = document.getElementById('dailyProgressInput').value;
        const note = document.getElementById('dailyNoteInput')?.value.trim() || null;

        if (status === 'blocked' && !note) {
            feedback.textContent = "Tell us what's blocking this task before submitting.";
            feedback.classList.remove('hidden');
            return;
        }
        feedback.classList.add('hidden');

        try {
            await api(`/project-tasks/${taskId}/daily-update`, {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: state.employeeId, company_code: state.companyCode,
                    status, progress: progress === '' ? null : Number(progress), note,
                }),
            });
            if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
            if (tg?.showPopup) tg.showPopup({ message: 'Daily update saved!' });
            switchRootTab('tasks');
        } catch (e) {
            feedback.textContent = e.data?.message || "Couldn't save — please try again.";
            feedback.classList.remove('hidden');
        }
    }

    /* ================================================================ */
    /* TASK DETAILS — the deeper screen behind "Details": quick numeric   */
    /* update (for tasks that do track a number), full KPI editing with   */
    /* an AI suggestion, reschedule, and update history.                  */
    /* ================================================================ */

    function updateHistoryRow(u) {
        const when = (u.created_at || '').replace('T', ' ').slice(0, 16);
        const parts = [];
        if (u.status_at_update) parts.push(`marked <b>${(TASK_STATUS_PILL[u.status_at_update] || {}).label || u.status_at_update}</b>`);
        if (u.progress_at_update !== null && u.progress_at_update !== undefined) parts.push(`${u.progress_at_update}% progress`);
        if (Number(u.delta) !== 0) parts.push(`${u.delta >= 0 ? '+' : ''}${u.delta} added (now ${u.new_actual})`);
        if (u.note) parts.push(`note: "${u.note}"`);
        if (u.reschedule_reason) parts.push(`rescheduled: "${u.reschedule_reason}"`);

        return `
            <div class="py-2 border-b border-[var(--card-border)] last:border-0">
                <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed">${parts.join(' · ') || 'Logged an update'}</p>
                <p class="text-[9px] text-[var(--text-muted)] mt-0.5">${when}</p>
            </div>
        `;
    }

    async function renderTaskDetail(taskId) {
        showSubScreenChrome('Task Details');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        let data;
        try {
            data = await api(`/project-tasks/${taskId}?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
        } catch (e) {
            renderError('Could not load this task.');
            return;
        }

        window.__taskDetail = data.task;
        window.__taskUpdates = data.updates || [];

        const t = data.task;
        const statusPill = TASK_STATUS_PILL[t.status] || TASK_STATUS_PILL.not_started;
        const priorityPill = PRIORITY_LABELS[t.priority] || PRIORITY_LABELS.medium;

        const kpiChips = (t.linked_kpis || []).length
            ? t.linked_kpis.map(k => `
                <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-[var(--accent-soft)] mt-1.5">
                    <p class="text-[11px] font-black text-[var(--accent)] min-w-0">${k.kpi_title}${k.ai_suggested ? ' 🤖' : ''}</p>
                </div>
            `).join('')
            : `<p class="text-[11px] text-[var(--text-muted)] mt-1.5">Not linked to a KPI yet.</p>`;

        app.innerHTML = `
            ${card(`
                <p class="text-[14px] font-black text-[var(--text-strong)] leading-snug">${t.title}</p>
                ${t.description ? `<p class="text-[11px] text-[var(--text-secondary)] mt-1.5 leading-relaxed">${t.description}</p>` : ''}
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${statusPill.color}">${statusPill.label}</span>
                    <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${priorityPill.color}">${priorityPill.label} priority</span>
                    ${dueDateBadge(t.due_date)}
                </div>
                <div class="flex items-center gap-2 mt-3">
                    <button onclick="renderDailyUpdate('${t.id}')" class="flex-1 py-2 rounded-xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[11px] font-black">Daily Update</button>
                    <button onclick="renderEditTask('${t.id}')" class="px-3 py-2 rounded-xl bg-[var(--card-bg)] border-2 border-[var(--card-border)] text-[var(--text-secondary)] text-[11px] font-black">✎ Edit</button>
                    <button onclick="confirmDeleteTask('${t.id}')" class="px-3 py-2 rounded-xl bg-white border-2 border-red-200 text-red-600 text-[11px] font-black">🗑</button>
                </div>
            `)}

            <div class="h-2"></div>
            ${card(`
                <p class="text-[12px] font-black text-[var(--text-strong)] mb-2">Quick number update</p>
                <div class="flex items-center gap-2">
                    <input type="number" step="any" placeholder="e.g. 50 or -10" id="taskDeltaInput"
                        class="flex-1 min-w-0 text-[13px] px-3 py-2.5 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)] focus:bg-[var(--input-bg)]">
                    <button onclick="submitTaskProgress('${t.id}')" class="px-5 py-2.5 rounded-xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[12px] font-black shrink-0">Add</button>
                </div>
                <p class="text-[9px] text-[var(--text-muted)] mt-1">Use a minus sign to reduce. For tasks that track a number (target ${formatUnit(t.target, t.unit)}, actual ${formatUnit(t.actual, t.unit)}).</p>
                <p id="taskProgressFeedback" class="hidden text-[10px] font-bold mt-2"></p>
            `)}

            <div class="h-2"></div>
            ${card(`
                <div class="flex items-center justify-between">
                    <p class="text-[12px] font-black text-[var(--text-strong)]">KPI alignment</p>
                    <button onclick="requestKpiSuggestion('${t.id}')" class="text-[10px] font-black text-[var(--accent)] bg-[var(--accent-soft)] px-2.5 py-1 rounded-full">🤖 Suggest with AI</button>
                </div>
                ${kpiChips}
                <p id="kpiSuggestionBox" class="hidden mt-2"></p>
                <button onclick="renderLinkTaskKpis('${t.id}')" class="w-full mt-2 py-2 rounded-xl bg-[var(--card-bg)] border-2 border-[var(--card-border)] text-[var(--text-secondary)] text-[11px] font-black">Edit KPI Links</button>
            `)}

            <div class="h-2"></div>
            ${card(`
                <p class="text-[12px] font-black text-[var(--text-strong)] mb-1">History</p>
                <div>${window.__taskUpdates.length ? window.__taskUpdates.map(updateHistoryRow).join('') : '<p class="text-[11px] text-[var(--text-muted)] py-2">No updates logged yet.</p>'}</div>
            `)}
        `;
    }

    async function submitTaskProgress(taskId) {
        const t = window.__taskDetail;
        const input = document.getElementById('taskDeltaInput');
        const feedback = document.getElementById('taskProgressFeedback');
        const raw = input.value.trim();

        if (raw === '' || isNaN(Number(raw)) || Number(raw) === 0) {
            showToast('Enter an amount first, e.g. 50 or -10.');
            return;
        }

        const delta = Number(raw);
        if (delta < 0 && (Number(t.actual) + delta) < 0) {
            feedback.textContent = `Can't reduce — this task's actual is only ${t.actual}.`;
            feedback.className = 'text-[10px] font-bold mt-2 text-red-600';
            feedback.classList.remove('hidden');
            return;
        }

        feedback.classList.add('hidden');

        try {
            await api(`/project-tasks/${taskId}/progress`, {
                method: 'POST',
                body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode, delta }),
            });
            if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
            if (tg?.showPopup) tg.showPopup({ message: 'Task updated!' });
            renderTaskDetail(taskId);
        } catch (e) {
            feedback.textContent = e.data?.message || "Couldn't update — please try again.";
            feedback.className = 'text-[10px] font-bold mt-2 text-red-600';
            feedback.classList.remove('hidden');
        }
    }

    async function requestKpiSuggestion(taskId) {
        const box = document.getElementById('kpiSuggestionBox');
        box.classList.remove('hidden');
        box.innerHTML = `<span class="text-[10px] text-[var(--text-muted)]">Thinking…</span>`;

        try {
            const data = await api(`/project-tasks/${taskId}/kpi-suggestion`, {
                method: 'POST',
                body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode }),
            });
            if (!data.suggestion) {
                box.innerHTML = `<span class="text-[10px] text-[var(--text-secondary)]">No confident match found among your KPIs.</span>`;
                return;
            }
            const s = data.suggestion;
            box.innerHTML = `
                <div class="px-3 py-2 rounded-xl bg-amber-50 border border-amber-200">
                    <p class="text-[11px] font-black text-amber-800">🤖 ${s.confidence}% confident</p>
                    <p class="text-[11px] text-amber-700 mt-0.5">${s.reason}</p>
                    <button onclick='applyKpiSuggestion(${JSON.stringify(taskId)}, ${JSON.stringify(s)})' class="mt-2 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-[10px] font-black">Link this KPI</button>
                </div>
            `;
        } catch (e) {
            box.innerHTML = `<span class="text-[10px] text-red-500">${e.data?.message || "Couldn't get a suggestion."}</span>`;
        }
    }

    async function applyKpiSuggestion(taskId, suggestion) {
        const existingIds = (window.__taskDetail.linked_kpis || []).map(k => k.kpi_id);
        try {
            await api(`/project-tasks/${taskId}/link-kpis`, {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: state.employeeId, company_code: state.companyCode,
                    kpi_ids: [...existingIds, suggestion.kpi_id],
                    ai_suggested: true, ai_confidence: suggestion.confidence, ai_reason: suggestion.reason,
                }),
            });
            if (tg?.showPopup) tg.showPopup({ message: 'KPI linked!' });
            renderTaskDetail(taskId);
        } catch (e) {
            showToast(e.data?.message || "Couldn't link — please try again.");
        }
    }

    async function renderLinkTaskKpis(taskId) {
        const t = (window.__taskDetail && window.__taskDetail.id === taskId) ? window.__taskDetail : (window.__myTasks || []).find(x => x.id === taskId);
        if (!t) { switchRootTab('tasks'); return; }

        showSubScreenChrome('Edit KPI Links');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading KPIs…</p>`;

        let data;
        try {
            data = await api(`/project-tasks/kpi-options?employee_id=${state.employeeId}&company_code=${state.companyCode}&unit=${t.unit}`);
        } catch (e) {
            renderError('Could not load your KPIs.');
            return;
        }

        const linkedIds = new Set((t.linked_kpis || []).map(k => k.kpi_id));
        const sortedKpis = sortByCategoryAndSub(data.kpis || []);

        const rows = sortedKpis.map(k => {
            const cat = CATEGORY_COLORS[k.category] || DEFAULT_CATEGORY_COLOR;
            const checked = linkedIds.has(k.kpi_id) ? 'checked' : '';
            return `
                <label class="block cursor-pointer">
                    ${card(`
                        <div class="flex items-center gap-3">
                            <input type="checkbox" value="${k.kpi_id}" class="kpi-link-checkbox w-5 h-5 accent-[var(--accent)] shrink-0" ${checked}>
                            <div class="min-w-0">
                                <span class="px-2 py-0.5 rounded-full ${cat.catPill} text-[8px] font-black">${k.category || '-'}</span>
                                <p class="text-[13px] font-black text-[var(--text-strong)] mt-1">${k.kpi_title}</p>
                            </div>
                        </div>
                    `)}
                </label>
            `;
        }).join('<div class="h-1.5"></div>') || `<p class="text-[12px] text-[var(--text-secondary)] text-center py-6">No open KPIs with a matching "${t.unit}" unit right now.</p>`;

        app.innerHTML = `
            <p class="text-[11px] text-[var(--text-secondary)] mb-2 px-1">Task "<b>${t.title}</b>" — tick which KPI(s) to align this to, or leave unticked for none. Doesn't change any KPI's actual — this is for visibility only.</p>
            <div>${rows}</div>
            <button onclick="saveLinkKpis('${taskId}')" class="w-full mt-4 py-3 rounded-2xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[13px] font-black">
                Save Links
            </button>
            <p id="linkKpisFeedback" class="hidden text-[10px] font-bold text-red-600 mt-2 text-center"></p>
        `;
    }

    async function saveLinkKpis(taskId) {
        const feedback = document.getElementById('linkKpisFeedback');
        const kpiIds = [...document.querySelectorAll('.kpi-link-checkbox:checked')].map(el => el.value);

        try {
            await api(`/project-tasks/${taskId}/link-kpis`, {
                method: 'POST',
                body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode, kpi_ids: kpiIds }),
            });
            if (tg?.showPopup) tg.showPopup({ message: 'Saved!' });
            renderTaskDetail(taskId);
        } catch (e) {
            feedback.textContent = e.data?.message || "Couldn't save — please try again.";
            feedback.classList.remove('hidden');
        }
    }

    /* ================================================================ */
    /* KPI TAB — unchanged inline-update flow for a KPI's own official    */
    /* actual, restyled to match the new palette.                         */
    /* ================================================================ */

    function quarterLabel(state) {
        if (state === 'current') return { text: '✏️ Update here', cls: 'bg-red-100 text-red-700' };
        if (state === 'ended') return { text: '🔒 Done', cls: 'bg-[var(--track-bg)] text-[var(--text-secondary)]' };
        return { text: '🔒 Upcoming', cls: 'bg-[var(--track-bg)] text-[var(--text-muted)]' };
    }

    function quarterRow(kpiId, q, unit) {
        const isCurrent = q.state === 'current';
        const badge = achvBadge(q.achievement_percentage);
        const barPct = Math.max(0, Math.min(100, q.achievement_percentage));
        const label = quarterLabel(q.state);

        const updateControl = isCurrent ? `
            <div class="mt-2.5 flex items-center gap-2">
                <input type="number" step="any" placeholder="e.g. 50 or -10" id="delta-${kpiId}"
                    class="flex-1 min-w-0 text-[12px] px-3 py-2 rounded-xl border-2 border-[var(--card-border)] bg-[var(--input-bg)] outline-none focus:border-[var(--accent)]">
                <button onclick="submitDelta('${kpiId}','${q.id}')" class="px-4 py-2 rounded-xl bg-[var(--accent)] hover:opacity-90 text-[#1a1408] text-[11px] font-black shrink-0">
                    Update
                </button>
            </div>
            <p class="text-[9px] text-[var(--text-muted)] mt-1">How much did today add? Use a minus sign to reduce.</p>
            <p id="feedback-${kpiId}" class="hidden text-[10px] font-bold mt-1.5"></p>
        ` : '';

        return `
            <div class="rounded-xl px-3 py-2.5 border ${isCurrent ? 'bg-[var(--accent-soft)] border-[var(--accent)]' : 'bg-[var(--card-bg)] border-[var(--card-border)]'}">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] font-black ${isCurrent ? 'text-[var(--accent)]' : 'text-[var(--text-secondary)]'}">${q.quarter}</p>
                    <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${label.cls}">${label.text}</span>
                </div>
                <div class="w-full h-1.5 bg-slate-200 rounded-full mt-2 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r ${badge.bar}" style="width:${barPct}%"></div>
                </div>
                <div class="flex items-center justify-between mt-1.5">
                    <p class="text-[10px] text-[var(--text-secondary)]">Target: <span class="font-bold text-[var(--text-secondary)]">${formatUnit(q.target, unit)}</span></p>
                    <p class="text-[10px] text-[var(--text-secondary)]">Actual: <span class="font-bold text-[var(--text-secondary)]">${formatUnit(q.actual, unit)}</span></p>
                    <p class="text-[10px] font-black ${isCurrent ? 'text-[var(--accent)]' : 'text-[var(--text-secondary)]'}">${q.achievement_percentage}%</p>
                </div>
                ${updateControl}
            </div>
        `;
    }

    async function renderMyKpis() {
        showRootChrome('kpi');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        let data;
        try {
            data = await api(`/kpis/summary?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
        } catch (e) {
            renderError('Could not load your KPIs.');
            return;
        }

        if (!data.kpis.length) {
            app.innerHTML = card(`<p class="text-[13px] text-[var(--text-secondary)] text-center py-6">No KPIs found for this financial year.</p>`);
            return;
        }

        window.__quarterActuals = {};
        data.kpis.forEach(k => (k.quarters || []).forEach(q => { window.__quarterActuals[q.id] = q.actual; }));

        const sorted = sortByCategoryAndSub(data.kpis);
        let lastCategory = null;
        let html = '';

        sorted.forEach(k => {
            if (k.category !== lastCategory) {
                html += `
                    <div class="flex items-center gap-2 mt-4 mb-1 px-1">
                        <p class="text-[11px] font-black uppercase tracking-wide text-[var(--text-secondary)]">${k.category || 'Other'}</p>
                    </div>
                `;
                lastCategory = k.category;
            }

            const cat = CATEGORY_COLORS[k.category] || DEFAULT_CATEGORY_COLOR;
            const sDef = STATUS_LABELS[k.status] || STATUS_LABELS.not_started;
            const aBadge = achvBadge(k.achievement_percentage);
            const pct = Math.max(0, Math.min(100, k.achievement_percentage));
            const annualTarget = (k.quarters || []).reduce((sum, q) => sum + (Number(q.target) || 0), 0);
            const quarterRows = (k.quarters || []).map(q => quarterRow(k.kpi_id, q, k.unit)).join('');

            html += card(`
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5 mb-2">
                            <span class="px-2 py-0.5 rounded-full ${cat.catPill} text-[8px] font-black">${k.category || '-'}</span>
                            ${k.sub_category ? `<span class="px-2 py-0.5 rounded-full ${cat.subPill} text-[8px] font-black">${k.sub_category}</span>` : ''}
                            <span class="flex items-center gap-1 px-2 py-0.5 rounded-full ${sDef.color} text-[8px] font-black">
                                <span class="w-1.5 h-1.5 rounded-full ${sDef.dot}"></span>${sDef.label}
                            </span>
                        </div>
                        <p class="text-[14px] font-black text-[var(--text-strong)] leading-snug">${k.kpi_title}</p>
                        <span class="inline-block mt-2 px-2 py-0.5 rounded-full ${aBadge.color} text-[9px] font-black">${aBadge.label}</span>
                    </div>
                    ${progressRing(k.achievement_percentage)}
                </div>

                <div class="w-full h-1.5 bg-[var(--track-bg)] rounded-full mt-3 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r ${aBadge.bar}" style="width:${pct}%"></div>
                </div>
                <div class="flex items-center justify-between mt-1.5">
                    <p class="text-[10px] text-[var(--text-secondary)] font-bold">Overall (Full Year)</p>
                    <p class="text-[11px] text-[var(--text-secondary)] font-black">${formatUnit(k.actual_value, k.unit)} / ${formatUnit(annualTarget, k.unit)}</p>
                </div>

                <div class="mt-3 pt-3 border-t border-dashed border-[var(--card-border)]">
                    <p class="text-[9px] uppercase tracking-wide text-[var(--text-muted)] font-black mb-2">By Quarter</p>
                    <div class="space-y-1.5">${quarterRows || '<p class="text-[10px] text-[var(--text-muted)]">No quarters set up yet.</p>'}</div>
                </div>

                <button onclick='renderKpiTaskHistory(${JSON.stringify(k.kpi_id)}, ${JSON.stringify(k.kpi_title)})' class="w-full mt-3 py-2 rounded-xl bg-[var(--card-bg)] border-2 border-[var(--card-border)] text-[var(--text-secondary)] text-[10px] font-black">
                    📜 Tasks & History
                </button>
            `) + '<div class="h-2"></div>';
        });

        app.innerHTML = html;
    }

    async function submitDelta(kpiId, quarterId) {
        const input = document.getElementById(`delta-${kpiId}`);
        const feedback = document.getElementById(`feedback-${kpiId}`);
        const raw = input.value.trim();

        if (raw === '' || isNaN(Number(raw)) || Number(raw) === 0) {
            showToast('Enter an amount first, e.g. 50 or -10.');
            return;
        }

        const delta = Number(raw);
        const currentActual = window.__quarterActuals?.[quarterId] ?? 0;

        if (delta < 0 && currentActual + delta < 0) {
            feedback.textContent = `Can't reduce — this quarter's actual is only ${currentActual}.`;
            feedback.className = 'text-[10px] font-bold mt-1.5 text-red-600';
            feedback.classList.remove('hidden');
            return;
        }

        feedback.classList.add('hidden');

        try {
            await api(`/kpis/${kpiId}/quarters/${quarterId}/adjust`, {
                method: 'POST',
                body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode, delta }),
            });
            if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
            if (tg?.showPopup) tg.showPopup({ message: 'Updated! Your KPI actual has been refreshed.' });
            renderMyKpis();
        } catch (e) {
            feedback.textContent = e.data?.message || "Couldn't update — please try again.";
            feedback.className = 'text-[10px] font-bold mt-1.5 text-red-600';
            feedback.classList.remove('hidden');
        }
    }

    async function renderKpiTaskHistory(kpiId, kpiTitle) {
        showSubScreenChrome('Tasks & History');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        let data;
        try {
            data = await api(`/kpis/${kpiId}/task-history?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
        } catch (e) {
            renderError('Could not load task history.');
            return;
        }

        if (!data.tasks.length) {
            app.innerHTML = card(`
                <p class="text-[13px] font-black text-[var(--text-strong)] mb-1">${kpiTitle}</p>
                <p class="text-[12px] text-[var(--text-secondary)] leading-relaxed mt-2">No tasks aligned to this KPI yet.</p>
            `);
            return;
        }

        const taskCards = data.tasks.map(t => {
            const pct = t.target > 0 ? Math.max(0, Math.min(100, (t.actual / t.target) * 100)) : 0;
            const badge = achvBadge(pct);
            const historyRows = t.updates.length ? t.updates.map(u => `
                <div class="flex items-center justify-between py-1.5 border-b border-[var(--card-border)] last:border-0">
                    <p class="text-[10px] text-[var(--text-secondary)]">${formatDateTime(u.created_at)}</p>
                    <p class="text-[10px] font-black ${u.delta >= 0 ? 'text-emerald-600' : 'text-red-600'}">${u.delta >= 0 ? '+' : ''}${formatUnit(u.delta, t.unit)}</p>
                    <p class="text-[10px] text-[var(--text-muted)]">→ ${formatUnit(u.new_actual, t.unit)}</p>
                </div>
            `).join('') : `<p class="text-[10px] text-[var(--text-muted)] py-2">No updates logged yet.</p>`;

            return card(`
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[13px] font-black text-[var(--text-strong)] leading-snug min-w-0">${t.title}</p>
                    <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full shrink-0 ${t.status === 'done' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">
                        ${t.status === 'done' ? 'Done' : 'In Progress'}
                    </span>
                </div>
                <p class="text-[10px] text-[var(--text-muted)]">📁 ${t.project_name}</p>
                <div class="w-full h-1.5 bg-[var(--track-bg)] rounded-full mt-2 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r ${badge.bar}" style="width:${pct}%"></div>
                </div>
                <div class="flex items-center justify-between mt-1.5">
                    <p class="text-[10px] text-[var(--text-secondary)]">Target: <span class="font-bold text-[var(--text-secondary)]">${formatUnit(t.target, t.unit)}</span></p>
                    <p class="text-[10px] text-[var(--text-secondary)]">Actual: <span class="font-bold text-[var(--text-secondary)]">${formatUnit(t.actual, t.unit)}</span></p>
                    <p class="text-[10px] font-black text-[var(--text-secondary)]">${pct.toFixed(0)}%</p>
                </div>
                <div class="mt-3 pt-3 border-t border-dashed border-[var(--card-border)]">
                    <p class="text-[9px] uppercase tracking-wide text-[var(--text-muted)] font-black mb-1">Update History</p>
                    ${historyRows}
                </div>
            `) + '<div class="h-2"></div>';
        }).join('');

        app.innerHTML = `<p class="text-[11px] text-[var(--text-secondary)] mb-2 px-1">Tasks aligned to "<b>${data.kpi_title}</b>"</p>` + taskCards;
    }

    /* ================================================================ */
    /* WEEKLY SUMMARY — My Progress (score + activity chart + AI          */
    /* summary) and My Team (attention list), reached from Profile.       */
    /* ================================================================ */

    function scoreStatusBand(status) {
        if (status === 'on_track') return { label: 'On Track', color: 'bg-emerald-100 text-emerald-700' };
        if (status === 'at_risk') return { label: 'At Risk', color: 'bg-amber-100 text-amber-700' };
        if (status === 'critical') return { label: 'Critical', color: 'bg-red-100 text-red-700' };
        return { label: 'Not enough data yet', color: 'bg-[var(--track-bg)] text-[var(--text-secondary)]' };
    }

    function avatarColor(status) {
        if (status === 'critical') return 'bg-red-500';
        if (status === 'at_risk') return 'bg-amber-500';
        if (status === 'on_track') return 'bg-emerald-500';
        return 'bg-slate-400';
    }

    let weeklySummarySubTab = 'progress';
    let __weeklyScoreData = null;
    let __weeklyTeamData = null;
    let __hasTeam = false;

    async function renderWeeklySummary() {
        showSubScreenChrome('Weekly Summary');
        weeklySummarySubTab = 'progress';
        await loadWeeklySummaryData();
        renderWeeklySummaryBody();
    }

    async function loadWeeklySummaryData() {
        document.getElementById('app').innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;
        try {
            __weeklyScoreData = await api(`/tasks/score?employee_id=${state.employeeId}&company_code=${state.companyCode}&period=weekly`);
        } catch (e) {
            __weeklyScoreData = null;
        }
        try {
            __weeklyTeamData = await api(`/team/attention?employee_id=${state.employeeId}&company_code=${state.companyCode}`);
            __hasTeam = true;
        } catch (e) {
            __hasTeam = false;
        }
    }

    function switchWeeklySummaryTab(tab) {
        weeklySummarySubTab = tab;
        renderWeeklySummaryBody();
    }

    function renderWeeklySummaryBody() {
        const app = document.getElementById('app');
        const tabsHtml = `
            <div class="flex items-center gap-1 border-b-2 border-[var(--card-border)] mb-3">
                <button onclick="switchWeeklySummaryTab('progress')" class="flex-1 pb-2.5 text-[12px] font-bold ${weeklySummarySubTab === 'progress' ? 'text-[var(--accent)] border-b-2 border-[var(--accent)] -mb-[2px]' : 'text-[var(--text-muted)]'}">My Progress</button>
                ${__hasTeam ? `<button onclick="switchWeeklySummaryTab('team')" class="flex-1 pb-2.5 text-[12px] font-bold ${weeklySummarySubTab === 'team' ? 'text-[var(--accent)] border-b-2 border-[var(--accent)] -mb-[2px]' : 'text-[var(--text-muted)]'}">My Team</button>` : ''}
            </div>
        `;

        app.innerHTML = tabsHtml + (weeklySummarySubTab === 'team' && __hasTeam ? weeklyTeamTabHtml() : weeklyProgressTabHtml());

        if (weeklySummarySubTab === 'progress') {
            setTimeout(loadWeeklyAiSummary, 0);
        }
    }

    function activityBarChart(series) {
        if (!series.length) return '';
        const max = Math.max(1, ...series.map(d => d.count));
        const bars = series.map(d => {
            const h = Math.round((d.count / max) * 48) + 4;
            return `
                <div class="flex flex-col items-center gap-1.5 flex-1">
                    <div class="w-full flex items-end justify-center" style="height:56px">
                        <div class="w-5 rounded-t-md ${d.count > 0 ? 'bg-[var(--accent)]' : 'bg-[var(--track-bg)]'}" style="height:${h}px"></div>
                    </div>
                    <p class="text-[9px] text-[var(--text-muted)] font-bold">${d.label}</p>
                </div>
            `;
        }).join('');
        return `<div class="flex items-end gap-1 mt-3">${bars}</div>`;
    }

    function weeklyProgressTabHtml() {
        const score = __weeklyScoreData;
        if (!score) return card(`<p class="text-[13px] text-[var(--text-secondary)] text-center py-6">Could not load your weekly score.</p>`);

        const band = scoreStatusBand(score.status);

        return card(`
            <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wide">Weekly Task Score</p>
            <div class="flex items-center justify-between mt-1">
                <p class="text-[34px] font-black text-[var(--text-strong)] leading-none">${score.score !== null ? Math.round(score.score) : '—'}</p>
                <div class="w-11 h-11 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 text-[18px]">↗</div>
            </div>
            <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full ${band.color} text-[9px] font-black">${band.label}</span>
            ${activityBarChart(score.daily_activity || [])}
        `) + '<div class="h-2"></div>' + `<div id="weeklyAiSummaryCard">${card(`<p class="text-[11px] text-[var(--text-muted)] text-center py-3">Loading AI summary…</p>`)}</div>`;
    }

    async function loadWeeklyAiSummary() {
        const el = document.getElementById('weeklyAiSummaryCard');
        if (!el) return;
        try {
            const data = await api(`/summaries?employee_id=${state.employeeId}&company_code=${state.companyCode}&scope=employee&period=weekly`);
            el.innerHTML = data.summary
                ? weeklyAiSummaryBlock(data.summary)
                : card(`
                    <p class="text-[11px] text-[var(--text-secondary)] text-center py-2">No AI summary generated yet for this week.</p>
                    <button onclick="generateWeeklyAiSummary()" class="w-full mt-1 py-2 rounded-xl bg-[var(--accent)] text-[#1a1408] text-[11px] font-black">✨ Generate AI Summary</button>
                `);
        } catch (e) {
            el.innerHTML = card(`<p class="text-[11px] text-red-500 text-center py-2">Could not load a summary.</p>`);
        }
    }

    function weeklyAiSummaryBlock(summary) {
        const recs = (summary.facts?.recommendations || []).map(r => `<li class="text-[10px] text-[var(--text-secondary)] mt-1">• ${r}</li>`).join('');
        return card(`
            <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[15px]">✨</span>
                <p class="text-[12px] font-black text-[var(--text-strong)]">AI Weekly Summary</p>
            </div>
            <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed">${summary.narrative}</p>
            ${recs ? `<ul class="mt-2">${recs}</ul>` : ''}
            <button onclick="generateWeeklyAiSummary()" class="mt-2 text-[10px] font-bold text-[var(--accent)]">↻ Regenerate</button>
        `);
    }

    async function generateWeeklyAiSummary() {
        const el = document.getElementById('weeklyAiSummaryCard');
        el.innerHTML = card(`<p class="text-[11px] text-[var(--text-muted)] text-center py-2">Generating…</p>`);
        try {
            const data = await api('/summaries/regenerate', {
                method: 'POST',
                body: JSON.stringify({ employee_id: state.employeeId, company_code: state.companyCode, scope: 'employee', period: 'weekly' }),
            });
            el.innerHTML = weeklyAiSummaryBlock(data.summary);
        } catch (e) {
            el.innerHTML = card(`<p class="text-[11px] text-red-500 text-center py-2">${e.data?.message || "Couldn't generate a summary."}</p>`);
        }
    }

    function weeklyTeamTabHtml() {
        const members = (__weeklyTeamData && __weeklyTeamData.members) || [];
        if (!members.length) return card(`<p class="text-[12px] text-[var(--text-secondary)] text-center py-6">No team members found.</p>`);

        const rows = members.map(m => `
            <div class="flex items-center justify-between gap-2 py-2 border-b border-[var(--card-border)] last:border-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-full ${avatarColor(m.status)} text-white text-[11px] font-black flex items-center justify-center shrink-0">${initials(m.name)}</div>
                    <div class="min-w-0">
                        <p class="text-[12px] font-bold text-slate-800 truncate">${m.name}</p>
                        <p class="text-[10px] text-[var(--text-muted)]">${m.overdue_count > 0 ? m.overdue_count + ' overdue' : (m.status === 'at_risk' ? 'At risk' : (m.score !== null ? Math.round(m.score) + '/100' : 'No data yet'))}</p>
                    </div>
                </div>
            </div>
        `).join('');

        return card(rows);
    }

    /* ================================================================ */
    /* PERFORMANCE REVIEW — AI-generated weekly/monthly/quarterly score   */
    /* and narrative, grounded in task activity + KPI standing. Reviews   */
    /* are generated on a schedule, not on demand — this screen only      */
    /* displays what already exists.                                     */
    /* ================================================================ */

    const REVIEW_PERIODS = [
        { key: 'weekly', label: 'Weekly' },
        { key: 'monthly', label: 'Monthly' },
        { key: 'quarterly', label: 'Quarterly' },
    ];

    function reviewBand(score) {
        if (score >= 90) return { label: 'Excellent', cls: 'text-emerald-800 border-emerald-700' };
        if (score >= 75) return { label: 'Good', cls: 'text-[var(--text-secondary)] border-slate-400' };
        if (score >= 50) return { label: 'Needs Attention', cls: 'text-amber-800 border-amber-700' };
        return { label: 'At Risk', cls: 'text-rose-800 border-rose-700' };
    }

    function reviewEmptyNote(periodType) {
        if (periodType === 'weekly') return 'Generated every Sunday evening, covering the previous 7 days.';
        if (periodType === 'monthly') return 'Generated on the 1st of each month, covering the month before.';
        return 'Generated automatically when one of your KPI quarters closes.';
    }

    function reviewCard(r) {
        const band = reviewBand(r.score);
        return card(`
            <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wide">${r.period_label}</p>
            <p class="text-[28px] font-black text-[var(--text-strong)] mt-1 leading-none">${Math.round(r.score)}<span class="text-[13px] font-bold text-[var(--text-muted)]">/100</span></p>
            <span class="inline-block mt-2 px-2 py-0.5 rounded-full border text-[9px] font-bold ${band.cls}">${band.label}</span>
            <p class="text-[12px] text-[var(--text-secondary)] leading-relaxed mt-3 pt-3 border-t border-[var(--card-border)]">${r.narrative}</p>
        `);
    }

    function reviewHistorySection(history) {
        if (!history.length) return '';
        const rows = history.map((r, i) => `
            <button onclick="renderReviewDetail(${i})" class="w-full text-left tap-card">
                ${card(`
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12px] font-bold text-[var(--text-secondary)]">${r.period_label}</p>
                        <p class="text-[12px] font-black text-[var(--text-secondary)]">${Math.round(r.score)}/100</p>
                    </div>
                `, 'hover:border-slate-400')}
            </button>
        `).join('<div class="h-1.5"></div>');

        return `
            <p class="text-[10px] uppercase tracking-wide text-[var(--text-muted)] font-bold mt-4 mb-1.5 px-1">Previous periods</p>
            <div>${rows}</div>
        `;
    }

    async function renderPerformanceReview(periodType) {
        periodType = periodType || 'weekly';
        showSubScreenChrome('Performance Review');
        const app = document.getElementById('app');
        app.innerHTML = `<p class="text-center text-[var(--text-muted)] text-[12px] mt-10">Loading…</p>`;

        const tabs = `
            <div class="flex items-center gap-1 border-b-2 border-[var(--card-border)] mb-4">
                ${REVIEW_PERIODS.map(p => `
                    <button onclick="renderPerformanceReview('${p.key}')"
                        class="flex-1 pb-2.5 text-[12px] font-bold ${p.key === periodType ? 'text-[var(--text-strong)] border-b-2 border-slate-900 -mb-[2px]' : 'text-[var(--text-muted)]'}">
                        ${p.label}
                    </button>
                `).join('')}
            </div>
        `;

        let data;
        try {
            data = await api(`/reviews?employee_id=${state.employeeId}&company_code=${state.companyCode}&period=${periodType}`);
        } catch (e) {
            app.innerHTML = tabs + card(`<p class="text-[13px] text-[var(--text-secondary)] text-center py-6">Could not load your review.</p>`);
            return;
        }

        window.__reviewHistory = data.history || [];

        if (!data.latest) {
            app.innerHTML = tabs + card(`
                <p class="text-[13px] font-bold text-[var(--text-strong)] mb-1.5">No review yet</p>
                <p class="text-[12px] text-[var(--text-secondary)] leading-relaxed">${reviewEmptyNote(periodType)}</p>
            `);
            return;
        }

        app.innerHTML = tabs + reviewCard(data.latest) + reviewHistorySection(window.__reviewHistory);
    }

    function renderReviewDetail(index) {
        const r = (window.__reviewHistory || [])[index];
        if (!r) { renderPerformanceReview('weekly'); return; }
        showSubScreenChrome('Performance Review');
        document.getElementById('app').innerHTML = reviewCard(r);
    }

    /* ================================================================ */
    /* PROFILE TAB                                                         */
    /* ================================================================ */

    function renderProfile() {
        showRootChrome('profile');
        const canSwitchCompany = (window.__dashboards || []).length > 1;
        document.getElementById('app').innerHTML = `
            ${card(`
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-full bg-[var(--navy)] text-white text-[18px] font-black flex items-center justify-center shrink-0">${initials(state.employeeName)}</div>
                    <div class="min-w-0">
                        <p class="text-[15px] font-black text-[var(--text-strong)] truncate">${state.employeeName}</p>
                        <p class="text-[11px] text-[var(--text-muted)]">${state.companyCode || ''}</p>
                    </div>
                </div>
            `)}

            <div class="h-2"></div>
            <button onclick="renderWeeklySummary()" class="w-full text-left tap-card">
                ${card(`<p class="text-[13px] font-bold text-[var(--text-secondary)]">📈 Weekly Summary</p>`, 'hover:border-slate-300')}
            </button>

            <div class="h-2"></div>
            <button onclick="renderPerformanceReview('weekly')" class="w-full text-left tap-card">
                ${card(`<p class="text-[13px] font-bold text-[var(--text-secondary)]">📊 KPI Performance Review</p>`, 'hover:border-slate-300')}
            </button>

            ${canSwitchCompany ? `
            <div class="h-2"></div>
            <button onclick="renderSwitchCompany()" class="w-full text-left tap-card">
                ${card(`<p class="text-[13px] font-bold text-[var(--text-secondary)]">🔄 Switch Company</p>`, 'hover:border-slate-300')}
            </button>` : ''}

            <div class="h-2"></div>
            <button onclick="confirmDisconnect()" class="w-full text-left tap-card">
                ${card(`<p class="text-[13px] font-bold text-red-500">🔌 Disconnect Telegram</p>`, 'hover:border-red-300')}
            </button>
        `;
    }

    boot();
</script>

</body>
</html>
