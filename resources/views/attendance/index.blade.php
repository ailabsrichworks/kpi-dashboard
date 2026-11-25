<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Attendance Import</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="/vendor/alpinejs/alpinejs-3.14.9.min.js"></script>
    <style>
        *, body { font-family: 'Inter', sans-serif; }
        .step-pill { transition: border-color .15s, background .15s; }
        .step-pill.active { border-color: #1a3d34; background: rgba(26,61,52,.05); }
        .step-pill.done .step-num { background: #6B9080; color: #fff; }
        .step-num { width:22px;height:22px;border-radius:9999px;background:#e2e8f0;color:#64748b;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .step-pill.active .step-num { background: #1a3d34; color: #fff; }
        .panel { display: none; }
        .panel.show { display: block; }
        .dropzone { border: 2px dashed #cbd5e1; transition: border-color .15s, background .15s; }
        .dropzone.drag, .dropzone:hover { border-color: #6B9080; background: #f2f7f5; }
        .time-late { color: #dc2626; font-weight: 800; }
        .status-badge { font-size:9.5px;font-weight:800;padding:2px 9px;border-radius:9999px;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap; }
        .status-present { background:#dcfce7;color:#15803d; }
        .status-absent  { background:#fee2e2;color:#b91c1c; }
        .status-leave   { background:#fef3c7;color:#92400e; }
        .status-off     { background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0; }
        .staff-row.open .chev { transform: rotate(90deg); }
        .staff-row .daily-wrap { display: none; }
        .staff-row.open .daily-wrap { display: block; }
        .conflict-card.resolved { opacity:.6; }
        .bulk-bar { display:none; }
        .bulk-bar.show { display:flex; }
        .modal-overlay { display:none; }
        .modal-overlay.show { display:flex; }
        @media print { .no-print{display:none!important;} body{background:#fff!important;} }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">
<div class="px-4 pt-4 pb-10 max-w-full">

{{-- ══ HEADER ═══════════════════════════════════════════════════════════ --}}
<div class="rounded-2xl bg-gradient-to-r from-[#0d241f] to-[#1a3d34] text-white px-6 py-5 mb-4 no-print flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-lg font-black">Attendance Import &amp; Analysis</h1>
        <p class="text-[11px] text-white/60 mt-0.5">Upload the InfoTech Daily Attendance Report CSV, review, and manage every staff member's record.</p>
    </div>
    <span class="text-[9px] font-black uppercase tracking-widest bg-white/10 border border-white/25 rounded-full px-3 py-1.5">🔒 BTS &amp; HR Access Only</span>
</div>

@if(session('error'))
<div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl no-print">{{ session('error') }}</div>
@endif

{{-- ══ STEP RAIL ════════════════════════════════════════════════════════ --}}
<div class="flex gap-2 mb-4 no-print" id="stepRail">
    <button type="button" class="step-pill active flex-1 bg-white border-2 border-[#e2e8f0] rounded-xl px-4 py-2.5 flex items-center gap-2.5 text-left" data-step="1" onclick="goStep(1)">
        <span class="step-num">1</span>
        <span><span class="block text-[12px] font-black text-slate-700">Upload</span><span class="block text-[9px] text-slate-400">Pick company &amp; CSV file</span></span>
    </button>
    <button type="button" class="step-pill flex-1 bg-white border-2 border-[#e2e8f0] rounded-xl px-4 py-2.5 flex items-center gap-2.5 text-left" data-step="2" onclick="goStep(2)">
        <span class="step-num">2</span>
        <span><span class="block text-[12px] font-black text-slate-700">Review &amp; Resolve</span><span class="block text-[9px] text-slate-400">Confirm what changes</span></span>
    </button>
    <button type="button" class="step-pill flex-1 bg-white border-2 border-[#e2e8f0] rounded-xl px-4 py-2.5 flex items-center gap-2.5 text-left" data-step="3" onclick="goStep(3)">
        <span class="step-num">3</span>
        <span><span class="block text-[12px] font-black text-slate-700">Staff Attendance</span><span class="block text-[9px] text-slate-400">View, filter &amp; manage</span></span>
    </button>
</div>

{{-- ══ STEP 1 — UPLOAD ══════════════════════════════════════════════════ --}}
<div class="panel show" id="panel-1">
    <div class="bg-white rounded-2xl border border-[#6B9080]/25 shadow-sm p-6 mb-5 no-print">
        <p class="text-[10px] font-black text-[#6B9080] uppercase tracking-widest mb-4">Upload Daily Attendance Report</p>

        <div class="grid grid-cols-2 gap-4 mb-4 max-w-md">
            <div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Company</p>
                <select id="companySelect" class="w-full border border-[#6B9080]/30 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white outline-none focus:border-[#6B9080]">
                    @foreach(['RCG','RGHB','RCT'] as $co)
                    <option value="{{ $co }}" {{ $defaultCompany === $co ? 'selected' : '' }}>{{ $co }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="dropzone rounded-2xl p-10 text-center cursor-pointer" id="dropzone" onclick="document.getElementById('fileInput').click()">
            <svg class="w-8 h-8 mx-auto text-slate-400 mb-2" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M12 3v12m0-12l-4 4m4-4l4 4M5 17v2a2 2 0 002 2h10a2 2 0 002-2v-2"/></svg>
            <p class="text-[13px] font-black text-slate-700">Drag CSV here, or click to browse</p>
            <p class="text-[11px] text-slate-400 mt-1">Accepts the InfoTech "Daily Attendance Report (Individual)" export (.csv)</p>
            <input type="file" id="fileInput" accept=".csv,text/csv" class="hidden" onchange="onFilePicked(this.files[0])">
            <div class="hidden items-center gap-2 bg-[#eef6f3] border border-[#6B9080] rounded-xl px-4 py-2.5 mt-4 text-[12px]" id="fileChip">
                <svg class="w-4 h-4 text-[#1a3d34] flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
                <span class="font-bold truncate" id="fileChipName"></span>
            </div>
            <div class="hidden mt-4" id="progressWrap">
                <div class="h-1.5 rounded-full bg-slate-200 overflow-hidden"><div class="h-full bg-[#6B9080] rounded-full transition-all" style="width:0%" id="progressFill"></div></div>
                <p class="text-[11px] text-slate-400 mt-1.5" id="progressLabel">Reading file…</p>
            </div>
        </div>
    </div>
</div>

{{-- ══ STEP 2 — REVIEW & RESOLVE ════════════════════════════════════════ --}}
<div class="panel" id="panel-2">
    <div class="bg-white rounded-2xl border border-[#6B9080]/25 shadow-sm p-6 mb-5 no-print">
        <p class="text-[10px] font-black text-[#6B9080] uppercase tracking-widest mb-1">Review Before Importing</p>
        <p class="text-[11px] text-slate-400 mb-4" id="reviewSubtitle">Parse a file in Step 1 first.</p>

        <div class="grid grid-cols-4 gap-3 mb-5" id="reviewStats"></div>

        <div id="conflictSection">
            <h3 class="text-[11px] font-black text-slate-700 mb-2">⚠️ Conflicts — existing data differs from this upload</h3>
            <div class="space-y-3" id="conflictList"></div>
        </div>

        <div class="flex justify-between items-center gap-3 flex-wrap mt-5 pt-4 border-t border-dashed border-slate-200">
            <p class="text-[11px] text-slate-400" id="resolveNote">Choose <b>Keep saved</b> or <b>Use upload</b> for each staff with conflicts above. Nothing is overwritten without your choice.</p>
            <div class="flex gap-2">
                <button type="button" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-[12px]" onclick="goStep(1)">← Back</button>
                <button type="button" id="confirmImportBtn" class="px-5 py-2.5 rounded-xl bg-[#1a3d34] hover:bg-[#2d5548] text-white font-black text-[12px]" onclick="confirmImport()" disabled>Confirm &amp; Import</button>
            </div>
        </div>
    </div>
</div>

{{-- ══ STEP 3 — STAFF ATTENDANCE ════════════════════════════════════════ --}}
<div class="panel" id="panel-3">
    <div class="bg-white rounded-2xl border border-[#6B9080]/25 shadow-sm p-6 mb-5">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-4 no-print">
            <div>
                <p class="text-[10px] font-black text-[#6B9080] uppercase tracking-widest">Staff Attendance</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Click a staff member for daily clock in/out. Late clock-ins (after 8:30 AM) and early clock-outs (before 5:30 PM) show in red.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2.5 mb-4 no-print">
            <div class="flex-[2] min-w-[200px]">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Company</p>
                <select id="listCompany" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold" onchange="loadStaffList()">
                    @foreach(['RCG','RGHB','RCT'] as $co)
                    <option value="{{ $co }}" {{ $defaultCompany === $co ? 'selected' : '' }}>{{ $co }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-[2] min-w-[200px]">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Search Staff</p>
                <input type="search" id="searchInput" placeholder="Name or staff code…" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs" oninput="debouncedRender()">
            </div>
            <div class="flex-1 min-w-[150px]">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Department</p>
                <select id="deptFilter" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs" onchange="renderStaffList()">
                    <option value="">All</option>
                    @foreach($departments as $d)
                    <option value="{{ $d['code'] }}">{{ $d['name'] ?? $d['code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[150px]">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Period</p>
                <input type="month" id="periodInput" value="{{ $defaultPeriod }}" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs" onchange="loadStaffList()">
            </div>
            <div class="flex-1 min-w-[140px]">
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Flag</p>
                <select id="flagFilter" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs" onchange="renderStaffList()">
                    <option value="">Any</option>
                    <option value="late">Has late clock-ins</option>
                    <option value="absent">Has absences</option>
                    <option value="clean">No issues</option>
                </select>
            </div>
        </div>

        <div class="bulk-bar items-center justify-between gap-3 flex-wrap bg-[#1a0a0a] text-white rounded-xl px-4 py-2.5 mb-3 no-print" id="bulkBar">
            <span class="text-[12px] font-bold" id="bulkCount">0 selected</span>
            <div class="flex items-center gap-2 flex-wrap">
                <select id="deleteScope" class="text-xs rounded-lg px-2 py-1.5 bg-white/10 border border-white/25 text-white">
                    <option value="selected">Delete: selected records only</option>
                    <option value="day">Delete: entire day (all staff)</option>
                    <option value="week">Delete: entire week</option>
                    <option value="month">Delete: entire month</option>
                    <option value="staff">Delete: this staff member's whole record</option>
                </select>
                <button type="button" class="bg-red-600 hover:bg-red-700 text-white text-[11px] font-black px-3 py-1.5 rounded-lg" onclick="openDeleteModal()">🗑 Delete</button>
                <button type="button" class="bg-white/10 border border-white/25 text-white text-[11px] font-bold px-3 py-1.5 rounded-lg" onclick="clearSelection()">Clear</button>
            </div>
        </div>

        <div class="grid text-[9px] text-slate-400 uppercase tracking-wide font-bold px-3 pb-1.5" style="grid-template-columns:22px 1.6fr repeat(7,.7fr) 20px;">
            <span></span><span></span>
            <span class="text-center">Work</span><span class="text-center">Present</span><span class="text-center">Absent</span>
            <span class="text-center">MC</span><span class="text-center">AL</span><span class="text-center">EL</span><span class="text-center">Late</span><span></span>
        </div>

        <div id="staffList" class="space-y-2"></div>

        <div class="flex items-start gap-2.5 bg-[#eef6f3] border border-[#6B9080]/40 rounded-xl px-4 py-3 mt-4 text-[11px] text-slate-600">
            <svg class="w-4 h-4 text-[#6B9080] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            <span><b class="text-[#1a3d34]">Feeds the Appraisal automatically.</b> Working Days, Present, Absent, MC, AL, EL and Late counts here are exactly what appears in each staff member's Performance Report → Attendance Record — nothing is re-entered by hand.</span>
        </div>
    </div>
</div>

</div>
</main>

{{-- ══ DELETE CONFIRM MODAL ═════════════════════════════════════════════ --}}
<div class="modal-overlay fixed inset-0 bg-black/50 z-50 items-center justify-center px-4" id="deleteModal">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full">
        <h3 class="text-[15px] font-black text-slate-800 mb-2">Delete attendance records?</h3>
        <p class="text-[12px] text-slate-500 leading-relaxed mb-4">This removes the records from Staff Attendance <b>and</b> from the affected staff member's appraisal attendance section. This cannot be undone.</p>
        <div class="bg-red-50 border border-red-200 rounded-xl px-3.5 py-2.5 text-[11.5px] text-slate-700 mb-5" id="deleteScopeText"></div>
        <div class="flex justify-end gap-2">
            <button type="button" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold text-[12px]" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-black text-[12px]" id="confirmDeleteBtn" onclick="performDelete()">Delete</button>
        </div>
    </div>
</div>

<div id="saveToast" style="display:none;opacity:0;transition:opacity .4s;position:fixed;bottom:24px;right:24px;z-index:50;" class="px-5 py-3 rounded-xl font-bold text-sm shadow-xl"></div>

<script>
const CSRF = document.querySelector('meta[name=csrf-token]')?.content || '';
function authHeaders(extra) { return Object.assign({ 'X-CSRF-TOKEN': CSRF }, extra || {}); }

function showToast(msg, isError) {
    var t = document.getElementById('saveToast');
    t.textContent = msg;
    t.className = 'px-5 py-3 rounded-xl font-bold text-sm shadow-xl ' + (isError ? 'bg-red-600 text-white' : 'bg-[#1a3d34] text-white');
    t.style.display = 'block';
    requestAnimationFrame(function(){ t.style.opacity = '1'; });
    setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.style.display = 'none'; }, 400); }, 3500);
}

function goStep(n) {
    document.querySelectorAll('.step-pill').forEach(function(el){ el.classList.toggle('active', +el.dataset.step === n); });
    document.querySelectorAll('.panel').forEach(function(el, i){ el.classList.toggle('show', i+1 === n); });
    if (n === 3) loadStaffList();
}

// ───────────────────── Step 1: upload ─────────────────────
var dropzone = document.getElementById('dropzone');
['dragenter','dragover'].forEach(function(ev){ dropzone.addEventListener(ev, function(e){ e.preventDefault(); dropzone.classList.add('drag'); }); });
['dragleave','drop'].forEach(function(ev){ dropzone.addEventListener(ev, function(e){ e.preventDefault(); dropzone.classList.remove('drag'); }); });
dropzone.addEventListener('drop', function(e){
    var f = e.dataTransfer.files[0];
    if (f) onFilePicked(f);
});

function onFilePicked(file) {
    if (!file) return;
    document.getElementById('fileChip').classList.remove('hidden');
    document.getElementById('fileChip').classList.add('flex');
    document.getElementById('fileChipName').textContent = file.name;

    var wrap = document.getElementById('progressWrap');
    var fill = document.getElementById('progressFill');
    var label = document.getElementById('progressLabel');
    wrap.classList.remove('hidden');
    fill.style.width = '15%';
    label.textContent = 'Uploading & parsing…';

    var fd = new FormData();
    fd.append('file', file);
    fd.append('company', document.getElementById('companySelect').value);

    fetch("{{ route('attendance.import') }}", {
        method: 'POST',
        headers: authHeaders(),
        body: fd,
    })
    .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
    .then(function(res) {
        fill.style.width = '100%';
        if (!res.ok) {
            label.textContent = res.d.error || 'Could not parse this file.';
            label.className = 'text-[11px] text-red-600 font-bold mt-1.5';
            return;
        }
        label.textContent = 'Done — ' + res.d.conflictCount + ' conflict(s) found across ' + res.d.totalStaff + ' staff.';
        window.__importPreview = res.d;
        renderReview(res.d);
        setTimeout(function(){ goStep(2); }, 400);
    })
    .catch(function(err) {
        label.textContent = 'Network error — ' + err.message;
        label.className = 'text-[11px] text-red-600 font-bold mt-1.5';
    });
}

// ───────────────────── Step 2: review & resolve ─────────────────────
var conflictChoices = {};

function renderReview(d) {
    document.getElementById('reviewSubtitle').textContent =
        'Parsed ' + d.totalStaff + ' staff · ' + d.dateRangeLabel + ' · ' + d.totalRecords + ' daily records.';

    document.getElementById('reviewStats').innerHTML = [
        ['New / unchanged', d.newOrCleanCount, 'text-[#6B9080]'],
        ['Conflicts to review', d.conflictCount, 'text-red-600'],
        ['Staff in file', d.totalStaff, 'text-slate-700'],
        ['Daily records parsed', d.totalRecords, 'text-slate-700'],
    ].map(function(s){
        return '<div class="bg-slate-50 rounded-xl px-4 py-3"><p class="text-xl font-black ' + s[2] + '">' + s[1] + '</p><p class="text-[9px] text-slate-500 uppercase tracking-wide mt-0.5">' + s[0] + '</p></div>';
    }).join('');

    conflictChoices = {};
    (d.conflicts || []).forEach(function(c){ conflictChoices[c.internal_id] = 'keep'; });

    var list = document.getElementById('conflictList');
    document.getElementById('conflictSection').style.display = (d.conflicts && d.conflicts.length) ? 'block' : 'none';
    list.innerHTML = (d.conflicts || []).map(function(c) {
        var rows = c.sample.map(function(s){
            return '<div class="grid grid-cols-2 gap-2 text-[10.5px] border-t border-slate-100 py-1.5 first:border-0 first:pt-0">' +
                '<div><span class="text-slate-400">' + s.date + ' (' + s.day + ')</span> — saved: <b>' + (s.saved.status) + (s.saved.clock_in ? ' ' + s.saved.clock_in : '') + '</b></div>' +
                '<div>upload: <b class="text-[#7A0019]">' + (s.new.status) + (s.new.clock_in ? ' ' + s.new.clock_in : '') + '</b></div>' +
                '</div>';
        }).join('');
        var moreNote = c.count > c.sample.length ? '<p class="text-[10px] text-slate-400 mt-1">+ ' + (c.count - c.sample.length) + ' more day(s) for this staff</p>' : '';
        return '' +
        '<div class="conflict-card border-2 border-red-200 bg-red-50/60 rounded-xl p-4" data-eid="' + c.internal_id + '">' +
            '<div class="flex items-center justify-between gap-2 mb-2">' +
                '<div><span class="text-[12.5px] font-black text-slate-800">' + c.name + '</span> <span class="text-[11px] text-slate-400">· ' + c.internal_id + ' · ' + (c.department || '—') + '</span></div>' +
                '<span class="text-[10px] font-black uppercase" data-status>' + c.count + ' day(s) — Unresolved</span>' +
            '</div>' +
            rows + moreNote +
            '<div class="flex items-center gap-2 mt-3">' +
                '<label class="inline-flex items-center gap-1.5 text-[11px] border rounded-full px-2.5 py-1 cursor-pointer border-[#1a3d34] bg-[#1a3d34]/5 font-bold" data-choice="keep"><input type="radio" name="c-' + c.internal_id + '" value="keep" checked onchange="pickConflict(\'' + c.internal_id + '\',\'keep\')"> Keep saved</label>' +
                '<label class="inline-flex items-center gap-1.5 text-[11px] border rounded-full px-2.5 py-1 cursor-pointer border-slate-200" data-choice="new"><input type="radio" name="c-' + c.internal_id + '" value="new" onchange="pickConflict(\'' + c.internal_id + '\',\'new\')"> Use upload (all ' + c.count + ' days)</label>' +
            '</div>' +
        '</div>';
    }).join('');

    document.getElementById('confirmImportBtn').disabled = false;
    document.getElementById('confirmImportBtn').textContent = 'Confirm & Import ' + d.totalStaff + ' Staff';
}

function pickConflict(eid, val) {
    conflictChoices[eid] = val;
    var card = document.querySelector('.conflict-card[data-eid="' + CSS.escape(eid) + '"]');
    card.querySelectorAll('[data-choice]').forEach(function(l){
        var isActive = l.dataset.choice === val;
        l.classList.toggle('border-[#1a3d34]', isActive);
        l.classList.toggle('bg-[#1a3d34]/5', isActive);
        l.classList.toggle('font-bold', isActive);
        l.classList.toggle('border-slate-200', !isActive);
    });
    card.classList.toggle('resolved', true);
    card.querySelector('[data-status]').textContent = val === 'keep' ? 'Kept saved value' : 'Will use upload';
}

function confirmImport() {
    if (!window.__importPreview) return;
    var btn = document.getElementById('confirmImportBtn');
    btn.disabled = true;
    btn.textContent = 'Importing…';

    fetch("{{ route('attendance.confirm') }}", {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ token: window.__importPreview.token, resolutions: conflictChoices }),
    })
    .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
    .then(function(res) {
        if (!res.ok) { showToast(res.d.error || 'Import failed.', true); btn.disabled = false; btn.textContent = 'Confirm & Import'; return; }
        showToast('Imported ' + res.d.imported + ' daily record(s).', false);
        document.querySelector('.step-pill[data-step="2"]').classList.add('done');
        window.__importPreview = null;
        goStep(3);
    })
    .catch(function(err) { showToast('Network error — ' + err.message, true); btn.disabled = false; btn.textContent = 'Confirm & Import'; });
}

// ───────────────────── Step 3: staff attendance ─────────────────────
var STAFF_CACHE = [];
var selected = new Set();
var dailyCache = {};

function loadStaffList() {
    var company = document.getElementById('listCompany').value;
    var period = document.getElementById('periodInput').value; // "YYYY-MM"
    document.getElementById('staffList').innerHTML = '<p class="text-[12px] text-slate-400 px-2 py-6 text-center">Loading…</p>';
    fetch("{{ route('attendance.staff') }}?company=" + encodeURIComponent(company) + "&period=" + encodeURIComponent(period))
        .then(function(r) { return r.json(); })
        .then(function(d) { STAFF_CACHE = d.staff || []; renderStaffList(); })
        .catch(function() { document.getElementById('staffList').innerHTML = '<p class="text-[12px] text-red-500 px-2 py-6 text-center">Could not load attendance.</p>'; });
}

var _debounce;
function debouncedRender() { clearTimeout(_debounce); _debounce = setTimeout(renderStaffList, 200); }

function renderStaffList() {
    var q = document.getElementById('searchInput').value.trim().toLowerCase();
    var dept = document.getElementById('deptFilter').value;
    var flag = document.getElementById('flagFilter').value;

    var visible = STAFF_CACHE.filter(function(s) {
        if (q && s.name.toLowerCase().indexOf(q) === -1 && s.internal_id.toLowerCase().indexOf(q) === -1) return false;
        if (dept && s.department !== dept) return false;
        if (flag === 'late' && s.late_count === 0) return false;
        if (flag === 'absent' && s.absent_days === 0) return false;
        if (flag === 'clean' && (s.late_count > 0 || s.absent_days > 0)) return false;
        return true;
    });

    document.getElementById('staffList').innerHTML = visible.map(function(s) {
        return '' +
        '<div class="staff-row border border-slate-200 rounded-xl overflow-hidden" id="row-' + cssId(s.internal_id) + '">' +
            '<div class="grid items-center gap-2.5 px-3.5 py-2.5 cursor-pointer hover:bg-slate-50 text-[11.5px]" style="grid-template-columns:22px 1.6fr repeat(7,.7fr) 20px;" onclick="toggleRow(\'' + s.internal_id + '\')">' +
                '<input type="checkbox" onclick="event.stopPropagation(); toggleSelect(\'' + s.internal_id + '\')">' +
                '<div class="min-w-0"><span class="font-black text-slate-800 truncate block">' + s.name + '</span><span class="text-[10px] text-slate-400">' + s.internal_id + ' · ' + s.department + '</span></div>' +
                '<div class="text-center font-black">' + s.working_days + '</div>' +
                '<div class="text-center font-black">' + s.present_days + '</div>' +
                '<div class="text-center font-black ' + (s.absent_days>0?'text-red-600':'') + '">' + s.absent_days + '</div>' +
                '<div class="text-center font-black">' + s.mc_days + '</div>' +
                '<div class="text-center font-black">' + s.al_days + '</div>' +
                '<div class="text-center font-black">' + s.el_days + '</div>' +
                '<div class="text-center font-black ' + (s.late_count>0?'text-red-600':'') + '">' + s.late_count + '</div>' +
                '<svg class="chev w-3.5 h-3.5 text-slate-400 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>' +
            '</div>' +
            '<div class="daily-wrap border-t border-slate-100 bg-slate-50 px-3.5 py-3" data-loaded="0"></div>' +
        '</div>';
    }).join('') || '<p class="text-[12px] text-slate-400 px-2 py-6 text-center">No staff match these filters.</p>';
}

function cssId(id) { return id.replace(/[^a-zA-Z0-9_-]/g, '_'); }

function toggleRow(internalId) {
    var row = document.getElementById('row-' + cssId(internalId));
    var opening = !row.classList.contains('open');
    row.classList.toggle('open');
    if (!opening) return;
    var wrap = row.querySelector('.daily-wrap');
    if (wrap.dataset.loaded === '1') return;
    wrap.innerHTML = '<p class="text-[11px] text-slate-400 py-2">Loading daily records…</p>';
    var company = document.getElementById('listCompany').value;
    var period = document.getElementById('periodInput').value;
    fetch("{{ url('/attendance/staff') }}/" + encodeURIComponent(internalId) + "/daily?company=" + encodeURIComponent(company) + "&period=" + encodeURIComponent(period))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            wrap.dataset.loaded = '1';
            dailyCache[internalId] = d.daily || [];
            wrap.innerHTML = renderDailyTable(internalId, d.daily || []);
        })
        .catch(function() { wrap.innerHTML = '<p class="text-[11px] text-red-500 py-2">Could not load daily records.</p>'; });
}

var STATUS_LABEL = { present: 'Present', absent: 'Absent', mc: 'MC', al: 'AL', el: 'EL', off: 'Rest Day', other: 'Other' };
var STATUS_CLASS = { present: 'status-present', absent: 'status-absent', mc: 'status-leave', al: 'status-leave', el: 'status-leave', off: 'status-off', other: 'status-leave' };

function renderDailyTable(internalId, rows) {
    var legend = '<div class="flex flex-wrap gap-3 text-[10px] text-slate-500 mb-2">' +
        '<span>🔴 Late clock-in (after 8:30 AM) or early clock-out (before 5:30 PM)</span>' +
        '<span>Present</span><span>MC / AL / EL</span><span>Absent</span><span>Rest Day</span>' +
    '</div>';
    var body = rows.map(function(r) {
        var inHtml = r.clock_in ? ('<span class="' + (r.is_late ? 'time-late' : '') + '">' + r.clock_in + '</span>') : '<span class="text-slate-300">—</span>';
        var outHtml = r.clock_out ? ('<span class="' + (r.is_early_out ? 'time-late' : '') + '">' + r.clock_out + '</span>') : '<span class="text-slate-300">—</span>';
        return '<tr class="border-b border-slate-100 last:border-0">' +
            '<td class="py-1.5 pr-3"><input type="checkbox" data-row-id="' + r.id + '" onclick="toggleRowSelect(\'' + r.id + '\')"></td>' +
            '<td class="py-1.5 pr-3 whitespace-nowrap">' + Carbon(r.date) + ' <span class="text-slate-400">(' + (r.day_name || '').slice(0,3) + ')</span></td>' +
            '<td class="py-1.5 pr-3">' + inHtml + '</td>' +
            '<td class="py-1.5 pr-3">' + outHtml + '</td>' +
            '<td class="py-1.5"><span class="status-badge ' + (STATUS_CLASS[r.status] || 'status-leave') + '">' + (STATUS_LABEL[r.status] || r.status) + '</span></td>' +
        '</tr>';
    }).join('');
    return legend + '<table class="w-full text-[11px]"><thead><tr class="text-left text-[9px] uppercase tracking-wide text-slate-400"><th class="py-1"></th><th class="py-1">Date</th><th class="py-1">Clock In</th><th class="py-1">Clock Out</th><th class="py-1">Status</th></tr></thead><tbody>' + body + '</tbody></table>';
}

function Carbon(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    return String(d.getDate()).padStart(2,'0') + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()];
}

var selectedRowIds = new Set();
function toggleRowSelect(id) {
    if (selectedRowIds.has(id)) selectedRowIds.delete(id); else selectedRowIds.add(id);
    updateBulkBar();
}
function toggleSelect(internalId) {
    if (selected.has(internalId)) selected.delete(internalId); else selected.add(internalId);
    updateBulkBar();
}
function updateBulkBar() {
    var count = selected.size + selectedRowIds.size;
    document.getElementById('bulkCount').textContent = count + ' selected';
    document.getElementById('bulkBar').classList.toggle('show', count > 0);
}
function clearSelection() {
    selected.clear(); selectedRowIds.clear();
    document.querySelectorAll('#staffList input[type=checkbox]').forEach(function(c){ c.checked = false; });
    updateBulkBar();
}

function openDeleteModal() {
    var scope = document.getElementById('deleteScope').value;
    var names = Array.from(selected).map(function(id){ var s = STAFF_CACHE.find(function(x){ return x.internal_id === id; }); return s ? s.name : id; });
    var text = {
        selected: 'You\'re about to delete ' + selectedRowIds.size + ' selected daily record(s).',
        day: 'You\'re about to delete <b>one full day</b> of attendance for every staff member in the current list.',
        week: 'You\'re about to delete <b>one full week</b> of attendance.',
        month: 'You\'re about to delete the <b>entire selected month</b> of attendance for: <b>' + (names.join(', ') || 'the checked staff') + '</b>.',
        staff: 'You\'re about to delete <b>every saved attendance record</b> for: <b>' + (names.join(', ') || 'the checked staff') + '</b> — all months.',
    };
    document.getElementById('deleteScopeText').innerHTML = text[scope] || text.selected;
    document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('show'); }

function performDelete() {
    var scope = document.getElementById('deleteScope').value;
    var company = document.getElementById('listCompany').value;
    var period = document.getElementById('periodInput').value;
    var payload = { company: company, scope: scope };

    if (scope === 'selected') payload.ids = Array.from(selectedRowIds);
    if (scope === 'day' || scope === 'week') payload.date = period + '-01';
    if (scope === 'month') payload.period = period.slice(0,4) + '-' + period.slice(5,7);
    if (scope === 'staff') payload.internal_id = Array.from(selected)[0];

    var btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true; btn.textContent = 'Deleting…';

    fetch("{{ route('attendance.delete') }}", {
        method: 'POST',
        headers: authHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(payload),
    })
    .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, d: d }; }); })
    .then(function(res) {
        btn.disabled = false; btn.textContent = 'Delete';
        closeDeleteModal();
        if (!res.ok) { showToast(res.d.error || 'Delete failed.', true); return; }
        showToast('Deleted ' + res.d.deleted + ' record(s).', false);
        clearSelection();
        loadStaffList();
    })
    .catch(function(err) { btn.disabled = false; btn.textContent = 'Delete'; showToast('Network error — ' + err.message, true); });
}
</script>

</body>
</html>
