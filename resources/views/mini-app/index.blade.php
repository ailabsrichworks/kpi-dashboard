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
        .kanban-dragover { background: rgba(15,23,42,.05); outline: 2px dashed rgba(15,23,42,.25); outline-offset: -2px; }
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

<div id="contentCol" class="space-y-3">
    <div id="toast" class="hidden px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-semibold"></div>

    <div id="app" class="space-y-3">
        <p class="text-center text-slate-400 text-[12px] mt-10">Loading…</p>
    </div>
</div>

</div>
</main>

<script>
const _csrfToken = '{{ csrf_token() }}';
const CURRENT_EMPLOYEE_ID = '{{ session('employee.id') }}';
// "1st Accent" (settings.blade.php) -- this employee/company's own main
// brand colour, defaulting to the app-wide gold if never customized. Used
// as a highlight on specific elements (the primary "+ New task" button,
// "AI Summary").
const THEME_ACCENT = '{{ session('theme_accent') ?: '#D4AF37' }}';

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

// Category order/colors used by the To-Do tab's "Edit KPI Links" screen —
// mirrors the web dashboard (resources/views/dashboard.blade.php,
// kpi/my-department-kpi.blade.php) and the Telegram Mini App so a KPI's
// category reads the same everywhere in the system.
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

function achvBadge(score) {
    if (score >= 90) return { label: 'Excellent', color: 'bg-emerald-100 text-emerald-700', bar: 'from-emerald-400 to-green-500', ring: '#10B981' };
    if (score >= 75) return { label: 'Good',      color: 'bg-[#F5EAE0] text-[#6B3F2A]',     bar: 'from-[#8B5E4A] to-[#6B3F2A]', ring: '#6B3F2A' };
    if (score >= 50) return { label: 'Watch',     color: 'bg-yellow-100 text-yellow-700',   bar: 'from-yellow-400 to-amber-500', ring: '#F59E0B' };
    return              { label: 'Critical', color: 'bg-red-100 text-red-700',       bar: 'from-red-400 to-rose-500', ring: '#EF4444' };
}

function card(inner, extra = '') {
    return `<div class="bg-[#FFFCF4] rounded-2xl soft-card border-2 border-[#D9C4A0] p-4 ${extra}">${inner}</div>`;
}

/* ---------------------------------------------------------------- */
/* TO-DO LIST — a personal to-do list separate from KPI actuals. A    */
/* task can optionally be tied to a KPI purely for visibility — doing */
/* so never changes that KPI's official actual (only the "My KPIs"    */
/* update box does that). Full CRUD: create, edit, log progress,      */
/* delete — each action notifies you (in-app + Telegram if linked).   */
/* ---------------------------------------------------------------- */

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

