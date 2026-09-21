<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performix</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        *, body { font-family: 'Inter', sans-serif; }
        .soft-card {
            box-shadow: 0 14px 26px -10px rgba(107,63,42,.28), 0 4px 10px rgba(107,63,42,.14), inset 0 1px 0 rgba(255,255,255,.7);
        }
        .soft-card-sm { box-shadow: 0 6px 14px -6px rgba(107,63,42,.22), inset 0 1px 0 rgba(255,255,255,.6); }
        .tap-card { transition: border-color .15s, background .15s; }
        .nav-btn { transition: all .15s; color: #64748b; }
        .nav-btn.active { background: #F5EAE0; color: #6B3F2A; }
        .nav-btn:not(.active):hover { background: #F8FAFC; color: #334155; }
        .kanban-dragover { background: #F5EAE0; outline: 2px dashed #6B3F2A; outline-offset: -2px; }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">
<div class="p-6 space-y-4">

{{-- CONNECT BANNER — Telegram is only needed for reminders/notifications, --}}
{{-- not for using Performix itself, so it's a dismissible prompt rather   --}}
{{-- than a gate blocking the whole app (task management shouldn't wait on --}}
{{-- a Telegram round trip). Same connect/status endpoints as Account      --}}
{{-- Settings. Dismissal is remembered locally so it doesn't nag every     --}}
{{-- visit; it reappears automatically once actually linked disappears.    --}}
@if(!$telegramLinked)
<div id="tg-banner" class="hidden bg-white rounded-2xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
    <div class="w-9 h-9 rounded-full bg-[#229ED9]/10 flex items-center justify-center shrink-0">
        <svg viewBox="0 0 24 24" class="w-4.5 h-4.5" fill="#229ED9"><path d="M21.94 4.53a1.6 1.6 0 0 0-1.63-.27L2.98 10.98a1.53 1.53 0 0 0 .1 2.88l4.54 1.42 1.76 5.5c.14.44.5.72.94.72.03 0 .06 0 .1-.01.34-.03.63-.24.77-.55l2.15-3.9 4.5 3.3c.24.18.53.27.82.27.14 0 .29-.02.43-.07a1.5 1.5 0 0 0 1-1.1l3.03-13.7a1.6 1.6 0 0 0-.62-1.74Zm-3.35 2.68-8.03 7.28-.31 3.35-1.35-4.22 8.6-6.9c.2-.16.42.1.24.28l-6.9 6.24a.5.5 0 0 0-.15.3l-.2 2.13 8.6-9.7c.2-.23.5.03.33.24Z"/></svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-[12px] font-black text-slate-900">Connect Telegram for reminders</p>
        <p id="tg-gate-text" class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Optional — Performix works fully without it, but linking lets us send you task and KPI reminders.</p>
    </div>
    <button id="tg-gate-btn" type="button" onclick="connectTelegramGate()" class="text-[11px] font-black px-4 py-2 rounded-xl bg-[#6B9080] text-white hover:bg-[#5a7a6d] transition shrink-0 whitespace-nowrap">
        Connect
    </button>
    <button type="button" onclick="dismissTelegramBanner()" class="text-slate-300 hover:text-slate-500 shrink-0 text-[16px] leading-none px-1" title="Dismiss">&times;</button>
</div>

<script>
    const TG_CSRF = '{{ csrf_token() }}';
    let tgGatePollTimer = null;
    const TG_BANNER_DISMISS_KEY = 'performixTgBannerDismissed';

    if (!localStorage.getItem(TG_BANNER_DISMISS_KEY)) {
        document.getElementById('tg-banner').classList.remove('hidden');
    }

    function dismissTelegramBanner() {
        localStorage.setItem(TG_BANNER_DISMISS_KEY, '1');
        document.getElementById('tg-banner').classList.add('hidden');
        if (tgGatePollTimer) { clearInterval(tgGatePollTimer); tgGatePollTimer = null; }
    }

    async function refreshTelegramGateStatus() {
        const res = await fetch('{{ route("settings.telegram.status") }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.linked) {
            if (tgGatePollTimer) { clearInterval(tgGatePollTimer); tgGatePollTimer = null; }
            localStorage.removeItem(TG_BANNER_DISMISS_KEY);
            document.getElementById('tg-gate-text').textContent = 'Connected! Reloading…';
            window.location.reload();
        }
    }

    async function connectTelegramGate() {
        const res = await fetch('{{ route("settings.telegram.connect") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': TG_CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();

        window.open(data.deep_link, '_blank');
        document.getElementById('tg-gate-text').textContent = 'Waiting for confirmation in Telegram…';
        document.getElementById('tg-gate-btn').textContent = 'Reconnect';

        let attempts = 0;
        if (tgGatePollTimer) clearInterval(tgGatePollTimer);
        tgGatePollTimer = setInterval(async () => {
            attempts++;
            await refreshTelegramGateStatus();
            if (attempts >= 40) { clearInterval(tgGatePollTimer); tgGatePollTimer = null; } // ~2 min at 3s
        }, 3000);
    }
</script>
@endif

<div class="flex flex-col md:flex-row gap-4 items-start">
    <nav class="w-full md:w-44 md:shrink-0 bg-white rounded-2xl border border-slate-200 shadow-sm p-2 flex md:flex-col gap-1.5 overflow-x-auto md:overflow-visible">
        <button id="tab-home" onclick="switchTab('home')" class="nav-btn active w-full flex items-center px-3 py-2.5 rounded-xl text-[12px] font-black text-left whitespace-nowrap">Home</button>
        <button id="tab-kpis" onclick="switchTab('kpis')" class="nav-btn w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl text-[12px] font-black text-left whitespace-nowrap">
            <span>My KPIs</span>
            <span id="kpi-alert-badge" class="hidden min-w-[18px] h-[18px] rounded-full bg-red-500 text-white text-[9px] font-black flex items-center justify-center px-1 shrink-0 shadow-lg shadow-red-500/30"></span>
        </button>
        <button id="tab-todo" onclick="switchTab('todo')" class="nav-btn w-full flex items-center px-3 py-2.5 rounded-xl text-[12px] font-black text-left whitespace-nowrap">To-Do</button>
        <button id="tab-score" onclick="switchTab('score')" class="nav-btn w-full flex items-center px-3 py-2.5 rounded-xl text-[12px] font-black text-left whitespace-nowrap">Score</button>
        @if($hasTeam)
        <button id="tab-team" onclick="switchTab('team')" class="nav-btn w-full flex items-center px-3 py-2.5 rounded-xl text-[12px] font-black text-left whitespace-nowrap">Team</button>
        @endif
    </nav>

    <div id="contentCol" class="flex-1 min-w-0 space-y-3">
        <div id="toast" class="hidden px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-semibold"></div>

        <div id="app" class="space-y-3">
            <p class="text-center text-slate-400 text-[12px] mt-10">Loading…</p>
        </div>
    </div>
</div>

</div>
</main>

<script>
const _csrfToken = '{{ csrf_token() }}';
const CURRENT_EMPLOYEE_ID = '{{ session('employee.id') }}';

async function api(path, opts = {}) {
    const res = await fetch('/mini-app/api' + path, {
        ...opts,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken, ...(opts.headers || {}) },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(data.message || 'Request failed');
        err.status = res.status; err.data = data;
        throw err;
    }
    return data;
}

function showToast(message) {
    const t = document.getElementById('toast');
    t.textContent = message;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 4000);
}

function formatUnit(value, unit) {
    const n = Number(value || 0);
    if (unit === 'currency') return 'RM ' + n.toLocaleString(undefined, { maximumFractionDigits: 0 });
    if (unit === 'percentage') return n.toLocaleString(undefined, { maximumFractionDigits: 2 }) + '%';
    return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
}

// Same category order/colors, status labels, and achievement bands used on
// the web dashboard (resources/views/dashboard.blade.php,
// kpi/my-department-kpi.blade.php) and mirrored 1:1 by the Telegram Mini
// App — kept identical here so a KPI's status/score reads the same
// everywhere in the system, not a mini-app-only interpretation.
const CATEGORY_ORDER = ['Financial', 'Growth & Customer', 'Initiatives', 'People'];
const CATEGORY_COLORS = {
    'Financial':         { catPill: 'bg-emerald-700 text-white', subPill: 'bg-emerald-100 text-emerald-700' },
    'Growth & Customer': { catPill: 'bg-indigo-700 text-white',  subPill: 'bg-indigo-100 text-indigo-700' },
    'Initiatives':       { catPill: 'bg-amber-600 text-white',   subPill: 'bg-amber-100 text-amber-700' },
    'People':            { catPill: 'bg-pink-700 text-white',    subPill: 'bg-pink-100 text-pink-700' },
};
const DEFAULT_CATEGORY_COLOR = { catPill: 'bg-slate-600 text-white', subPill: 'bg-slate-100 text-slate-600' };

function sortByCategoryAndSub(items) {
    return [...items].sort((a, b) => {
        const ai = CATEGORY_ORDER.indexOf(a.category); const bi = CATEGORY_ORDER.indexOf(b.category);
        const catDiff = (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
        if (catDiff !== 0) return catDiff;
        return (a.sub_category || '').localeCompare(b.sub_category || '');
    });
}

const STATUS_LABELS = {
    completed:   { label: 'Completed',   color: 'bg-[#F5EAE0] text-[#6B3F2A]' },
    on_track:    { label: 'On Track',    color: 'bg-emerald-100 text-emerald-700' },
    at_risk:     { label: 'At Risk',     color: 'bg-yellow-100 text-yellow-700' },
    in_trouble:  { label: 'In Trouble',  color: 'bg-red-100 text-red-700' },
    not_started: { label: 'Not Started', color: 'bg-slate-100 text-slate-500' },
};

function achvBadge(score) {
    if (score >= 90) return { label: 'Excellent', color: 'bg-emerald-100 text-emerald-700', bar: 'from-emerald-400 to-green-500', ring: '#10B981' };
    if (score >= 75) return { label: 'Good',      color: 'bg-[#F5EAE0] text-[#6B3F2A]',     bar: 'from-[#8B5E4A] to-[#6B3F2A]', ring: '#6B3F2A' };
    if (score >= 50) return { label: 'Watch',     color: 'bg-yellow-100 text-yellow-700',   bar: 'from-yellow-400 to-amber-500', ring: '#F59E0B' };
    return              { label: 'Critical', color: 'bg-red-100 text-red-700',       bar: 'from-red-400 to-rose-500', ring: '#EF4444' };
}

// A circular "at a glance" ring of a KPI's achievement score — same visual
// language as the Telegram Mini App, instead of just printing a number.
function progressRing(scoreRaw) {
    const badge = achvBadge(scoreRaw);
    const score = Math.max(0, Math.min(100, scoreRaw));
    const r = 24, c = 2 * Math.PI * r;
    const offset = c - (score / 100) * c;
    return `
        <svg width="56" height="56" viewBox="0 0 60 60" class="shrink-0">
            <circle cx="30" cy="30" r="${r}" fill="none" stroke="#EFE3C7" stroke-width="6"/>
            <circle cx="30" cy="30" r="${r}" fill="none" stroke="${badge.ring}" stroke-width="6"
                stroke-linecap="round" stroke-dasharray="${c}" stroke-dashoffset="${offset}"
                transform="rotate(-90 30 30)"/>
            <text x="30" y="35" text-anchor="middle" font-size="12" font-weight="900" fill="#1e293b">${Math.round(scoreRaw)}%</text>
        </svg>
    `;
}

function card(inner, extra = '') {
    return `<div class="bg-[#FFFCF4] rounded-2xl soft-card border-2 border-[#D9C4A0] p-4 ${extra}">${inner}</div>`;
}

// Red counter on the "My KPIs" tab — same visual language as the sidebar's
// notification bell badge — counting distinct KPIs that need attention
// (not logged today, or status at_risk/in_trouble), so it's visible from
// whichever tab the user is currently on.
function updateKpiAlertBadge(count) {
    const badge = document.getElementById('kpi-alert-badge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

let currentTab = 'home';
function switchTab(tab) {
    currentTab = tab;
    ['home', 'kpis', 'todo', 'score', 'team'].forEach(t => {
        const el = document.getElementById('tab-' + t);
        if (el) el.classList.toggle('active', t === tab);
    });
    // Home's stat-card/sidebar grid is designed to use the full width next
    // to the nav; the other tabs are simple card lists that read better at
    // a capped width instead of stretching edge-to-edge on wide monitors.
    document.getElementById('contentCol')?.classList.toggle('max-w-2xl', tab !== 'home');
    if (tab === 'home') renderHome();
    if (tab === 'kpis') renderMyKpis();
    if (tab === 'todo') renderTodo();
    if (tab === 'score') renderScore('monthly');
    if (tab === 'team') renderTeam();
}

/* ---------------------------------------------------------------- */
/* MY KPIS + REMINDER BANNER                                         */
/* Value mapping is pulled straight from MiniAppController — same     */
/* kpis/kpi_quarters rows, same achievement/status fields the rest    */
/* of the system uses, nothing computed independently here.           */
/* ---------------------------------------------------------------- */

async function renderMyKpis() {
    const app = document.getElementById('app');
    app.innerHTML = `<p class="text-center text-slate-400 text-[12px] mt-10">Loading…</p>`;

    let openData, summaryData;
    try {
        [openData, summaryData] = await Promise.all([api('/kpis/open'), api('/kpis/summary')]);
    } catch (e) {
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load your KPIs.</p>`);
        return;
    }

    const notLogged = (openData.kpis || []).filter(k => !k.already_logged_today);
    const warningKpiIds = (summaryData.kpis || [])
        .filter(k => ['at_risk', 'in_trouble'].includes(k.status))
        .map(k => k.kpi_id);
    const alertKpiIds = new Set([...notLogged.map(k => k.kpi_id), ...warningKpiIds]);
    updateKpiAlertBadge(alertKpiIds.size);

    const banner = notLogged.length ? `
        <div class="rounded-2xl bg-red-50 border-2 border-red-300 px-4 py-3">
            <p class="text-[12px] font-black text-red-700">⏰ Reminder — ${notLogged.length} KPI(s) not updated today</p>
            <p class="text-[11px] text-red-600 mt-1">${notLogged.map(k => k.kpi_title).join(', ')}</p>
        </div>
    ` : `
        <div class="rounded-2xl bg-emerald-50 border-2 border-emerald-300 px-4 py-3">
            <p class="text-[12px] font-black text-emerald-700">All caught up — every open KPI updated today.</p>
        </div>
    `;

    if (!summaryData.kpis.length) {
        app.innerHTML = banner + card(`<p class="text-[13px] text-slate-600 text-center py-6 mt-3">No KPIs found for this financial year.</p>`);
        return;
    }

    window.__quarterActuals = {};
    summaryData.kpis.forEach(k => (k.quarters || []).forEach(q => { window.__quarterActuals[q.id] = q.actual; }));

    const sorted = sortByCategoryAndSub(summaryData.kpis);
    let lastCategory = null;
    let html = banner;

    sorted.forEach(k => {
        if (k.category !== lastCategory) {
            const cat = CATEGORY_COLORS[k.category] || DEFAULT_CATEGORY_COLOR;
            html += `
                <div class="flex items-center gap-2 mt-4 mb-1 px-1">
                    <p class="text-[11px] font-black uppercase tracking-wide text-[#6B3F2A]">${k.category || 'Other'}</p>
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
                        <span class="px-2 py-0.5 rounded-full ${sDef.color} text-[8px] font-black">${sDef.label}</span>
                    </div>
                    <p class="text-[14px] font-black text-slate-900 leading-snug">${k.kpi_title}</p>
                    <span class="inline-block mt-2 px-2 py-0.5 rounded-full ${aBadge.color} text-[9px] font-black">${aBadge.label}</span>
                </div>
                ${progressRing(k.achievement_percentage)}
            </div>
            <div class="w-full h-1.5 bg-[#EFE3C7] rounded-full mt-3 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r ${aBadge.bar}" style="width:${pct}%"></div>
            </div>
            <div class="flex items-center justify-between mt-1.5">
                <p class="text-[10px] text-slate-500 font-bold">Overall (Full Year)</p>
                <p class="text-[11px] text-slate-700 font-black">${formatUnit(k.actual_value, k.unit)} / ${formatUnit(annualTarget, k.unit)}</p>
            </div>
            <div class="mt-3 pt-3 border-t-2 border-dashed border-[#E3D2B0]">
                <p class="text-[9px] uppercase tracking-wide text-slate-400 font-black mb-2">By Quarter</p>
                <div class="space-y-1.5">${quarterRows || '<p class="text-[10px] text-slate-400">No quarters set up yet.</p>'}</div>
            </div>
        `) + '<div class="h-2"></div>';
    });

    app.innerHTML = html;
}

function quarterLabel(state) {
    if (state === 'current') return { text: '✏️ Update here', cls: 'bg-red-100 text-red-700' };
    if (state === 'ended') return { text: '🔒 Done', cls: 'bg-slate-100 text-slate-500' };
    return { text: '🔒 Upcoming', cls: 'bg-slate-100 text-slate-400' };
}

function quarterRow(kpiId, q, unit) {
    const isCurrent = q.state === 'current';
    const badge = achvBadge(q.achievement_percentage);
    const barPct = Math.max(0, Math.min(100, q.achievement_percentage));
    const label = quarterLabel(q.state);
    const hint = `How much did today add? Use a minus sign to reduce.`;

    const updateControl = isCurrent ? `
        <div class="mt-2.5 flex items-center gap-2">
            <input type="number" step="any" placeholder="e.g. 50 or -10" id="delta-${kpiId}"
                class="flex-1 min-w-0 text-[12px] px-3 py-2 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
            <button onclick="submitDelta('${kpiId}','${q.id}')" class="px-4 py-2 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[11px] font-black shrink-0 shadow-[0_4px_12px_rgba(22,163,74,.4)]">
                Update
            </button>
        </div>
        <p class="text-[9px] text-slate-400 mt-1">${hint}</p>
        <p id="feedback-${kpiId}" class="hidden text-[10px] font-bold mt-1.5"></p>
    ` : '';

    return `
        <div class="rounded-xl px-3 py-2.5 soft-card-sm ${isCurrent ? 'bg-red-50 border-2 border-red-500' : 'bg-[#FBF4E6] border-2 border-[#E3D2B0]'}">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-black ${isCurrent ? 'text-red-700' : 'text-slate-600'}">${q.quarter}</p>
                <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${label.cls}">${label.text}</span>
            </div>
            <div class="w-full h-1.5 bg-[#EFE3C7] rounded-full mt-2 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r ${badge.bar}" style="width:${barPct}%"></div>
            </div>
            <div class="flex items-center justify-between mt-1.5">
                <p class="text-[10px] text-slate-500">Target: <span class="font-bold text-slate-700">${formatUnit(q.target, unit)}</span></p>
                <p class="text-[10px] text-slate-500">Actual: <span class="font-bold text-slate-700">${formatUnit(q.actual, unit)}</span></p>
                <p class="text-[10px] font-black ${isCurrent ? 'text-red-700' : 'text-slate-500'}">${q.achievement_percentage}%</p>
            </div>
            ${updateControl}
        </div>
    `;
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
        await api(`/kpis/${kpiId}/quarters/${quarterId}/adjust`, { method: 'POST', body: JSON.stringify({ delta }) });
        showToast('Updated! Your KPI actual has been refreshed.');
        renderMyKpis();
    } catch (e) {
        feedback.textContent = e.data?.message || "Couldn't update — please try again.";
        feedback.className = 'text-[10px] font-bold mt-1.5 text-red-600';
        feedback.classList.remove('hidden');
    }
}

/* ---------------------------------------------------------------- */
/* HOME — a unified daily dashboard combining today's tasks, this      */
/* week's task score, and KPI alignment in one screen. Pure          */
/* presentation layer: every number comes from the same /tasks,      */
/* /tasks/score, /kpis/summary and /summaries endpoints the To-Do/    */
/* Score/My KPIs tabs already call — no new data source, and tasks    */
/* created from the Telegram bot show up here too since both         */
/* channels write to the same telegram_project_tasks table.           */
/* ---------------------------------------------------------------- */

let __homeData = null; // { tasks, weeklyScore, kpis }
let __qduStatus = 'in_progress';

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

async function renderHome() {
    const app = document.getElementById('app');
    app.innerHTML = `<p class="text-center text-slate-400 text-[12px] mt-10">Loading your day…</p>`;

    let tasksRes, weeklyScore, kpiSummary;
    try {
        [tasksRes, weeklyScore, kpiSummary] = await Promise.all([
            api('/tasks'),
            api('/tasks/score?period=weekly'),
            api('/kpis/summary'),
        ]);
    } catch (e) {
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load your dashboard.</p>`);
        return;
    }

    __homeData = {
        tasks: tasksRes.tasks || [],
        weeklyScore,
        kpis: kpiSummary.kpis || [],
    };
    // Home's Kanban board reuses the To-Do tab's own drag/drop, details, and
    // delete handlers, which all look tasks up via window.__myTasks.
    window.__myTasks = __homeData.tasks;

    app.innerHTML = `
        ${homeHeader()}
        ${homeStatCards()}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mt-3">
            <div class="lg:col-span-2 space-y-3">
                ${homeTasksSection()}
                ${homeQuickUpdateCard()}
            </div>
            <div class="space-y-3">
                <div id="homeAiInsight">${homeAiInsightLoadingCard()}</div>
                ${homeKpiAlignmentCard()}
                ${homeRemindersCard()}
            </div>
        </div>
    `;

    loadHomeAiInsight();
}

function homeHeader() {
    return `
        <div class="px-1">
            <p class="text-[16px] font-black text-slate-900">{{ $greeting }}, {{ $employeeName }}</p>
            <p class="text-[11px] text-slate-400 font-semibold mt-0.5">{{ $todayLabel }}</p>
        </div>
    `;
}

// Plain, uniform title used by every Home section — no emoji, no per-section
// color, so headings read consistently wherever they appear on the page.
function sectionHeader(label, marginClass = 'mb-2') {
    return `<p class="text-[13px] font-black text-slate-900 ${marginClass}">${label}</p>`;
}

function homeStatCard(label, value, extra = '') {
    return card(`
        <p class="text-[9px] font-black text-slate-400 uppercase tracking-wide truncate">${label}</p>
        <p class="text-[26px] font-black text-slate-900 leading-none mt-1.5">${value}</p>
        ${extra}
    `);
}

function homeStatCards() {
    const tasks = __homeData.tasks;
    const today = todayISO();
    const activeTasks = tasks.filter(t => ['not_started', 'in_progress', 'blocked'].includes(t.status));
    const dueToday = tasks.filter(t => t.due_date === today && t.status !== 'cancelled');
    const dueTodayOpen = dueToday.filter(t => t.status !== 'done');
    const dailyProgressPct = dueToday.length
        ? Math.round(dueToday.reduce((sum, t) => sum + (t.progress_percentage || 0), 0) / dueToday.length)
        : 0;
    const weeklyScoreVal = __homeData.weeklyScore.score;

    return `
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
            ${homeStatCard('Daily Progress', dueToday.length ? dailyProgressPct + '%' : '—', `
                <div class="w-full h-1.5 bg-[#EFE3C7] rounded-full mt-3 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r ${achvBadge(dailyProgressPct).bar}" style="width:${dailyProgressPct}%"></div>
                </div>
            `)}
            ${homeStatCard('Active Tasks', activeTasks.length)}
            ${homeStatCard('Due Today', dueTodayOpen.length)}
            ${homeStatCard('Task Score', weeklyScoreVal !== null ? Math.round(weeklyScoreVal) : '—')}
        </div>
    `;
}

/* Today's Tasks — the same Kanban board as the To-Do tab (KANBAN_COLUMNS/
   kanbanBoard(), defined below), so Home reads as the same product instead
   of a separate filtered list. Drag/drop, Details, and Delete all look the
   task up via window.__myTasks, which renderHome() keeps in sync. */

function homeTasksSection() {
    const tasks = __homeData.tasks;
    return card(`
        ${sectionHeader("Today's Tasks")}
        ${tasks.length ? kanbanBoard(tasks) : `<p class="text-[12px] text-slate-400 text-center py-6">No tasks yet.</p>`}
    `);
}

/* Quick Daily Update — the evening check-in flow (status/progress/note) */
/* pulled up to Home so it doesn't need a trip into each task's Details  */
/* page. Deliberately offers only the 3 common states; blocked/          */
/* cancelled + reschedule stay in the full Task Details daily-update     */
/* form for the less common cases.                                       */

function normalizeQduStatus(status) {
    return ['not_started', 'in_progress', 'done'].includes(status) ? status : 'in_progress';
}

function homeQuickUpdateCard() {
    const activeTasks = __homeData.tasks.filter(t => t.status !== 'done' && t.status !== 'cancelled');

    if (!activeTasks.length) {
        return `<div id="qduCard">${card(`
            ${sectionHeader('Quick Daily Update')}
            <p class="text-[12px] text-slate-500 text-center py-4">No active tasks to update.</p>
            <button onclick="renderNewTaskForm(true)" class="w-full py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black">+ Add an unplanned task</button>
        `)}</div>`;
    }

    const first = activeTasks[0];
    __qduStatus = normalizeQduStatus(first.status);

    const statusBtn = (s) => `
        <button type="button" onclick="setQduStatus('${s}')" data-qdu-status="${s}"
            class="qdu-status-btn py-2.5 rounded-xl border-2 text-[11px] font-black ${__qduStatus === s ? 'border-[#6B3F2A] bg-[#F5EAE0] text-[#6B3F2A]' : 'border-[#D9C4A0] text-slate-500'}">
            ${STATUS_PILL[s].label}
        </button>
    `;

    return `<div id="qduCard">${card(`
        ${sectionHeader('Quick Daily Update', 'mb-3')}

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Selected task</p>
                <select id="qduTaskSelect" onchange="onQduTaskChange()" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
                    ${activeTasks.map(t => `<option value="${t.id}">${t.title}</option>`).join('')}
                </select>

                <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Status</p>
                <div class="grid grid-cols-3 gap-2">
                    ${statusBtn('not_started')}${statusBtn('in_progress')}${statusBtn('done')}
                </div>

                <div class="flex items-center justify-between mt-3">
                    <p class="text-[10px] font-bold text-slate-600">Progress</p>
                    <p class="text-[11px] font-black text-[#6B3F2A]"><span id="qduProgressValue">${first.progress_percentage || 0}</span>%</p>
                </div>
                <input type="range" id="qduProgressInput" min="0" max="100" value="${first.progress_percentage || 0}"
                    oninput="document.getElementById('qduProgressValue').textContent = this.value" class="w-full accent-[#6B3F2A]">
            </div>

            <div class="flex flex-col">
                <p class="text-[10px] font-bold text-slate-600 mb-1">What did you complete today?</p>
                <textarea id="qduNoteInput" rows="4" maxlength="500" oninput="document.getElementById('qduNoteCount').textContent = this.value.length"
                    placeholder="Share a brief update…" class="w-full flex-1 text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500 resize-none"></textarea>
                <p class="text-[9px] text-slate-400 text-right mt-0.5"><span id="qduNoteCount">0</span> / 500</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-2 mt-4">
            <button onclick="renderNewTaskForm(true)" class="text-[11px] font-bold text-[#6B3F2A] shrink-0">+ Add an unplanned task</button>
            <button onclick="submitQuickDailyUpdate()" class="px-5 py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black shrink-0">Submit Update</button>
        </div>
        <p id="qduFeedback" class="hidden text-[10px] font-bold mt-2 text-center"></p>
    `)}</div>`;
}

function setQduStatus(s) {
    __qduStatus = s;
    document.querySelectorAll('.qdu-status-btn').forEach(b => {
        const active = b.dataset.qduStatus === s;
        b.classList.toggle('border-[#6B3F2A]', active);
        b.classList.toggle('bg-[#F5EAE0]', active);
        b.classList.toggle('text-[#6B3F2A]', active);
        b.classList.toggle('border-[#D9C4A0]', !active);
        b.classList.toggle('text-slate-500', !active);
    });
}

function onQduTaskChange() {
    const id = document.getElementById('qduTaskSelect').value;
    const t = __homeData.tasks.find(x => x.id === id);
    if (!t) return;
    setQduStatus(normalizeQduStatus(t.status));
    document.getElementById('qduProgressInput').value = t.progress_percentage || 0;
    document.getElementById('qduProgressValue').textContent = t.progress_percentage || 0;
    document.getElementById('qduNoteInput').value = '';
    document.getElementById('qduNoteCount').textContent = '0';
}

async function submitQuickDailyUpdate() {
    const feedback = document.getElementById('qduFeedback');
    const taskId = document.getElementById('qduTaskSelect').value;
    const progress = Number(document.getElementById('qduProgressInput').value);
    const note = document.getElementById('qduNoteInput').value.trim() || null;

    feedback.classList.add('hidden');

    try {
        await api(`/tasks/${taskId}/daily-update`, {
            method: 'POST',
            body: JSON.stringify({ status: __qduStatus, progress, note }),
        });
        showToast('Daily update saved!');
        renderHome();
    } catch (e) {
        feedback.textContent = e.data?.message || "Couldn't save — please try again.";
        feedback.classList.remove('hidden');
    }
}

/* Sidebar — AI Daily Insight (lazy-generated, same ai_summaries table    */
/* the Score tab's weekly summary already writes to, just period=daily), */
/* KPI Alignment (straight from /kpis/summary, no new computation), and   */
/* Upcoming Reminders (each task's own reminder_at, nothing fabricated).  */

function homeAiInsightLoadingCard() {
    return card(`${sectionHeader('AI Daily Insight')}<p class="text-[11px] text-slate-400">Loading…</p>`);
}

async function loadHomeAiInsight() {
    const box = document.getElementById('homeAiInsight');
    if (!box) return;
    try {
        const data = await api('/summaries?scope=employee&period=daily');
        box.innerHTML = data.summary ? homeAiInsightCard(data.summary) : homeAiInsightEmptyCard();
    } catch (e) {
        box.innerHTML = homeAiInsightEmptyCard();
    }
}

function homeAiInsightCard(summary) {
    const facts = summary.facts || {};
    const total = facts.scored_task_count ?? 0;
    const completed = facts.completed_count ?? 0;
    const attention = (facts.overdue_count ?? 0) + (facts.blocked_count ?? 0);
    return card(`
        ${sectionHeader('AI Daily Insight')}
        <p class="text-[11px] text-slate-600 leading-relaxed">${summary.narrative}</p>
        <div class="mt-2.5 space-y-1">
            <p class="text-[10px] text-emerald-700 font-bold">✓ ${completed} of ${total} tasks updated</p>
            ${attention > 0 ? `<p class="text-[10px] text-amber-700 font-bold">⚠ ${attention} task(s) need attention</p>` : ''}
        </div>
        <button onclick="generateHomeAiInsight()" class="mt-2.5 w-full py-2 rounded-xl bg-[#F5EAE0] text-[#6B3F2A] text-[10px] font-black">↻ Refresh Insight</button>
    `, 'bg-gradient-to-br from-[#FFFCF4] to-[#FBF0E0]');
}

function homeAiInsightEmptyCard() {
    return card(`
        ${sectionHeader('AI Daily Insight')}
        <p class="text-[11px] text-slate-500">No insight generated yet today.</p>
        <button onclick="generateHomeAiInsight()" class="mt-2 w-full py-2 rounded-xl bg-[#6B3F2A] hover:bg-[#5a341f] text-white text-[10px] font-black">Generate Insight</button>
    `);
}

async function generateHomeAiInsight() {
    const box = document.getElementById('homeAiInsight');
    if (!box) return;
    box.innerHTML = card(`${sectionHeader('AI Daily Insight')}<p class="text-[11px] text-slate-400">Generating…</p>`);
    try {
        const data = await api('/summaries/regenerate', { method: 'POST', body: JSON.stringify({ scope: 'employee', period: 'daily' }) });
        box.innerHTML = homeAiInsightCard(data.summary);
    } catch (e) {
        box.innerHTML = card(`${sectionHeader('AI Daily Insight')}<p class="text-[11px] text-red-500">${e.data?.message || "Couldn't generate right now."}</p>`);
    }
}

function homeKpiAlignmentCard() {
    const kpis = __homeData.kpis;
    if (!kpis.length) {
        return card(`${sectionHeader('KPI Alignment')}<p class="text-[11px] text-slate-400">No KPIs set up for this financial year.</p>`);
    }
    const rows = kpis.map(k => {
        const pct = Math.max(0, Math.min(100, k.achievement_percentage));
        return `
            <div class="mt-2.5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[10px] font-bold text-slate-600 truncate min-w-0">${k.kpi_title}</p>
                    <p class="text-[10px] font-black text-slate-700 shrink-0">${pct.toFixed(0)}%</p>
                </div>
                <div class="w-full h-1.5 bg-[#EFE3C7] rounded-full mt-1 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r ${achvBadge(pct).bar}" style="width:${pct}%"></div>
                </div>
            </div>
        `;
    }).join('');
    return card(`
        ${sectionHeader('KPI Alignment')}
        ${rows}
        <p class="text-[9px] text-slate-400 mt-3 leading-relaxed">Task activity supports KPI tracking but does not update KPI Actual automatically.</p>
    `);
}

function homeRemindersCard() {
    const now = new Date();
    const upcoming = __homeData.tasks
        .filter(t => t.reminder_at && new Date(t.reminder_at) >= now && !['done', 'cancelled'].includes(t.status))
        .sort((a, b) => new Date(a.reminder_at) - new Date(b.reminder_at))
        .slice(0, 5);

    if (!upcoming.length) {
        return card(`${sectionHeader('Upcoming Reminders')}<p class="text-[11px] text-slate-400">No reminders scheduled.</p>`);
    }

    const rows = upcoming.map(t => {
        const when = new Date(t.reminder_at).toLocaleString(undefined, { weekday: 'short', hour: 'numeric', minute: '2-digit' });
        return `
            <div class="mt-2">
                <p class="text-[11px] font-bold text-slate-700 truncate">${t.title}</p>
                <p class="text-[9px] text-slate-400">${when}</p>
            </div>
        `;
    }).join('');

    return card(`${sectionHeader('Upcoming Reminders')}${rows}`);
}

/* ---------------------------------------------------------------- */
/* TO-DO LIST — a personal to-do list separate from KPI actuals. A    */
/* task can optionally be tied to a KPI purely for visibility — doing */
/* so never changes that KPI's official actual (only the "My KPIs"    */
/* update box does that). Full CRUD: create, edit, log progress,      */
/* delete — each action notifies you (in-app + Telegram if linked).   */
/* ---------------------------------------------------------------- */

async function renderTodo() {
    const app = document.getElementById('app');
    app.innerHTML = `<p class="text-center text-slate-400 text-[12px] mt-10">Loading your to-dos…</p>`;

    let data;
    try {
        data = await api('/tasks');
    } catch (e) {
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load your to-dos.</p>`);
        return;
    }

    window.__myTasks = data.tasks || [];

    const header = `
        <div class="flex items-center gap-2">
            <button onclick="renderNewTaskForm()" class="flex-1 py-3 rounded-2xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black shadow-[0_6px_16px_rgba(22,163,74,.35)]">➕ New Task</button>
            <button onclick="renderCalendar()" class="px-4 py-3 rounded-2xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black" title="Calendar">📅</button>
        </div>
        <div id="taskScoreCard" class="mt-3"></div>
    `;

    const board = !window.__myTasks.length
        ? `<div class="mt-3">${card(`<p class="text-[13px] text-slate-600 text-center py-6">No to-dos yet — tap "New Task" to start your list.</p>`)}</div>`
        : kanbanBoard(window.__myTasks);

    app.innerHTML = header + board;
    loadTaskScoreCard();
}

/* Kanban board, grouped by status -- each column is just STATUS_PILL's own
   set, so a column never drifts out of sync with what a task's status
   dropdown actually offers. No drag-and-drop between columns (status still
   changes via the Update Task form) -- this is a "see everything grouped,
   drill into one" board, not a full trello-style rewrite. */
const KANBAN_COLUMNS = [
    { key: 'not_started', label: 'Not Started' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'blocked', label: 'Blocked' },
    { key: 'done', label: 'Done' },
    { key: 'cancelled', label: 'Cancelled' },
];

function kanbanBoard(tasks) {
    const byStatus = {};
    KANBAN_COLUMNS.forEach(col => { byStatus[col.key] = []; });
    tasks.forEach(t => (byStatus[t.status] || byStatus.not_started).push(t));

    const columns = KANBAN_COLUMNS.map(col => {
        const colTasks = byStatus[col.key];
        const pill = STATUS_PILL[col.key];
        return `
            <div class="min-w-0">
                <div class="flex items-center justify-between px-1 mb-2">
                    <span class="text-[10px] font-black px-2 py-0.5 rounded-full ${pill.color}">${col.label}</span>
                    <span class="text-[10px] font-bold text-slate-400">${colTasks.length}</span>
                </div>
                <div class="kanban-col space-y-2 min-h-[64px] rounded-xl p-1 -m-1 transition-colors"
                    ondragover="event.preventDefault(); this.classList.add('kanban-dragover')"
                    ondragleave="this.classList.remove('kanban-dragover')"
                    ondrop="onDropTaskCard(event, '${col.key}')">
                    ${colTasks.length ? colTasks.map(t => taskCard(t)).join('') : `<p class="text-[10px] text-slate-400 text-center py-4">No tasks</p>`}
                </div>
            </div>
        `;
    }).join('');

    return `<div class="mt-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">${columns}</div>`;
}

function onDragStartTaskCard(event, taskId) {
    event.dataTransfer.setData('text/plain', taskId);
    event.dataTransfer.effectAllowed = 'move';
}

function onDropTaskCard(event, newStatus) {
    event.preventDefault();
    event.currentTarget.classList.remove('kanban-dragover');
    const taskId = event.dataTransfer.getData('text/plain');
    const t = (window.__myTasks || []).find(x => x.id === taskId);
    if (!t || t.status === newStatus) return;
    moveTaskCard(taskId, newStatus);
}

async function moveTaskCard(taskId, newStatus) {
    try {
        await api(`/tasks/${taskId}/daily-update`, { method: 'POST', body: JSON.stringify({ status: newStatus }) });
        showToast(`Moved to ${(STATUS_PILL[newStatus] || {}).label || newStatus}.`);
        if (currentTab === 'home') renderHome(); else renderTodo();
    } catch (e) {
        showToast(e.data?.message || "Couldn't move the task — please try again.");
    }
}

/* ---------------------------------------------------------------- */
/* TASK SCORE — this week's precomputed-on-demand score, with an AI   */
/* summary the user can generate/refresh. Sits above the task list    */
/* rather than as its own tab, so it's visible right where it matters.*/
/* ---------------------------------------------------------------- */

function scoreStatusBand(status) {
    if (status === 'on_track') return { label: 'On Track', color: 'bg-emerald-100 text-emerald-700' };
    if (status === 'at_risk') return { label: 'At Risk', color: 'bg-amber-100 text-amber-700' };
    if (status === 'critical') return { label: 'Critical', color: 'bg-red-100 text-red-700' };
    return { label: 'Not enough data yet', color: 'bg-slate-100 text-slate-500' };
}

async function loadTaskScoreCard() {
    const el = document.getElementById('taskScoreCard');
    if (!el) return;

    let score;
    try {
        score = await api('/tasks/score?period=weekly');
    } catch (e) {
        return;
    }

    const band = scoreStatusBand(score.status);

    el.innerHTML = card(`
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">This Week's Task Score</p>
                <p class="text-[24px] font-black text-slate-900 leading-none mt-1">${score.score !== null ? Math.round(score.score) : '—'}<span class="text-[12px] font-bold text-slate-400">/100</span></p>
                <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full ${band.color} text-[9px] font-black">${band.label}</span>
            </div>
            <button onclick="toggleTaskSummary()" class="text-[10px] font-black text-[#6B3F2A] bg-[#F5EAE0] px-3 py-1.5 rounded-full shrink-0">✨ AI Summary</button>
        </div>
        <div id="taskSummaryBox" class="hidden mt-3 pt-3 border-t border-slate-200"></div>
    `, 'bg-gradient-to-br from-[#FFFCF4] to-[#FBF0E0]');
}

let __summaryLoaded = false;
async function toggleTaskSummary() {
    const box = document.getElementById('taskSummaryBox');
    box.classList.toggle('hidden');
    if (box.classList.contains('hidden') || __summaryLoaded) return;

    box.innerHTML = `<p class="text-[11px] text-slate-400">Loading…</p>`;

    try {
        const data = await api('/summaries?scope=employee&period=weekly');
        if (data.summary) {
            box.innerHTML = summaryBlock(data.summary);
        } else {
            box.innerHTML = `
                <p class="text-[11px] text-slate-500">No summary generated yet for this week.</p>
                <button onclick="generateTaskSummary()" class="mt-2 px-3 py-1.5 rounded-lg bg-[#6B3F2A] hover:bg-[#5a341f] text-white text-[10px] font-black">Generate now</button>
            `;
        }
        __summaryLoaded = true;
    } catch (e) {
        box.innerHTML = `<p class="text-[11px] text-red-500">Could not load a summary right now.</p>`;
    }
}

function summaryBlock(summary) {
    const recs = (summary.facts?.recommendations || []).map(r => `<li class="text-[10px] text-slate-600 mt-1">• ${r}</li>`).join('');
    return `
        <p class="text-[11px] text-slate-700 leading-relaxed">${summary.narrative}</p>
        ${recs ? `<ul class="mt-2">${recs}</ul>` : ''}
        <button onclick="generateTaskSummary()" class="mt-2 text-[10px] font-bold text-[#6B3F2A]">↻ Regenerate</button>
    `;
}

async function generateTaskSummary() {
    const box = document.getElementById('taskSummaryBox');
    box.innerHTML = `<p class="text-[11px] text-slate-400">Generating…</p>`;
    try {
        const data = await api('/summaries/regenerate', { method: 'POST', body: JSON.stringify({ scope: 'employee', period: 'weekly' }) });
        box.innerHTML = summaryBlock(data.summary);
    } catch (e) {
        box.innerHTML = `<p class="text-[11px] text-red-500">${e.data?.message || "Couldn't generate a summary right now."}</p>`;
    }
}

const STATUS_PILL = {
    not_started: { label: 'Not Started', color: 'bg-slate-100 text-slate-500' },
    in_progress: { label: 'In Progress', color: 'bg-amber-100 text-amber-700' },
    done: { label: 'Done', color: 'bg-emerald-100 text-emerald-700' },
    blocked: { label: 'Blocked', color: 'bg-red-100 text-red-700' },
    cancelled: { label: 'Cancelled', color: 'bg-slate-100 text-slate-400' },
};

function dueDateBadge(dueDate) {
    if (!dueDate) return '';
    const isOverdue = dueDate < new Date().toISOString().slice(0, 10);
    return `<span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${isOverdue ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500'}">${isOverdue ? '⚠ ' : ''}Due ${dueDate}</span>`;
}

function fmtTime12(hhmm) {
    if (!hhmm) return '';
    const [h, m] = hhmm.split(':').map(Number);
    const period = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return `${h12}:${String(m).padStart(2, '0')} ${period}`;
}

// A meeting is a task with a specific time-of-day -- shown in place of the
// plain due-date badge when set, since a scheduled time is the more useful
// fact once it exists.
function taskTimeBadge(t) {
    if (t.meeting_time) {
        return `<span class="text-[8px] font-black px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">🕐 ${fmtTime12(t.meeting_time)}</span>`;
    }
    return dueDateBadge(t.due_date);
}

// "Who assign" -- a chip naming the assignee whenever a task isn't simply
// assigned to yourself, so a delegated task reads clearly on the board.
function taskAssigneeBadge(t) {
    if (!t.assignee_name || t.assignee_employee_id === CURRENT_EMPLOYEE_ID) return '';
    return `<span class="text-[8px] font-black px-1.5 py-0.5 rounded-full bg-[#CCE3DE] text-[#1a3d34]">👤 ${t.assignee_name}</span>`;
}

/* Collapsed by default -- just enough to scan a whole column at a glance
   (title, priority, due date, progress bar). Tapping the card opens it in
   place to show the numbers/KPI links/actions, instead of every card
   eating that much vertical space all the time. */
function taskCard(t) {
    const pct = t.target > 0 ? Math.max(0, Math.min(100, (t.actual / t.target) * 100)) : 0;
    const badge = achvBadge(pct);
    const priorityPill = PRIORITY_LABELS[t.priority] || PRIORITY_LABELS.medium;
    const kpiChips = (t.linked_kpis || []).length
        ? `<div class="flex flex-wrap gap-1.5 mt-2">${t.linked_kpis.map(k => `<span class="px-2 py-0.5 rounded-full bg-[#CCE3DE] text-[#1a3d34] text-[8px] font-black">${k.kpi_title}</span>`).join('')}</div>`
        : '';
    const safeId = t.id.replace(/[^a-zA-Z0-9_-]/g, '');

    return `
        <div draggable="true" ondragstart="onDragStartTaskCard(event,'${t.id}')" class="bg-[#FFFCF4] rounded-2xl soft-card border-2 border-[#D9C4A0] overflow-hidden cursor-grab active:cursor-grabbing">
            <button type="button" onclick="toggleTaskCard('${safeId}')" class="w-full text-left p-3">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[12px] font-black text-slate-900 leading-snug min-w-0">${t.title}</p>
                    <span id="task-chevron-${safeId}" class="text-slate-400 text-[10px] shrink-0 mt-0.5">▸</span>
                </div>
                <div class="flex flex-wrap gap-1.5 mt-1.5">
                    <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${priorityPill.color}">${priorityPill.label}</span>
                    ${taskTimeBadge(t)}
                    ${taskAssigneeBadge(t)}
                </div>
                <div class="w-full h-1.5 bg-[#EFE3C7] rounded-full mt-2 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r ${badge.bar}" style="width:${pct}%"></div>
                </div>
            </button>
            <div id="task-body-${safeId}" class="hidden px-3 pb-3">
                <div class="flex items-center justify-between pt-1 border-t border-[#EFE3C7]">
                    <p class="text-[10px] text-slate-500 pt-1.5">Target: <span class="font-bold text-slate-700">${formatUnit(t.target, t.unit)}</span></p>
                    <p class="text-[10px] text-slate-500 pt-1.5">Actual: <span class="font-bold text-slate-700">${formatUnit(t.actual, t.unit)}</span></p>
                    <p class="text-[10px] font-black text-slate-700 pt-1.5">${pct.toFixed(0)}%</p>
                </div>
                ${kpiChips}
                <div class="flex items-center gap-2 mt-3">
                    <button onclick="window.__taskDetailBackTo='${currentTab === 'home' ? 'home' : 'todo'}'; renderTaskDetail('${t.id}')" class="flex-1 py-2 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[11px] font-black">Details</button>
                    <button onclick="confirmDeleteTask('${t.id}')" class="px-3 py-2 rounded-xl bg-white border-2 border-red-300 text-red-600 text-[11px] font-black">🗑️</button>
                </div>
            </div>
        </div>
    `;
}

function toggleTaskCard(safeId) {
    const body = document.getElementById(`task-body-${safeId}`);
    const chevron = document.getElementById(`task-chevron-${safeId}`);
    if (!body) return;
    const opening = body.classList.contains('hidden');
    body.classList.toggle('hidden');
    if (chevron) chevron.textContent = opening ? '▾' : '▸';
}

const PRIORITY_LABELS = {
    low: { label: 'Low', color: 'bg-slate-100 text-slate-600' },
    medium: { label: 'Medium', color: 'bg-[#F5EAE0] text-[#6B3F2A]' },
    high: { label: 'High', color: 'bg-amber-100 text-amber-700' },
    critical: { label: 'Critical', color: 'bg-red-100 text-red-700' },
};

function taskFormFields(t) {
    return `
        <p class="text-[10px] font-bold text-slate-600 mb-1">Task title</p>
        <input type="text" id="taskTitleInput" value="${t?.title || ''}" placeholder="e.g. Follow up with client"
            class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">

        <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Description <span class="text-slate-400 font-normal">(optional)</span></p>
        <textarea id="taskDescriptionInput" rows="2" placeholder="Any extra context…"
            class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500 resize-none">${t?.description || ''}</textarea>

        <div class="grid grid-cols-2 gap-2 mt-3">
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Priority</p>
                <select id="taskPriorityInput" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
                    ${Object.entries(PRIORITY_LABELS).map(([key, p]) => `<option value="${key}" ${(t?.priority || 'medium') === key ? 'selected' : ''}>${p.label}</option>`).join('')}
                </select>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Due date <span class="text-slate-400 font-normal">(optional)</span></p>
                <input type="date" id="taskDueDateInput" value="${t?.due_date || ''}"
                    class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
            </div>
        </div>

        <div class="mt-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="taskIsMeetingInput" ${t?.meeting_time ? 'checked' : ''} onchange="onTaskMeetingToggled()" class="w-4 h-4 accent-[#6B3F2A]">
                <span class="text-[11px] font-bold text-slate-600">This is a scheduled meeting — set a time</span>
            </label>
            <input type="time" id="taskMeetingTimeInput" value="${t?.meeting_time || ''}"
                class="${t?.meeting_time ? '' : 'hidden'} w-full mt-2 text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
        </div>

        <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Assign to</p>
        <select id="taskAssigneeInput" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
            <option value="">Loading…</option>
        </select>

        <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Unit</p>
        <select id="taskUnitInput" onchange="onTaskUnitChanged()" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
            <option value="number" ${t?.unit === 'number' ? 'selected' : ''}>Number</option>
            <option value="currency" ${t?.unit === 'currency' ? 'selected' : ''}>Currency (RM)</option>
            <option value="percentage" ${t?.unit === 'percentage' ? 'selected' : ''}>Percentage (%)</option>
        </select>

        <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Target</p>
        <input type="number" step="any" min="0" id="taskTargetInput" value="${t?.target ?? ''}" placeholder="e.g. 10"
            class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
    `;
}

/* Mirrors the Telegram Mini App's "Align to KPI (optional)" picker on its
   own Create Task screen (renderCreateTask() in telegram/app.blade.php) --
   same backend (/mini-app/api/tasks/kpi-options, telegram_project_tasks),
   so a KPI linked here shows up there and vice versa. Kept out of
   taskFormFields()/taskFormValues() deliberately: those are shared with
   Edit Task, but the update() endpoint (PATCH /tasks/{id}) doesn't accept
   kpi_ids -- only the dedicated /tasks/{id}/link-kpis endpoint does, which
   the task-detail screen's own "Edit KPI Links" already covers.

   Checkboxes, not a <select>, so a task can be aligned to more than one
   KPI at once -- a single task legitimately counts toward several KPIs
   (e.g. a client call that's both a "Client Retention" and a "Revenue"
   activity). The "+ Create a new KPI" link opens in a new tab specifically
   so it never discards whatever's already been typed into this form --
   without that, the only way to park a task under a KPI that doesn't exist
   yet was to abandon the task draft, go create the KPI, then start over. */
function taskKpiField() {
    return `
        <div class="flex items-center justify-between mt-3 mb-1">
            <p class="text-[10px] font-bold text-slate-600">Align to KPI <span class="text-slate-400 font-normal">(optional, pick any that apply)</span></p>
            <button type="button" onclick="onTaskUnitChanged()" class="text-[9px] font-black text-[#6B3F2A] shrink-0">🔄 Refresh</button>
        </div>
        <div id="taskKpiOptions" class="space-y-1.5">
            <p class="text-[11px] text-slate-400">Loading KPIs…</p>
        </div>
        <a href="/kpi/create" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-black text-[#6B3F2A] mt-1.5">+ Create a new KPI (opens in a new tab, won't lose this task)</a>
    `;
}

async function onTaskUnitChanged() {
    const container = document.getElementById('taskKpiOptions');
    if (!container) return; // not on this form (e.g. Edit Task, which doesn't have this field)

    const unit = document.getElementById('taskUnitInput').value;
    const previouslyChecked = new Set([...document.querySelectorAll('.task-kpi-checkbox:checked')].map(el => el.value));
    container.innerHTML = `<p class="text-[11px] text-slate-400">Loading KPIs…</p>`;

    try {
        const data = await api(`/tasks/kpi-options?unit=${unit}`);
        const options = data.kpis || [];
        container.innerHTML = options.length
            ? options.map(k => `
                <label class="flex items-center gap-2 px-3 py-2 rounded-xl border-2 border-[#D9C4A0] bg-white cursor-pointer">
                    <input type="checkbox" value="${k.kpi_id}" class="task-kpi-checkbox w-4 h-4 accent-[#16A34A] shrink-0" ${previouslyChecked.has(k.kpi_id) ? 'checked' : ''}>
                    <span class="text-[12px] font-bold text-slate-700 min-w-0">${k.kpi_title}</span>
                </label>
            `).join('')
            : `<p class="text-[11px] text-slate-400">No open KPIs with a matching "${unit}" unit right now. Create one, then tap Refresh.</p>`;
    } catch (e) {
        container.innerHTML = `<p class="text-[11px] text-red-500">Couldn't load KPIs — tap Refresh to try again.</p>`;
    }
}

function onTaskMeetingToggled() {
    const checked = document.getElementById('taskIsMeetingInput').checked;
    document.getElementById('taskMeetingTimeInput').classList.toggle('hidden', !checked);
}

function taskFormValues() {
    const isMeeting = document.getElementById('taskIsMeetingInput')?.checked;
    return {
        title: document.getElementById('taskTitleInput').value.trim(),
        description: document.getElementById('taskDescriptionInput').value.trim() || null,
        priority: document.getElementById('taskPriorityInput').value,
        due_date: document.getElementById('taskDueDateInput').value || null,
        meeting_time: isMeeting ? (document.getElementById('taskMeetingTimeInput').value || null) : null,
        assignee_employee_id: document.getElementById('taskAssigneeInput')?.value || null,
        unit: document.getElementById('taskUnitInput').value,
        target: document.getElementById('taskTargetInput').value,
    };
}

function renderNewTaskForm(isUnplanned = false) {
    window.__newTaskIsUnplanned = isUnplanned;
    const cancelTo = isUnplanned ? 'renderHome()' : 'renderTodo()';
    document.getElementById('app').innerHTML = card(`
        <p class="text-[14px] font-black text-slate-900 mb-3">${isUnplanned ? 'Add Unplanned Task' : 'New Task'}</p>
        ${taskFormFields(null)}
        ${taskKpiField()}
        <p class="text-[10px] text-slate-400 mt-3">Optionally align this task to a KPI above, or link one later from Edit.</p>
        <div class="flex items-center gap-2 mt-4">
            <button onclick="${cancelTo}" class="flex-1 py-2.5 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black">Cancel</button>
            <button onclick="saveNewTask()" class="flex-1 py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black">Save Task</button>
        </div>
        <p id="taskFormFeedback" class="hidden text-[10px] font-bold text-red-600 mt-2 text-center"></p>
    `);
    onTaskUnitChanged();
    loadAssignableEmployees({ assignee_employee_id: CURRENT_EMPLOYEE_ID });
}

async function saveNewTask() {
    const feedback = document.getElementById('taskFormFeedback');
    const v = taskFormValues();
    const kpiIds = [...document.querySelectorAll('.task-kpi-checkbox:checked')].map(el => el.value);

    if (!v.title || v.target === '' || isNaN(Number(v.target)) || Number(v.target) < 0) {
        feedback.textContent = 'Enter a task title and a valid target.';
        feedback.classList.remove('hidden');
        return;
    }

    try {
        await api('/tasks', {
            method: 'POST',
            body: JSON.stringify({ ...v, target: Number(v.target), is_unplanned: !!window.__newTaskIsUnplanned, kpi_ids: kpiIds }),
        });
        showToast('Task saved!');
        if (window.__newTaskIsUnplanned) renderHome(); else renderTodo();
    } catch (e) {
        feedback.textContent = e.data?.message || "Couldn't save — please try again.";
        feedback.classList.remove('hidden');
    }
}

function renderEditTask(taskId) {
    const t = (window.__taskDetail && window.__taskDetail.id === taskId) ? window.__taskDetail : (window.__myTasks || []).find(x => x.id === taskId);
    if (!t) { renderTodo(); return; }

    document.getElementById('app').innerHTML = card(`
        <p class="text-[14px] font-black text-slate-900 mb-3">Edit Task</p>
        ${taskFormFields(t)}
        <div class="flex items-center gap-2 mt-4">
            <button onclick="renderTaskDetail('${taskId}')" class="flex-1 py-2.5 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black">Cancel</button>
            <button onclick="saveEditTask('${taskId}')" class="flex-1 py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black">Save Changes</button>
        </div>
        <p id="taskFormFeedback" class="hidden text-[10px] font-bold text-red-600 mt-2 text-center"></p>
    `);
    loadAssignableEmployees(t);
}

async function saveEditTask(taskId) {
    const feedback = document.getElementById('taskFormFeedback');
    const v = taskFormValues();

    if (!v.title || v.target === '' || isNaN(Number(v.target)) || Number(v.target) < 0) {
        feedback.textContent = 'Enter a task title and a valid target.';
        feedback.classList.remove('hidden');
        return;
    }

    try {
        await api(`/tasks/${taskId}`, { method: 'PATCH', body: JSON.stringify({ ...v, target: Number(v.target) }) });
        showToast('Task updated!');
        renderTaskDetail(taskId);
    } catch (e) {
        feedback.textContent = e.data?.message || "Couldn't save — please try again.";
        feedback.classList.remove('hidden');
    }
}

/* ---------------------------------------------------------------- */
/* TASK DETAILS — quick numeric update, the evening-style daily       */
/* update (status/progress/blocked-note/reschedule), KPI alignment    */
/* with an optional AI suggestion, and the full update history.       */
/* ---------------------------------------------------------------- */

function updateHistoryRow(u) {
    const when = (u.created_at || '').replace('T', ' ').slice(0, 16);
    const parts = [];
    if (u.status_at_update) parts.push(`marked <b>${(STATUS_PILL[u.status_at_update] || {}).label || u.status_at_update}</b>`);
    if (u.progress_at_update !== null && u.progress_at_update !== undefined) parts.push(`${u.progress_at_update}% progress`);
    if (Number(u.delta) !== 0) parts.push(`${u.delta >= 0 ? '+' : ''}${u.delta} added (now ${u.new_actual})`);
    if (u.note) parts.push(`note: "${u.note}"`);
    if (u.reschedule_reason) parts.push(`rescheduled: "${u.reschedule_reason}"`);

    return `
        <div class="py-2 border-b border-slate-100 last:border-0">
            <p class="text-[11px] text-slate-600 leading-relaxed">${parts.join(' · ') || 'Logged an update'}</p>
            <p class="text-[9px] text-slate-400 mt-0.5">${when}</p>
        </div>
    `;
}

async function renderTaskDetail(taskId) {
    const app = document.getElementById('app');
    app.innerHTML = `<p class="text-center text-slate-400 text-[12px] mt-10">Loading…</p>`;

    let data;
    try {
        data = await api(`/tasks/${taskId}`);
    } catch (e) {
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load this task.</p>`) + `<button onclick="${window.__taskDetailBackTo === 'home' ? 'renderHome()' : 'renderTodo()'}" class="w-full mt-3 py-2 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black">← Back</button>`;
        return;
    }

    window.__taskDetail = data.task;
    window.__taskUpdates = data.updates || [];

    const t = data.task;
    const pct = t.target > 0 ? Math.max(0, Math.min(100, (t.actual / t.target) * 100)) : 0;
    const badge = achvBadge(pct);
    const statusPill = STATUS_PILL[t.status] || STATUS_PILL.not_started;
    const priorityPill = PRIORITY_LABELS[t.priority] || PRIORITY_LABELS.medium;

    const kpiChips = (t.linked_kpis || []).length
        ? t.linked_kpis.map(k => `
            <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-[#CCE3DE] mt-1.5">
                <p class="text-[11px] font-black text-[#1a3d34] min-w-0">${k.kpi_title}${k.ai_suggested ? ' 🤖' : ''}</p>
                <button onclick="removeKpiLink('${k.kpi_id}')" class="text-[10px] font-black text-[#1a3d34]/60 hover:text-[#1a3d34] shrink-0">✕</button>
            </div>
        `).join('')
        : `<p class="text-[11px] text-slate-400 mt-1.5">Not linked to a KPI yet.</p>`;

    const backTo = window.__taskDetailBackTo === 'home' ? { fn: 'renderHome()', label: 'Home' } : { fn: 'renderTodo()', label: 'To-Do' };

    app.innerHTML = `
        <button onclick="${backTo.fn}" class="text-[11px] font-bold text-[#6B3F2A] mb-1">← Back to ${backTo.label}</button>

        ${card(`
            <div class="flex items-center justify-between gap-2">
                <p class="text-[14px] font-black text-slate-900 leading-snug min-w-0">${t.title}</p>
                <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full shrink-0 ${statusPill.color}">${statusPill.label}</span>
            </div>
            ${t.description ? `<p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">${t.description}</p>` : ''}
            <div class="flex flex-wrap gap-1.5 mt-2">
                <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${priorityPill.color}">${priorityPill.label} priority</span>
                ${dueDateBadge(t.due_date)}
            </div>
            <div class="w-full h-1.5 bg-[#EFE3C7] rounded-full mt-3 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r ${badge.bar}" style="width:${pct}%"></div>
            </div>
            <div class="flex items-center justify-between mt-1.5">
                <p class="text-[10px] text-slate-500">Target: <span class="font-bold text-slate-700">${formatUnit(t.target, t.unit)}</span></p>
                <p class="text-[10px] text-slate-500">Actual: <span class="font-bold text-slate-700">${formatUnit(t.actual, t.unit)}</span></p>
                <p class="text-[10px] font-black text-slate-700">${pct.toFixed(0)}%</p>
            </div>
            <div class="flex items-center gap-2 mt-3">
                <button onclick="renderEditTask('${t.id}')" class="flex-1 py-2 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[11px] font-black">✏️ Edit Details</button>
            </div>
        `)}

        <div class="h-2"></div>
        ${card(`
            <p class="text-[12px] font-black text-slate-900 mb-2">Update Task</p>

            <p class="text-[10px] font-bold text-slate-600 mb-1">Quick number update <span class="text-slate-400 font-normal">(optional)</span></p>
            <input type="number" step="any" placeholder="e.g. 5 or -1" id="taskDeltaInput"
                class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
            <p class="text-[9px] text-slate-400 mt-1">Use a minus sign to reduce, or leave blank to skip.</p>

            <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Status</p>
            <select id="dailyStatusInput" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
                ${Object.entries(STATUS_PILL).map(([key, s]) => `<option value="${key}" ${t.status === key ? 'selected' : ''}>${s.label}</option>`).join('')}
            </select>

            <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Remark <span class="text-slate-400 font-normal">(optional)</span></p>
            <textarea id="dailyNoteInput" rows="2" placeholder="Any notes about this update…" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500 resize-none"></textarea>

            <p class="text-[10px] font-bold text-slate-600 mt-3 mb-1">Assign To</p>
            <select id="taskAssigneeInput" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
                <option value="">Loading…</option>
            </select>

            <button onclick="submitTaskUpdate('${t.id}')" class="w-full mt-4 py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black">Save Update</button>
            <p id="taskUpdateFeedback" class="hidden text-[10px] font-bold mt-2 text-center"></p>
        `)}

        <div class="h-2"></div>
        ${card(`
            <div class="flex items-center justify-between">
                <p class="text-[12px] font-black text-slate-900">KPI alignment</p>
                <button onclick="requestKpiSuggestion('${t.id}')" class="text-[10px] font-black text-[#6B3F2A] bg-[#F5EAE0] px-2.5 py-1 rounded-full">🤖 Suggest with AI</button>
            </div>
            ${kpiChips}
            <p id="kpiSuggestionBox" class="hidden mt-2"></p>
            <button onclick="renderLinkTaskKpis('${t.id}')" class="w-full mt-2 py-2 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[11px] font-black">✏️ Edit KPI Links</button>
        `)}

        <div class="h-2"></div>
        ${card(`
            <p class="text-[12px] font-black text-slate-900 mb-1">History</p>
            <div>${window.__taskUpdates.length ? window.__taskUpdates.map(updateHistoryRow).join('') : '<p class="text-[11px] text-slate-400 py-2">No updates logged yet.</p>'}</div>
        `)}

        <div class="h-2"></div>
        <button onclick="confirmDeleteTask('${t.id}')" class="w-full py-2.5 rounded-xl bg-white border-2 border-red-300 text-red-600 text-[12px] font-black">🗑️ Delete Task</button>
    `;

    loadAssignableEmployees(t);
}

async function loadAssignableEmployees(t) {
    const select = document.getElementById('taskAssigneeInput');
    if (!select) return;

    try {
        const data = await api('/tasks/assignable');
        const employees = data.employees || [];
        select.innerHTML = employees.length
            ? employees.map(e => `<option value="${e.id}" ${e.id === t.assignee_employee_id ? 'selected' : ''}>${e.short_name}</option>`).join('')
            : `<option value="">—</option>`;
    } catch (e) {
        select.innerHTML = `<option value="">—</option>`;
    }
}

/* The single "Save Update" action -- replaces what used to be two separate
   cards/buttons (a "Quick number update" Add button, and a "Daily update"
   Save button), which was confusing since either one alone looked like a
   complete save. Applies the delta (if any) first, then saves status/
   remark/assignee in one call, computing "progress" from the resulting
   actual/target ratio itself -- there's no manual Progress(%) input
   anymore, since that duplicated the auto-calculated percentage already
   shown at the top of this page. */
async function submitTaskUpdate(taskId) {
    const feedback = document.getElementById('taskUpdateFeedback');
    const t = window.__taskDetail;
    const deltaRaw = document.getElementById('taskDeltaInput').value.trim();
    const status = document.getElementById('dailyStatusInput').value;
    const note = document.getElementById('dailyNoteInput').value.trim() || null;
    const assigneeId = document.getElementById('taskAssigneeInput')?.value || null;

    feedback.classList.add('hidden');

    let newActual = Number(t.actual);
    if (deltaRaw !== '') {
        if (isNaN(Number(deltaRaw)) || Number(deltaRaw) === 0) {
            feedback.textContent = 'Enter a non-zero amount for the quick number update, or leave it blank.';
            feedback.classList.remove('hidden');
            return;
        }
        const delta = Number(deltaRaw);
        if (Number(t.actual) + delta < 0) {
            feedback.textContent = `Can't reduce — this task's actual is only ${t.actual}.`;
            feedback.classList.remove('hidden');
            return;
        }
        try {
            const res = await api(`/tasks/${taskId}/progress`, { method: 'POST', body: JSON.stringify({ delta }) });
            newActual = res.task_actual;
        } catch (e) {
            feedback.textContent = e.data?.message || "Couldn't update — please try again.";
            feedback.classList.remove('hidden');
            return;
        }
    }

    const progress = t.target > 0 ? Math.max(0, Math.min(100, (newActual / t.target) * 100)) : 0;

    try {
        await api(`/tasks/${taskId}/daily-update`, {
            method: 'POST',
            body: JSON.stringify({ status, progress, note, assignee_employee_id: assigneeId }),
        });
        showToast('Task updated!');
        renderTaskDetail(taskId);
    } catch (e) {
        feedback.textContent = e.data?.message || "Couldn't save — please try again.";
        feedback.classList.remove('hidden');
    }
}

async function requestKpiSuggestion(taskId) {
    const box = document.getElementById('kpiSuggestionBox');
    box.classList.remove('hidden');
    box.innerHTML = `<span class="text-[10px] text-slate-400">Thinking…</span>`;

    try {
        const data = await api(`/tasks/${taskId}/kpi-suggestion`, { method: 'POST' });
        if (!data.suggestion) {
            box.innerHTML = `<span class="text-[10px] text-slate-500">No confident match found among your KPIs — you can leave this unlinked.</span>`;
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
        await api(`/tasks/${taskId}/link-kpis`, {
            method: 'POST',
            body: JSON.stringify({
                kpi_ids: [...existingIds, suggestion.kpi_id],
                ai_suggested: true,
                ai_confidence: suggestion.confidence,
                ai_reason: suggestion.reason,
            }),
        });
        showToast('KPI linked!');
        renderTaskDetail(taskId);
    } catch (e) {
        showToast(e.data?.message || "Couldn't link — please try again.");
    }
}

async function removeKpiLink(kpiId) {
    const t = window.__taskDetail;
    const remaining = (t.linked_kpis || []).map(k => k.kpi_id).filter(id => id !== kpiId);
    try {
        await api(`/tasks/${t.id}/link-kpis`, { method: 'POST', body: JSON.stringify({ kpi_ids: remaining }) });
        renderTaskDetail(t.id);
    } catch (e) {
        showToast(e.data?.message || "Couldn't update KPI links.");
    }
}

/* Full add/remove/change picker for an EXISTING task's KPI links -- the ✕ on
   each chip above only removes one at a time; this is the "actually change
   which KPI(s) this is aligned to" screen, mirroring the Telegram Mini App's
   own renderLinkTaskKpis()/saveLinkKpis() (telegram/app.blade.php) so both
   surfaces offer the same capability. */
async function renderLinkTaskKpis(taskId) {
    const t = (window.__taskDetail && window.__taskDetail.id === taskId) ? window.__taskDetail : (window.__myTasks || []).find(x => x.id === taskId);
    if (!t) { renderTodo(); return; }

    const app = document.getElementById('app');
    app.innerHTML = `<p class="text-center text-slate-400 text-[12px] mt-10">Loading KPIs…</p>`;

    let data;
    try {
        data = await api(`/tasks/kpi-options?unit=${t.unit}`);
    } catch (e) {
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load your KPIs.</p>`) + `<button onclick="renderTaskDetail('${taskId}')" class="w-full mt-3 py-2 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black">← Back</button>`;
        return;
    }

    const linkedIds = new Set((t.linked_kpis || []).map(k => k.kpi_id));
    const sortedKpis = sortByCategoryAndSub(data.kpis || []);

    const rows = sortedKpis.map(k => {
        const cat = CATEGORY_COLORS[k.category] || DEFAULT_CATEGORY_COLOR;
        const checked = linkedIds.has(k.kpi_id) ? 'checked' : '';
        return `
            <label class="block cursor-pointer">
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-white border-2 border-[#D9C4A0]">
                    <input type="checkbox" value="${k.kpi_id}" class="kpi-link-checkbox w-5 h-5 accent-[#16A34A] shrink-0" ${checked}>
                    <div class="min-w-0">
                        <span class="px-2 py-0.5 rounded-full ${cat.catPill} text-[8px] font-black">${k.category || '-'}</span>
                        <p class="text-[13px] font-black text-slate-900 mt-1">${k.kpi_title}</p>
                    </div>
                </div>
            </label>
        `;
    }).join('<div class="h-1.5"></div>') || `<p class="text-[12px] text-slate-500 text-center py-6">No open KPIs with a matching "${t.unit}" unit right now.</p>`;

    app.innerHTML = card(`
        <div class="flex items-center justify-between mb-1">
            <p class="text-[13px] font-black text-slate-900">Edit KPI Links</p>
            <button onclick="renderLinkTaskKpis('${taskId}')" class="text-[9px] font-black text-[#6B3F2A] shrink-0">🔄 Refresh</button>
        </div>
        <p class="text-[11px] text-slate-500 mb-3">Task "<b>${t.title}</b>" — tick which KPI(s) to align this to, untick to remove. Doesn't change any KPI's actual — for visibility only.</p>
        <a href="/kpi/create" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-black text-[#6B3F2A] mb-3">+ Create a new KPI (opens in a new tab)</a>
        <div>${rows}</div>
    `) + `
        <div class="flex items-center gap-2 mt-4">
            <button onclick="renderTaskDetail('${taskId}')" class="flex-1 py-2.5 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black">Cancel</button>
            <button onclick="saveLinkKpis('${taskId}')" class="flex-1 py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black">Save Links</button>
        </div>
        <p id="linkKpisFeedback" class="hidden text-[10px] font-bold text-red-600 mt-2 text-center"></p>
    `;
}

async function saveLinkKpis(taskId) {
    const feedback = document.getElementById('linkKpisFeedback');
    const kpiIds = [...document.querySelectorAll('.kpi-link-checkbox:checked')].map(el => el.value);

    try {
        await api(`/tasks/${taskId}/link-kpis`, { method: 'POST', body: JSON.stringify({ kpi_ids: kpiIds }) });
        showToast('KPI links updated!');
        renderTaskDetail(taskId);
    } catch (e) {
        feedback.textContent = e.data?.message || "Couldn't save — please try again.";
        feedback.classList.remove('hidden');
    }
}

function confirmDeleteTask(taskId) {
    const t = (window.__taskDetail && window.__taskDetail.id === taskId) ? window.__taskDetail : (window.__myTasks || []).find(x => x.id === taskId);
    if (!t) return;
    if (!confirm(`Delete "${t.title}"? This can't be undone.`)) return;

    api(`/tasks/${taskId}`, { method: 'DELETE' })
        .then(() => { showToast('Task deleted.'); if (currentTab === 'home') renderHome(); else renderTodo(); })
        .catch(e => showToast(e.data?.message || "Couldn't delete — please try again."));
}

/* ---------------------------------------------------------------- */
/* MONTHLY SCORE — AI-generated score + narrative, read-only, already */
/* generated on a schedule (TelegramReviewService). Defaults to        */
/* "monthly" here since that's the primary ask, but weekly/quarterly   */
/* are one tap away, same as the Telegram version.                     */
/* ---------------------------------------------------------------- */

const REVIEW_PERIODS = [
    { key: 'weekly', label: 'Weekly' },
    { key: 'monthly', label: 'Monthly' },
    { key: 'quarterly', label: 'Quarterly' },
];

function reviewBand(score) {
    if (score >= 90) return { label: 'Excellent', cls: 'text-emerald-800 border-emerald-700' };
    if (score >= 75) return { label: 'Good', cls: 'text-slate-700 border-slate-400' };
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
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">${r.period_label}</p>
        <p class="text-[28px] font-black text-slate-900 mt-1 leading-none">${Math.round(r.score)}<span class="text-[13px] font-bold text-slate-400">/100</span></p>
        <span class="inline-block mt-2 px-2 py-0.5 rounded-full border text-[9px] font-bold ${band.cls}">${band.label}</span>
        <p class="text-[12px] text-slate-600 leading-relaxed mt-3 pt-3 border-t border-slate-200">${r.narrative}</p>
    `);
}

async function renderScore(periodType) {
    periodType = periodType || 'monthly';
    const app = document.getElementById('app');

    const tabs = `
        <div class="flex items-center gap-1 border-b-2 border-slate-200 mb-4">
            ${REVIEW_PERIODS.map(p => `
                <button onclick="renderScore('${p.key}')"
                    class="flex-1 pb-2.5 text-[12px] font-bold ${p.key === periodType ? 'text-slate-900 border-b-2 border-slate-900 -mb-[2px]' : 'text-slate-400'}">
                    ${p.label}
                </button>
            `).join('')}
        </div>
    `;

    app.innerHTML = tabs + `<p class="text-center text-slate-400 text-[12px] mt-10">Loading…</p>`;

    let data;
    try {
        data = await api(`/reviews?period=${periodType}`);
    } catch (e) {
        app.innerHTML = tabs + card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load your review.</p>`);
        return;
    }

    window.__reviewHistory = data.history || [];

    if (!data.latest) {
        app.innerHTML = tabs + card(`
            <p class="text-[13px] font-bold text-slate-900 mb-1.5">No review yet</p>
            <p class="text-[12px] text-slate-500 leading-relaxed">${reviewEmptyNote(periodType)}</p>
        `);
        return;
    }

    const historyRows = window.__reviewHistory.length ? `
        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-bold mt-4 mb-1.5 px-1">Previous periods</p>
        <div>${window.__reviewHistory.map((r, i) => `
            <button onclick="renderReviewDetail(${i})" class="w-full text-left tap-card">
                ${card(`<div class="flex items-center justify-between gap-2"><p class="text-[12px] font-bold text-slate-700">${r.period_label}</p><p class="text-[12px] font-black text-slate-500">${Math.round(r.score)}/100</p></div>`, 'hover:border-slate-400')}
            </button>
        `).join('<div class="h-1.5"></div>')}</div>
    ` : '';

    app.innerHTML = tabs + reviewCard(data.latest) + historyRows;
}

function renderReviewDetail(index) {
    const r = (window.__reviewHistory || [])[index];
    if (!r) { renderScore('monthly'); return; }
    document.getElementById('app').innerHTML = reviewCard(r);
}

/* ---------------------------------------------------------------- */
/* CALENDAR — month view of To-Do due dates, built from the same       */
/* task list already loaded on the To-Do tab (no extra API call).     */
/* ---------------------------------------------------------------- */

let __calendarCursor = new Date();
__calendarCursor.setDate(1);

function renderCalendar() {
    const app = document.getElementById('app');
    const tasks = window.__myTasks || [];

    const year = __calendarCursor.getFullYear();
    const month = __calendarCursor.getMonth();
    const monthLabel = __calendarCursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    const firstDow = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const tasksByDate = {};
    tasks.forEach(t => {
        if (!t.due_date) return;
        (tasksByDate[t.due_date] = tasksByDate[t.due_date] || []).push(t);
    });

    let cells = '';
    for (let i = 0; i < firstDow; i++) cells += `<div></div>`;
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const dayTasks = tasksByDate[dateStr] || [];
        const hasMeeting = dayTasks.some(x => x.meeting_time);
        const isToday = dateStr === new Date().toISOString().slice(0, 10);
        cells += `
            <button onclick="renderCalendarDay('${dateStr}')" class="aspect-square rounded-lg flex flex-col items-center justify-center relative ${isToday ? 'bg-[#F5EAE0] font-black' : 'hover:bg-slate-50'}">
                <span class="text-[11px] ${isToday ? 'text-[#6B3F2A]' : 'text-slate-600'}">${d}</span>
                ${dayTasks.length ? `<span class="w-1.5 h-1.5 rounded-full ${hasMeeting ? 'bg-indigo-500' : 'bg-[#6B9080]'} absolute bottom-1"></span>` : ''}
            </button>
        `;
    }

    const dow = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    app.innerHTML = `
        <button onclick="renderTodo()" class="text-[11px] font-bold text-[#6B3F2A] mb-1">← Back to To-Do</button>
        ${card(`
            <div class="flex items-center justify-between mb-3">
                <button onclick="shiftCalendar(-1)" class="px-2 py-1 text-[13px] font-black text-slate-500">‹</button>
                <p class="text-[13px] font-black text-slate-900">${monthLabel}</p>
                <button onclick="shiftCalendar(1)" class="px-2 py-1 text-[13px] font-black text-slate-500">›</button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center mb-1">
                ${dow.map(d => `<p class="text-[9px] font-black text-slate-400">${d}</p>`).join('')}
            </div>
            <div class="grid grid-cols-7 gap-1">${cells}</div>
        `)}
        <div id="calendarDayTasks" class="mt-3"></div>
    `;
}

function shiftCalendar(delta) {
    __calendarCursor.setMonth(__calendarCursor.getMonth() + delta);
    renderCalendar();
}

function renderCalendarDay(dateStr) {
    const dayTasks = (window.__myTasks || [])
        .filter(t => t.due_date === dateStr)
        .sort((a, b) => (a.meeting_time || '99:99').localeCompare(b.meeting_time || '99:99'));
    const box = document.getElementById('calendarDayTasks');
    if (!dayTasks.length) {
        box.innerHTML = card(`<p class="text-[11px] text-slate-400 text-center py-3">No tasks due ${dateStr}.</p>`);
        return;
    }
    box.innerHTML = `<p class="text-[10px] uppercase tracking-wide text-slate-400 font-black mb-1.5 px-1">Due ${dateStr}</p>` + dayTasks.map(t => taskCard(t)).join('<div class="h-2"></div>');
}

/* ---------------------------------------------------------------- */
/* MY TEAM — Manager/VP/SLT only. Reads already-computed weekly       */
/* task_score_snapshots for everyone TaskAccessPolicy allows this      */
/* viewer to see (docs/performix-design.md §6-R5 — no live per-member  */
/* recompute on page load), worst-first so who needs attention is      */
/* obvious immediately.                                                */
/* ---------------------------------------------------------------- */

async function renderTeam() {
    const app = document.getElementById('app');
    app.innerHTML = `<p class="text-center text-slate-400 text-[12px] mt-10">Loading your team…</p>`;

    let data;
    try {
        data = await api('/team/attention');
    } catch (e) {
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load your team.</p>`);
        return;
    }

    const members = data.members || [];

    if (!members.length) {
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">No team members found.</p>`);
        return;
    }

    const rows = members.map(m => {
        const band = scoreStatusBand(m.status);
        return card(`
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[13px] font-black text-slate-900 truncate">${m.name}</p>
                    ${m.department_code ? `<p class="text-[10px] text-slate-400">${m.department_code}</p>` : ''}
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[16px] font-black text-slate-900 leading-none">${m.score !== null ? Math.round(m.score) : '—'}</p>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded-full ${band.color} text-[8px] font-black">${band.label}</span>
                </div>
            </div>
        `);
    }).join('<div class="h-2"></div>');

    app.innerHTML = `
        <p class="text-[10px] uppercase tracking-wide text-slate-400 font-black mb-2 px-1">This week · sorted by who needs attention</p>
        ${rows}
    `;
}

if (document.getElementById('tab-home')) {
    switchTab('home');
}
</script>

</body>
</html>
