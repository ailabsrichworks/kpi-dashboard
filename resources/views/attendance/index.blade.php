  <!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Attendance Import</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        *, body { font-family: 'Inter', sans-serif; }
        .tbl th { background:#1a3d34;color:#fff;padding:9px 11px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;white-space:nowrap; }
        .tbl td { padding:7px 10px;border-bottom:1px solid rgba(107,144,128,.12);font-size:11px;vertical-align:middle; }
        .tbl tbody tr:hover { background:rgba(107,144,128,.04); }
        .badge { display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;border-radius:6px;font-size:10px;font-weight:800;padding:0 5px; }
        .leaf-input { width:48px;border:1.5px solid rgba(107,144,128,.3);border-radius:7px;padding:3px 5px;font-size:10px;font-weight:600;color:#334155;text-align:center;background:#fff;outline:none; }
        .leaf-input:focus { border-color:#6B9080; }
        .m-card { border-radius:10px;padding:9px 11px;border:1.5px solid;display:flex;flex-direction:column;gap:3px; }
        .m-card.done   { background:#edf7f1;border-color:#6B9080; }
        .m-card.empty  { background:#f8f9fa;border-color:#e2e8f0; }
        .m-card.future { background:#f1f5f9;border-color:#e2e8f0;opacity:.4; }
        @media print { .no-print{display:none!important;} body{background:#fff!important;} }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">

<div class="px-4 pt-4 py-4 max-w-full">

@php
    $mNames     = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $mStatus    = $monthStatus ?? [];
    $sYear      = $statusYear  ?? now()->year;
    $curMonth   = now()->month;
    $defCompany = $defaultCompany ?? 'RCG';
    $defUrl     = $sheetUrl ?? 'https://docs.google.com/spreadsheets/d/1idaGU_UfJ7tyoQtB65muFC5jKGYXZRshqUxb6ig5BbQ/edit';
@endphp

{{-- ══ IMPORT STATUS GRID ════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-[#6B9080]/25 shadow-sm p-5 mb-5 no-print">
    <div class="flex items-center gap-2 mb-3">
        <p class="text-[10px] font-black text-[#6B9080] uppercase tracking-widest">Import Status</p>
        <span class="text-[9px] bg-[#1a3d34]/10 text-[#1a3d34] font-bold px-2 py-0.5 rounded-full">{{ $sYear }}</span>
    </div>
    <div class="grid grid-cols-6 gap-2">
        @for($mi = 1; $mi <= 12; $mi++)
        @php
            $done    = isset($mStatus[$mi]);
            $future  = ($sYear == now()->year && $mi > $curMonth) || $sYear > now()->year;
            $cls     = $future ? 'future' : ($done ? 'done' : 'empty');
            $lastTs  = $done ? \Carbon\Carbon::parse($mStatus[$mi])->format('d M · H:i') : null;
        @endphp
        <div class="m-card {{ $cls }}" id="mc-{{ $mi }}">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black {{ $done ? 'text-[#1a3d34]' : ($future ? 'text-slate-300' : 'text-slate-400') }}">
                    {{ substr($mNames[$mi-1], 0, 3) }}
                </span>
                <span id="mc-chk-{{ $mi }}" class="text-[9px]">{{ $done ? '✓' : ($future ? '' : '—') }}</span>
            </div>
            <div id="mc-ts-{{ $mi }}" class="text-[8px] font-medium {{ $done ? 'text-[#6B9080]' : 'text-slate-300' }}">
                {{ $done ? $lastTs : ($future ? 'Future' : 'Not imported') }}
            </div>
        </div>
        @endfor
    </div>
</div>

{{-- ══ IMPORT FORM ═══════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-[#6B9080]/25 shadow-sm p-6 mb-5 no-print">
    <p class="text-[10px] font-black text-[#6B9080] uppercase tracking-widest mb-4">Import Attendance</p>

    @if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('attendance.import') }}" id="importForm">
        @csrf
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Company</p>
                <select name="company" class="w-full border border-[#6B9080]/30 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white outline-none focus:border-[#6B9080]">
                    @foreach(['RCG','RGHB','RCT'] as $co)
                    <option value="{{ $co }}" {{ (isset($company) ? $company : $defCompany) === $co ? 'selected' : '' }}>{{ $co }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Month</p>
                <select name="month" class="w-full border border-[#6B9080]/30 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white outline-none focus:border-[#6B9080]">
                    @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ ($defaultMonth ?? date('n')) == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create(null,$m)->format('F') }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Year</p>
                <select name="year" class="w-full border border-[#6B9080]/30 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white outline-none focus:border-[#6B9080]">
                    @foreach([2025,2026,2027] as $y)
                    <option value="{{ $y }}" {{ ($defaultYear ?? date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Google Sheet URL</p>
            <div class="flex gap-2">
                <input type="url" name="sheet_url" required placeholder="https://docs.google.com/spreadsheets/d/…"
                    value="{{ $defUrl }}"
                    class="flex-1 border border-[#6B9080]/30 rounded-xl px-4 py-2.5 text-sm text-slate-700 bg-white outline-none focus:border-[#6B9080]">
                <button type="submit" id="importBtn"
                    class="bg-[#1a3d34] hover:bg-[#2d5548] text-white px-6 py-2.5 rounded-xl font-bold text-sm transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Import & Preview
                </button>
            </div>
            <p class="text-[9px] text-slate-400 mt-1.5">
                Sheet must have a tab named after the month (e.g. <strong>January</strong>, <strong>February</strong> …) and be set to
                <strong>"Anyone with link can view"</strong>.
                Only staff who have clock-in records in that month will appear.
            </p>
        </div>
    </form>
</div>

{{-- ══ PREVIEW & SAVE ════════════════════════════════════════════════════ --}}
@isset($results)

@php
    $totalEmp          = count($results);
    $totalLate         = collect($results)->sum('late_count');
    $totalAbsent       = collect($results)->sum('absent_days');
    $totalInsufficient = collect($results)->sum('insufficient_count');
    $monthLabel        = \Carbon\Carbon::create(null, $month)->format('F') . ' ' . $year;
@endphp

<div class="flex justify-end mb-3 no-print">
    <button onclick="window.print()" class="bg-white hover:bg-slate-50 text-slate-600 px-4 py-2 rounded-xl font-bold text-xs border border-[#6B9080]/25 flex items-center gap-1.5 transition">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
    </button>
</div>

{{-- Summary cards --}}
<div class="grid grid-cols-5 gap-3 mb-4 no-print">
    @foreach([
        ['Staff in Sheet','👤',$totalEmp,'#1a3d34'],
        ['Working Days','📅',$totalWorkingDays,'#6B9080'],
        ['Late Incidents','⏰',$totalLate,'#d97706'],
        ['Absent Days','❌',$totalAbsent,'#dc2626'],
        ['Insufficient','⚠️',$totalInsufficient,'#7c3aed'],
    ] as [$lbl,$ico,$val,$clr])
    <div class="bg-white rounded-2xl border border-[#6B9080]/20 px-5 py-4 flex items-center gap-3">
        <span class="text-2xl">{{ $ico }}</span>
        <div>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">{{ $lbl }}</p>
            <p class="text-xl font-black" style="color:{{ $clr }}">{{ $val }}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- Period & legend --}}
<div class="flex items-center justify-between flex-wrap gap-2 mb-3 no-print">
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-xs font-black text-[#1a3d34]">{{ $monthLabel }}</span>
        @if(!empty($hasSavedData))
        <span class="text-[9px] bg-blue-100 text-blue-700 font-bold px-2 py-0.5 rounded-full">Re-import — existing MC / AL / Other values loaded. Edit if needed, then Save to update.</span>
        @else
        <span class="text-[9px] bg-amber-100 text-amber-700 font-bold px-2 py-0.5 rounded-full">Preview — enter MC / AL / Other then Save</span>
        @endif
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        @foreach([
            ['bg-emerald-100 text-emerald-700','Present'],
            ['bg-amber-100 text-amber-700','Late'],
            ['bg-red-100 text-red-700','Absent'],
            ['bg-purple-100 text-purple-700','Insufficient'],
        ] as [$cls,$lbl])
        <span class="badge {{ $cls }}">{{ $lbl }}</span>
        @endforeach
        <span class="text-[9px] text-slate-400">Late = clock-in after 8:30 AM</span>
    </div>
</div>

{{-- Attendance table --}}
<div class="bg-white rounded-2xl border border-[#6B9080]/25 shadow-sm overflow-hidden mb-4">
<div class="overflow-x-auto">
<table class="tbl w-full">
    <thead>
        <tr>
            <th class="text-left sticky left-0 bg-[#1a3d34]" style="min-width:160px;">Name</th>
            <th class="text-left" style="min-width:80px;">Dept</th>
            <th class="text-center">Work</th>
            <th class="text-center">Present</th>
            <th class="text-center">Absent</th>
            <th class="text-center">Late</th>
            <th class="text-center">Late Duration</th>
            <th class="text-center">Insuffic.</th>
            <th class="text-center">MC<br><span class="font-normal text-[8px] normal-case tracking-normal opacity-70">key in</span></th>
            <th class="text-center">AL<br><span class="font-normal text-[8px] normal-case tracking-normal opacity-70">key in</span></th>
            <th class="text-center">Other<br><span class="font-normal text-[8px] normal-case tracking-normal opacity-70">key in</span></th>
            @foreach($workingDays as $wd)
            <th class="text-center" style="min-width:30px;font-size:8px;padding:6px 2px;">
                {{ \Carbon\Carbon::parse($wd)->format('d') }}<br>
                <span class="font-normal opacity-70">{{ \Carbon\Carbon::parse($wd)->format('D') }}</span>
            </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
    @php $prevDept = null; @endphp
    @foreach($results as $eid => $emp)
    @if($emp['department'] !== $prevDept)
    @php $prevDept = $emp['department']; @endphp
    <tr>
        <td colspan="{{ 12 + count($workingDays) }}" class="bg-[#e4f0eb] py-2 px-4">
            <span class="text-[9px] font-black text-[#1a3d34] uppercase tracking-widest">▸ {{ $emp['department'] ?: 'No Department' }}</span>
        </td>
    </tr>
    @endif
    <tr class="emp-row" data-eid="{{ $eid }}">
        <td class="sticky left-0 bg-white font-bold text-slate-800" style="min-width:160px;">
            {{ $emp['preferred_name'] ?: explode(' ',$emp['name'])[0] }}
            <div class="text-[9px] text-slate-400 font-normal">{{ $emp['internal_id'] }}</div>
        </td>
        <td class="text-[10px] text-slate-500">{{ $emp['department'] ?: '—' }}</td>
        <td class="text-center font-bold text-[#1a3d34]">{{ $emp['working_days'] }}</td>
        <td class="text-center">
            <span class="badge bg-emerald-100 text-emerald-700">{{ $emp['present_days'] }}</span>
        </td>
        <td class="text-center">
            @if($emp['absent_days'] > 0)
            <span class="badge bg-red-100 text-red-700">{{ $emp['absent_days'] }}</span>
            @else<span class="text-slate-300">—</span>@endif
        </td>
        <td class="text-center">
            @if($emp['late_count'] > 0)
            <span class="badge bg-amber-100 text-amber-700">{{ $emp['late_count'] }}×</span>
            @else<span class="text-slate-300">—</span>@endif
        </td>
        <td class="text-center text-[10px] {{ $emp['total_late_minutes']>0?'text-amber-700 font-bold':'text-slate-300' }}">
            @if($emp['total_late_minutes'] > 0)
            {{ floor($emp['total_late_minutes']/60) }}h {{ $emp['total_late_minutes']%60 }}m
            @else—@endif
        </td>
        <td class="text-center">
            @if(($emp['insufficient_count'] ?? 0) > 0)
            <span class="badge bg-purple-100 text-purple-700">{{ $emp['insufficient_count'] }}</span>
            @else<span class="text-slate-300">—</span>@endif
        </td>
        <td class="text-center"><input type="number" min="0" max="{{ $emp['working_days'] }}" value="{{ $emp['mc_days'] }}" class="leaf-input mc-in" data-eid="{{ $eid }}"></td>
        <td class="text-center"><input type="number" min="0" max="{{ $emp['working_days'] }}" value="{{ $emp['al_days'] }}" class="leaf-input al-in" data-eid="{{ $eid }}"></td>
        <td class="text-center"><input type="number" min="0" max="{{ $emp['working_days'] }}" value="{{ $emp['other_leave_days'] }}" class="leaf-input ot-in" data-eid="{{ $eid }}"></td>
        @foreach($workingDays as $wd)
        @php
            $day = $emp['daily'][$wd] ?? ['status'=>'absent','clock_in'=>null,'clock_out'=>null,'is_late'=>false,'late_minutes'=>0,'is_insufficient'=>false];
            $dayInsuf = $day['is_insufficient'] ?? false;
        @endphp
        <td class="text-center" style="padding:3px 2px;">
            @if($day['status'] === 'present')
            @php
                $dayCls = $dayInsuf ? 'bg-purple-100 text-purple-700' : ($day['is_late'] ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');
                $dayTip = $day['clock_in'] . ($day['clock_out'] ? ' → '.$day['clock_out'] : ' → ?') . ($day['is_late'] ? ' · Late '.floor($day['late_minutes']/60).'h'.($day['late_minutes']%60).'m' : '') . ($dayInsuf ? ' · Insufficient' : '');
            @endphp
            <div class="mx-auto w-7 h-7 rounded-lg flex items-center justify-center text-[8px] font-bold {{ $dayCls }}" title="{{ $dayTip }}">
                {{ $day['clock_in'] }}
            </div>
            @else
            <div class="mx-auto w-7 h-7 rounded-lg bg-red-50 flex items-center justify-center">
                <span class="text-red-300 text-xs">✕</span>
            </div>
            @endif
        </td>
        @endforeach
    </tr>
    @endforeach
    </tbody>
</table>
</div>
</div>

{{-- Save bar --}}
<div class="flex justify-between items-center mb-8 no-print">
    <p class="text-[11px] text-slate-400">
        Enter MC / AL / Other Leave days above, then save.<br>
        <span class="text-[10px] text-[#6B9080] font-semibold">{{ $totalEmp }} staff found in the <strong>{{ $monthLabel }}</strong> sheet tab.</span>
    </p>
    <button id="saveBtn" onclick="saveMonth()" class="bg-[#1a3d34] hover:bg-[#2d5548] text-white px-8 py-3 rounded-xl font-black text-sm transition shadow-lg flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save {{ \Carbon\Carbon::create(null,$month)->format('F') }} {{ $year }}
    </button>
</div>

<script>
const RAW_RESULTS = @json($results);
const MONTH       = {{ $month }};
const YEAR        = {{ $year }};
const COMPANY     = "{{ $company }}";
const SHEET_URL   = "{{ addslashes($sheetUrl ?? '') }}";
</script>
@endisset

</div>
</main>

<script>

// Import button loading state
document.getElementById('importForm')?.addEventListener('submit', function() {
    var btn = document.getElementById('importBtn');
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Loading…';
    btn.disabled = true;
    btn.classList.add('opacity-70');
});

// Toast notification
function showToast(msg, isError) {
    var t = document.getElementById('saveToast');
    if (!t) return;
    t.textContent = msg;
    t.className = 'fixed bottom-6 right-6 z-50 px-5 py-3 rounded-xl font-bold text-sm shadow-xl transition-all ' +
        (isError ? 'bg-red-600 text-white' : 'bg-[#1a3d34] text-white');
    t.style.display = 'block';
    t.style.opacity = '1';
    setTimeout(function() {
        t.style.opacity = '0';
        setTimeout(function() { t.style.display = 'none'; }, 400);
    }, 3500);
}

// Save month
function saveMonth() {
    if (typeof RAW_RESULTS === 'undefined') return;
    var records = [];
    document.querySelectorAll('tr[data-eid]').forEach(function(row) {
        var eid = row.dataset.eid;
        var emp = RAW_RESULTS[eid];
        if (!emp) return;
        records.push({
            internal_id:        eid,
            db_employee_id:     emp.db_employee_id || null,
            working_days:       emp.working_days,
            present_days:       emp.present_days,
            absent_days:        emp.absent_days,
            late_count:         emp.late_count,
            total_late_minutes: emp.total_late_minutes,
            insufficient_count: emp.insufficient_count || 0,
            mc_days:            parseInt(row.querySelector('.mc-in')?.value) || 0,
            al_days:            parseInt(row.querySelector('.al-in')?.value) || 0,
            other_leave_days:   parseInt(row.querySelector('.ot-in')?.value) || 0,
        });
    });

    if (records.length === 0) { showToast('No records to save.', true); return; }

    var btn = document.getElementById('saveBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<svg class="w-4 h-4 animate-spin inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Saving…'; }

    fetch("{{ route('attendance.save') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
        },
        body: JSON.stringify({ records, month: MONTH, year: YEAR, company: COMPANY, sheet_url: SHEET_URL }),
    })
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function(d) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Saved!'; }
        if (d.success) {
            showToast('Saved ' + d.count + ' staff records for ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][MONTH-1] + ' ' + YEAR, false);
            // Update status grid card
            var card  = document.getElementById('mc-' + MONTH);
            var check = document.getElementById('mc-chk-' + MONTH);
            var ts    = document.getElementById('mc-ts-' + MONTH);
            if (card)  { card.className = 'm-card done'; }
            if (check) check.textContent = '✓';
            if (ts) {
                var now = new Date();
                var mn  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                ts.textContent = now.getDate().toString().padStart(2,'0') + ' ' + mn[now.getMonth()] + ' · ' + now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                ts.className = 'text-[8px] font-medium text-[#6B9080]';
            }
        } else {
            showToast('Error: ' + (d.error || 'Save failed. Try again.'), true);
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
        }
    })
    .catch(function(err) {
        showToast('Network error — please try again. (' + err.message + ')', true);
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
    });
}
</script>
{{-- Toast notification --}}
<div id="saveToast" style="display:none;opacity:0;transition:opacity .4s;"></div>
</body>
</html>