function fmtDateShort(iso) {
    if (!iso) return '';
    return new Date(iso + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

// A light, subtly-bordered panel used inside the To-Do board (score card,
// calendar) -- distinct from the tan/cream "card()" helper the New
// Task/Edit/Details sub-screens use, but the same light theme as the rest
// of the app.
function darkCard(inner, extra = '') {
    return `<div class="bg-white border border-slate-200 rounded-2xl p-4 ${extra}">${inner}</div>`;
}

let __todoView = 'board'; // 'board' | 'calendar'

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
    renderTodoShell();
    loadTaskScoreCard();
}

function switchTodoView(view) {
    __todoView = view;
    renderTodoShell();
    loadTaskScoreCard();
}

function renderTodoShell() {
    const app = document.getElementById('app');
    const tasks = window.__myTasks || [];

    app.innerHTML = `
        <div class="rounded-3xl p-4 md:p-6 bg-white border border-slate-200 shadow-sm">
            ${todoHeader()}
            ${todoStatCards(tasks)}
            <div id="taskScoreCard" class="mt-4"></div>
            <div class="mt-4">
                ${__todoView === 'calendar'
                    ? calendarBoard()
                    : (tasks.length ? kanbanBoard(tasks) : `<p class="text-[12px] text-slate-500 text-center py-10">No tasks yet — tap "New task" to start your board.</p>`)}
            </div>
        </div>
    `;
}

function todoHeader() {
    return `
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[22px] font-black text-slate-900 leading-tight">Things To Do</p>
                <p class="text-[12px] text-slate-500 mt-1 max-w-md leading-relaxed">Day-to-day work and meetings — drag a card between stages, or switch to Calendar to see everything by date and time.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="flex items-center bg-slate-100 border border-slate-200 rounded-xl p-1">
                    <button onclick="switchTodoView('board')" class="px-3 py-1.5 rounded-lg text-[11px] font-black whitespace-nowrap ${__todoView === 'board' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'}">▦ Board</button>
                    <button onclick="switchTodoView('calendar')" class="px-3 py-1.5 rounded-lg text-[11px] font-black whitespace-nowrap ${__todoView === 'calendar' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'}">📅 Calendar</button>
                </div>
                <button onclick="renderNewTaskForm()" style="background: ${THEME_ACCENT};" class="px-4 py-2 rounded-xl text-[#1a1408] text-[12px] font-black whitespace-nowrap hover:opacity-90">+ New task</button>
            </div>
        </div>
    `;
}

function todoStatCards(tasks) {
    const today = todayISO();
    const in7 = new Date();
    in7.setDate(in7.getDate() + 7);
    const in7Str = in7.toISOString().slice(0, 10);

    const openItems = tasks.filter(t => !['done', 'cancelled'].includes(t.status)).length;
    const dueToday = tasks.filter(t => t.due_date === today && !['done', 'cancelled'].includes(t.status)).length;
    const overdue = tasks.filter(t => t.due_date && t.due_date < today && !['done', 'cancelled'].includes(t.status)).length;
    const meetingsThisWeek = tasks.filter(t => t.meeting_time && t.due_date && t.due_date >= today && t.due_date <= in7Str).length;

    const stat = (label, value, tone) => `
        <div class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5">
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-wide">${label}</p>
            <p class="text-[24px] font-black ${tone || 'text-slate-900'} leading-none mt-1.5">${value}</p>
        </div>
    `;

    return `
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
            ${stat('Open Items', openItems)}
            ${stat('Due Today', dueToday)}
            ${stat('Overdue', overdue, overdue > 0 ? 'text-rose-600' : 'text-slate-900')}
            ${stat('Meetings This Week', meetingsThisWeek)}
        </div>
    `;
}

/* Kanban board, grouped by status -- each column is just STATUS_PILL's own
   set, so a column never drifts out of sync with what a task's status
   dropdown actually offers. */
const KANBAN_COLUMNS = [
    { key: 'not_started', label: 'To Do', dot: 'bg-slate-400' },
    { key: 'in_progress', label: 'In Progress', dot: 'bg-sky-400' },
    { key: 'blocked', label: 'Blocked', dot: 'bg-amber-400' },
    { key: 'done', label: 'Done', dot: 'bg-emerald-400' },
    { key: 'cancelled', label: 'Cancelled', dot: 'bg-rose-400' },
];

function kanbanBoard(tasks) {
    const byStatus = {};
    KANBAN_COLUMNS.forEach(col => { byStatus[col.key] = []; });
    tasks.forEach(t => (byStatus[t.status] || byStatus.not_started).push(t));

    const columns = KANBAN_COLUMNS.map(col => {
        const colTasks = byStatus[col.key];
        return `
            <div class="min-w-0">
                <div class="flex items-center gap-2 px-1 mb-2.5">
                    <span class="w-2 h-2 rounded-full ${col.dot}"></span>
                    <span class="text-[11px] font-black text-slate-900 uppercase tracking-wide">${col.label}</span>
                    <span class="ml-auto text-[10px] font-bold text-slate-500 bg-slate-100 rounded-full w-5 h-5 flex items-center justify-center shrink-0">${colTasks.length}</span>
                </div>
                <div class="kanban-col space-y-2 min-h-[64px] rounded-xl p-1 -m-1 transition-colors"
                    ondragover="event.preventDefault(); this.classList.add('kanban-dragover')"
                    ondragleave="this.classList.remove('kanban-dragover')"
                    ondrop="onDropTaskCard(event, '${col.key}')">
                    ${colTasks.length ? colTasks.map(t => taskCard(t)).join('') : `<p class="text-[10px] text-slate-400 text-center py-6">No tasks</p>`}
                </div>
            </div>
        `;
    }).join('');

    return `<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">${columns}</div>`;
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
        renderTodo();
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
    if (status === 'critical') return { label: 'Critical', color: 'bg-rose-100 text-rose-700' };
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

    el.innerHTML = darkCard(`
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">This Week's Task Score</p>
                <p class="text-[24px] font-black text-slate-900 leading-none mt-1">${score.score !== null ? Math.round(score.score) : '—'}<span class="text-[12px] font-bold text-slate-400">/100</span></p>
                <span class="inline-block mt-1.5 px-2 py-0.5 rounded-full ${band.color} text-[9px] font-black">${band.label}</span>
            </div>
            <button onclick="toggleTaskSummary()" style="background: ${THEME_ACCENT};" class="text-[10px] font-black text-[#1a1408] px-3 py-1.5 rounded-full shrink-0 hover:opacity-90">✨ AI Summary</button>
        </div>
        <div id="taskSummaryBox" class="hidden mt-3 pt-3 border-t border-slate-200"></div>
    `);
}

let __summaryLoaded = false;
async function toggleTaskSummary() {
    const box = document.getElementById('taskSummaryBox');
    box.classList.toggle('hidden');
    if (box.classList.contains('hidden') || __summaryLoaded) return;

    box.innerHTML = `<p class="text-[11px] text-slate-500">Loading…</p>`;

    try {
        const data = await api('/summaries?scope=employee&period=weekly');
        if (data.summary) {
            box.innerHTML = summaryBlock(data.summary);
        } else {
            box.innerHTML = `
                <p class="text-[11px] text-slate-500">No summary generated yet for this week.</p>
                <button onclick="generateTaskSummary()" class="mt-2 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-[10px] font-black">Generate now</button>
            `;
        }
        __summaryLoaded = true;
    } catch (e) {
        box.innerHTML = `<p class="text-[11px] text-rose-600">Could not load a summary right now.</p>`;
    }
}

function summaryBlock(summary) {
    const recs = (summary.facts?.recommendations || []).map(r => `<li class="text-[10px] text-slate-500 mt-1">• ${r}</li>`).join('');
    return `
        <p class="text-[11px] text-slate-600 leading-relaxed">${summary.narrative}</p>
        ${recs ? `<ul class="mt-2">${recs}</ul>` : ''}
        <button onclick="generateTaskSummary()" class="mt-2 text-[10px] font-bold text-slate-700">↻ Regenerate</button>
    `;
}

async function generateTaskSummary() {
    const box = document.getElementById('taskSummaryBox');
    box.innerHTML = `<p class="text-[11px] text-slate-500">Generating…</p>`;
    try {
        const data = await api('/summaries/regenerate', { method: 'POST', body: JSON.stringify({ scope: 'employee', period: 'weekly' }) });
        box.innerHTML = summaryBlock(data.summary);
    } catch (e) {
        box.innerHTML = `<p class="text-[11px] text-rose-600">${e.data?.message || "Couldn't generate a summary right now."}</p>`;
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

/* Collapsed by default -- just enough to scan a whole column at a glance
   (title, priority, due date, progress bar). Tapping the card opens it in
   place to show the numbers/KPI links/actions, instead of every card
   eating that much vertical space all the time. */
const AVATAR_COLORS = ['bg-rose-600', 'bg-amber-600', 'bg-emerald-600', 'bg-sky-600', 'bg-indigo-600', 'bg-fuchsia-600'];
function avatarColorFor(seed) {
    let hash = 0;
    for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
    return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}

function initialsOf(name) {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase();
}

const PRIORITY_BORDER = {
    low: 'border-l-slate-500',
    medium: 'border-l-yellow-500',
    high: 'border-l-amber-500',
    critical: 'border-l-rose-500',
};

function taskCard(t) {
    const pct = t.target > 0 ? Math.max(0, Math.min(100, (t.actual / t.target) * 100)) : 0;
    const badge = achvBadge(pct);
    const priorityBorder = PRIORITY_BORDER[t.priority] || PRIORITY_BORDER.medium;
    const assigneeName = t.assignee_name || (t.assignee_employee_id === CURRENT_EMPLOYEE_ID ? 'You' : null);
    const isOverdue = t.due_date && t.due_date < todayISO() && !['done', 'cancelled'].includes(t.status);
    const kpiChips = (t.linked_kpis || []).length
        ? `<div class="flex flex-wrap gap-1.5 mt-2">${t.linked_kpis.map(k => `<span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[8px] font-black">${k.kpi_title}</span>`).join('')}</div>`
        : '';
    const safeId = t.id.replace(/[^a-zA-Z0-9_-]/g, '');

    return `
        <div draggable="true" ondragstart="onDragStartTaskCard(event,'${t.id}')" class="bg-white hover:bg-slate-50 rounded-xl border border-slate-200 border-l-[3px] ${priorityBorder} overflow-hidden cursor-grab active:cursor-grabbing transition-colors shadow-sm">
            <button type="button" onclick="toggleTaskCard('${safeId}')" class="w-full text-left p-3">
                <p class="text-[13px] font-bold text-slate-900 leading-snug">${t.title}</p>
                <div class="flex items-center flex-wrap gap-2 mt-2.5">
                    ${assigneeName ? `<span class="w-5 h-5 rounded-full ${avatarColorFor(t.assignee_employee_id || assigneeName)} text-white text-[8px] font-black flex items-center justify-center shrink-0">${initialsOf(assigneeName)}</span>` : ''}
                    ${t.meeting_time ? `<span class="text-[10px] font-bold text-slate-500 flex items-center gap-1">🕐 ${fmtTime12(t.meeting_time)}</span>` : ''}
                    ${t.notify_email ? `<span title="Notifies ${t.notify_email}" class="text-[10px] text-slate-400">✉️</span>` : ''}
                    ${t.due_date ? `<span class="ml-auto text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0 ${isOverdue ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500'}">${fmtDateShort(t.due_date)}${t.due_time ? ' ' + fmtTime12(t.due_time) : ''}</span>` : ''}
                </div>
                ${kpiChips}
            </button>
            <div id="task-body-${safeId}" class="hidden px-3 pb-3 pt-1 border-t border-slate-200">
                <div class="flex items-center justify-between pt-2">
                    <p class="text-[10px] text-slate-500">Target: <span class="font-bold text-slate-700">${formatUnit(t.target, t.unit)}</span></p>
                    <p class="text-[10px] text-slate-500">Actual: <span class="font-bold text-slate-700">${formatUnit(t.actual, t.unit)}</span></p>
                    <p class="text-[10px] font-black text-slate-700">${pct.toFixed(0)}%</p>
                </div>
                <div class="w-full h-1.5 bg-slate-100 rounded-full mt-2 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r ${badge.bar}" style="width:${pct}%"></div>
                </div>
                <div class="flex items-center gap-2 mt-3">
                    <button onclick="renderTaskDetail('${t.id}')" class="flex-1 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-[11px] font-black">Details</button>
                    <button onclick="confirmDeleteTask('${t.id}')" class="px-3 py-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-600 text-[11px] font-black">🗑️</button>
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

        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-3">
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
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Due time <span class="text-slate-400 font-normal">(optional)</span></p>
                <input type="time" id="taskDueTimeInput" value="${t?.due_time || ''}"
                    class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Assign to</p>
                <select id="taskAssigneeInput" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
                    <option value="">Loading…</option>
                </select>
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

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-3">
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Notify by email <span class="text-slate-400 font-normal">(optional)</span></p>
                <select id="taskNotifyEmailInput" data-current="${t?.notify_email || ''}" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
                    <option value="">Loading…</option>
                </select>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Unit</p>
                <select id="taskUnitInput" onchange="onTaskUnitChanged()" class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
                    <option value="number" ${t?.unit === 'number' ? 'selected' : ''}>Number</option>
                    <option value="currency" ${t?.unit === 'currency' ? 'selected' : ''}>Currency (RM)</option>
                    <option value="percentage" ${t?.unit === 'percentage' ? 'selected' : ''}>Percentage (%)</option>
                </select>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-600 mb-1">Target</p>
                <input type="number" step="any" min="0" id="taskTargetInput" value="${t?.target ?? ''}" placeholder="e.g. 10"
                    class="w-full text-[13px] px-3 py-2.5 rounded-xl border-2 border-[#D9C4A0] bg-white outline-none focus:border-red-500">
            </div>
        </div>
        <p class="text-[9px] text-slate-400 mt-1">Notify by email sends this task straight to that person's inbox — picked from the same list as Assign to, since only a real Performix email can be notified.</p>
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
        due_time: document.getElementById('taskDueTimeInput').value || null,
        meeting_time: isMeeting ? (document.getElementById('taskMeetingTimeInput').value || null) : null,
        assignee_employee_id: document.getElementById('taskAssigneeInput')?.value || null,
        unit: document.getElementById('taskUnitInput').value,
        target: document.getElementById('taskTargetInput').value,
        notify_email: document.getElementById('taskNotifyEmailInput')?.value.trim() || null,
    };
}

function renderNewTaskForm() {
    document.getElementById('app').innerHTML = card(`
        <p class="text-[14px] font-black text-slate-900 mb-3">New Task</p>
        ${taskFormFields(null)}
        ${taskKpiField()}
        <p class="text-[10px] text-slate-400 mt-3">Optionally align this task to a KPI above, or link one later from Edit.</p>
        <div class="flex items-center gap-2 mt-4">
            <button onclick="renderTodo()" class="flex-1 py-2.5 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black">Cancel</button>
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
            body: JSON.stringify({ ...v, target: Number(v.target), kpi_ids: kpiIds }),
        });
        showToast('Task saved!');
        renderTodo();
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
        app.innerHTML = card(`<p class="text-[13px] text-slate-600 text-center py-6">Could not load this task.</p>`) + `<button onclick="renderTodo()" class="w-full mt-3 py-2 rounded-xl bg-white border-2 border-[#D9C4A0] text-[#6B3F2A] text-[12px] font-black">← Back</button>`;
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

    app.innerHTML = `
        <button onclick="renderTodo()" class="text-[11px] font-bold text-[#6B3F2A] mb-1">← Back to To-Do</button>

        ${card(`
            <div class="flex items-center justify-between gap-2">
                <p class="text-[14px] font-black text-slate-900 leading-snug min-w-0">${t.title}</p>
                <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full shrink-0 ${statusPill.color}">${statusPill.label}</span>
            </div>
            ${t.description ? `<p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">${t.description}</p>` : ''}
            <div class="flex flex-wrap gap-1.5 mt-2">
                <span class="text-[8px] font-black px-1.5 py-0.5 rounded-full ${priorityPill.color}">${priorityPill.label} priority</span>
                ${dueDateBadge(t.due_date)}
                ${t.notify_email ? `<span class="text-[8px] font-black px-1.5 py-0.5 rounded-full bg-sky-100 text-sky-700">✉️ ${t.notify_email}</span>` : ''}
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
    const notifySelect = document.getElementById('taskNotifyEmailInput');
    if (!select && !notifySelect) return;

    let employees = [];
    try {
        const data = await api('/tasks/assignable');
        employees = data.employees || [];
    } catch (e) {
        // fall through with an empty list -- both selects below already
        // handle that by showing just their own "nobody" option.
    }

    if (select) {
        select.innerHTML = employees.length
            ? employees.map(e => `<option value="${e.id}" ${e.id === t.assignee_employee_id ? 'selected' : ''}>${e.short_name}</option>`).join('')
            : `<option value="">—</option>`;
    }

    if (notifySelect) {
        const current = notifySelect.dataset.current || '';
        const withEmail = employees.filter(e => e.email);
        notifySelect.innerHTML = `<option value="">— No one —</option>` +
            withEmail.map(e => `<option value="${e.email}" ${e.email === current ? 'selected' : ''}>${e.short_name} (${e.email})</option>`).join('');
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
        .then(() => { showToast('Task deleted.'); renderTodo(); })
        .catch(e => showToast(e.data?.message || "Couldn't delete — please try again."));
}

/* ---------------------------------------------------------------- */
/* CALENDAR — month view of To-Do due dates, rendered inside the same  */
/* dark board shell as a Board/Calendar toggle (renderTodoShell()),    */
/* built from the same task list already loaded on the To-Do tab (no   */
/* extra API call).                                                     */
/* ---------------------------------------------------------------- */

let __calendarCursor = new Date();
__calendarCursor.setDate(1);

// Color-codes each inline calendar bar by the task's priority, the same
// signal the taskCard()'s left-border already uses, so a busy month reads
// at a glance without introducing a second, unrelated color scheme.
const PRIORITY_BAR = {
    low: 'bg-slate-500',
    medium: 'bg-indigo-500',
    high: 'bg-amber-600',
    critical: 'bg-rose-600',
};

function calendarEventBar(t) {
    const time = t.meeting_time || t.due_time;
    const label = (time ? fmtTime12(time) + ' ' : '') + t.title;
    const bg = PRIORITY_BAR[t.priority] || PRIORITY_BAR.medium;
    return `<button type="button" onclick="event.stopPropagation(); renderTaskDetail('${t.id}')" class="block w-full text-left ${bg} text-white text-[8px] font-bold leading-tight px-1.5 py-0.5 rounded truncate hover:opacity-90">${label}</button>`;
}

const CALENDAR_MAX_VISIBLE = 3;

function calendarBoard() {
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
    Object.values(tasksByDate).forEach(list =>
        list.sort((a, b) => (a.meeting_time || a.due_time || '99:99').localeCompare(b.meeting_time || b.due_time || '99:99')));

    let cells = '';
    for (let i = 0; i < firstDow; i++) cells += `<div class="min-h-[88px] border border-slate-100 bg-slate-50/50"></div>`;
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const dayTasks = tasksByDate[dateStr] || [];
        const isToday = dateStr === todayISO();
        const visible = dayTasks.slice(0, CALENDAR_MAX_VISIBLE);
        const hiddenCount = dayTasks.length - visible.length;

        cells += `
            <button onclick="renderCalendarDay('${dateStr}')" class="min-h-[88px] text-left p-1.5 border border-slate-100 hover:bg-slate-50 transition-colors flex flex-col gap-1">
                <span class="text-[11px] font-bold ${isToday ? 'w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center' : 'text-slate-600'}">${d}</span>
                <div class="space-y-0.5">
                    ${visible.map(t => calendarEventBar(t)).join('')}
                    ${hiddenCount > 0 ? `<p class="text-[8px] font-bold text-slate-400 px-1.5">+${hiddenCount} more</p>` : ''}
                </div>
            </button>
        `;
    }

    const dow = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

    return `
        <div class="rounded-2xl border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-3 py-2.5 bg-slate-50 border-b border-slate-200">
                <button onclick="shiftCalendar(-1)" class="px-2 py-1 text-[13px] font-black text-slate-400 hover:text-slate-900">‹</button>
                <p class="text-[13px] font-black text-slate-900">${monthLabel}</p>
                <button onclick="shiftCalendar(1)" class="px-2 py-1 text-[13px] font-black text-slate-400 hover:text-slate-900">›</button>
            </div>
            <div class="overflow-x-auto">
                <div class="min-w-[640px]">
                    <div class="grid grid-cols-7 text-center border-b border-slate-200">
                        ${dow.map(d => `<p class="text-[9px] font-black text-slate-500 py-2">${d}</p>`).join('')}
                    </div>
                    <div class="grid grid-cols-7">${cells}</div>
                </div>
            </div>
        </div>
        <div id="calendarDayTasks" class="mt-3"></div>
    `;
}

function shiftCalendar(delta) {
    __calendarCursor.setMonth(__calendarCursor.getMonth() + delta);
    renderTodoShell();
}

function renderCalendarDay(dateStr) {
    const dayTasks = (window.__myTasks || [])
        .filter(t => t.due_date === dateStr)
        .sort((a, b) => (a.meeting_time || a.due_time || '99:99').localeCompare(b.meeting_time || b.due_time || '99:99'));
    const box = document.getElementById('calendarDayTasks');
    if (!box) return;
    if (!dayTasks.length) {
        box.innerHTML = darkCard(`<p class="text-[11px] text-slate-500 text-center py-3">No tasks due ${fmtDateShort(dateStr)}.</p>`);
        return;
    }
    box.innerHTML = `<p class="text-[10px] uppercase tracking-wide text-slate-500 font-black mb-1.5 px-1">Due ${fmtDateShort(dateStr)}</p>` + dayTasks.map(t => taskCard(t)).join('<div class="h-2"></div>');
}

if (document.getElementById('app')) {
    renderTodo();
}
</script>

</body>
</html>
