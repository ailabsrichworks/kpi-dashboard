<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Evaluation Summary</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .thin-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .role-group-row td { background: #F8FAFC; }
        .q-pill { display:inline-flex; flex-direction:column; align-items:center; justify-content:center; min-width:64px; padding:4px 8px; border-radius:10px; line-height:1.1; }
        .score-pill-score { font-size:13px; font-weight:900; }
        .score-pill-label { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; opacity:.85; margin-top:1px; }
        @media print {
            #sidebar, .no-print { display: none !important; }
            #mainContent { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

@php
    $bandMeta = [
        'unsatisfactory'      => ['label' => 'Unsatisfactory',     'range' => '1–49',   'bg' => '#ED1C24', 'text' => '#FFFFFF'],
        'below_average'       => ['label' => 'Below Average',      'range' => '50–69',  'bg' => '#FF8C00', 'text' => '#000000'],
        'meets_expectations'  => ['label' => 'Meets Expectations', 'range' => '70–89',  'bg' => '#FFD700', 'text' => '#000000'],
        'outstanding'         => ['label' => 'Outstanding',        'range' => '90–100', 'bg' => '#00B050', 'text' => '#FFFFFF'],
    ];
    $statusMeta = [
        'not_submitted'    => ['bg' => '#F1F5F9', 'text' => '#94A3B8'],
        'pending'          => ['bg' => '#F1F5F9', 'text' => '#64748B'],
        'awaiting_signoff' => ['bg' => '#FEF3C7', 'text' => '#B45309'],
    ];
    $roleGroups = [
        'SLT'       => 'SLT',
        'VP'        => 'VP',
        'MANAGER'   => 'Manager',
        'EXECUTIVE' => 'Executive',
    ];
    $roleBadgeStyle = [
        'SLT'       => 'background:#F3E8FF;color:#7C3AED;',
        'VP'        => 'background:#F5EAE0;color:#6B3F2A;',
        'MANAGER'   => 'background:#E0E7FF;color:#4338CA;',
        'EXECUTIVE' => 'background:#F1F5F9;color:#475569;',
    ];
    $currentRoleGroup = null;

    $quarterCardColors = [
        'Q1' => '#2563EB',
        'Q2' => '#7C3AED',
        'Q3' => '#D97706',
        'Q4' => '#0D9488',
    ];
@endphp

<main id="mainContent" class="ml-[230px] min-h-screen">

{{-- ═══════ HEADER (sticky) ═══════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="relative">
            <p class="text-[9px] font-black uppercase tracking-[0.18em] text-[#D4AF37] mb-1">HR &amp; BTS · Cross-Department View</p>
            <h1 class="text-xl font-black tracking-tight leading-tight">
                Staff Quarterly Evaluation Summary — {{ $companyCode }} · {{ $currentFinancialYear }}
            </h1>
            <p class="text-[11px] text-white/60 mt-1">{{ $today }} · Every staff member's appraisal status and score, side-by-side across all four quarters</p>
        </div>

        <form method="GET" action="{{ route('staff-evaluation-summary') }}" class="relative flex flex-wrap items-center gap-2">
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Department</label>
                <select name="department" onchange="this.form.submit()" class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20">
                    <option value="ALL" @selected($deptFilter === 'ALL')>All Departments</option>
                    @foreach($departments as $d)
                        <option value="{{ $d['code'] }}" @selected($deptFilter === $d['code'])>{{ $d['name'] ?? $d['code'] }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="px-4 pb-6 space-y-3">

    @if(session('success'))<div class="bg-emerald-50 text-emerald-700 px-3 py-2 rounded-xl text-xs border border-emerald-200">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 text-red-700 px-3 py-2 rounded-xl text-xs border border-red-200">{{ session('error') }}</div>@endif

    {{-- ═══════ STAT CARDS: TOTAL STAFF + ONE PER QUARTER ═══════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">

        <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-7 h-7 rounded-lg bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.768-.231-1.48-.634-2.072M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.768.231-1.48.634-2.072m9.732 0A6.001 6.001 0 0012 6a6 6 0 00-4.366 9.928"/></svg>
                </span>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Staff</p>
            </div>
            <p class="text-3xl font-black text-slate-900 leading-none">{{ $totalStaff }}</p>
            <p class="text-[10px] text-slate-400 mt-2">{{ $deptFilter === 'ALL' ? 'Across all departments' : 'In ' . $deptFilter }}</p>
        </div>

        @foreach($quarters as $q)
            @php
                $qs = $quarterSummary[$q];
                $qColor = $quarterCardColors[$q];
                $avgBand = $qs['average_score'] !== null ? $bandMeta[
                    $qs['average_score'] >= 90 ? 'outstanding' : ($qs['average_score'] >= 70 ? 'meets_expectations' : ($qs['average_score'] >= 50 ? 'below_average' : 'unsatisfactory'))
                ] : null;
            @endphp
            <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] p-4" style="border-top:3px solid {{ $qColor }};">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[10px] font-black uppercase tracking-widest" style="color:{{ $qColor }};">{{ $q }}</p>
                    @if($avgBand)
                        <span class="text-[8px] font-black px-2 py-0.5 rounded-full" style="background:{{ $avgBand['bg'] }};color:{{ $avgBand['text'] }};">{{ $avgBand['label'] }}</span>
                    @endif
                </div>
                <p class="text-2xl font-black leading-none text-slate-900">
                    {{ $qs['average_score'] !== null ? number_format($qs['average_score'], 1) : '—' }}
                </p>
                <p class="text-[9px] text-slate-400 mt-1">avg score{{ $qs['completed'] > 0 ? ' · ' . $qs['completed'] . ' completed' : '' }}</p>
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden mt-2.5">
                    <div class="h-full rounded-full" style="width:{{ $qs['participation_rate'] }}%;background:{{ $qColor }};"></div>
                </div>
                <p class="text-[9px] text-slate-400 mt-1">{{ $qs['participation_rate'] }}% submitted ({{ $qs['submitted'] }}/{{ $totalStaff }})</p>
            </div>
        @endforeach

    </div>

    {{-- ═══════ BAND LEGEND ═══════ --}}
    <div class="flex flex-wrap items-center gap-2 px-1">
        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mr-1">Legend</span>
        @foreach($bandMeta as $key => $b)
            <span class="text-[9px] font-black px-2.5 py-1 rounded-full" style="background:{{ $b['bg'] }}22;color:{{ $b['bg'] === '#FFD700' ? '#8a6d00' : $b['bg'] }};">{{ $b['label'] }} ({{ $b['range'] }})</span>
        @endforeach
        <span class="text-[9px] font-black px-2.5 py-1 rounded-full bg-slate-100 text-slate-500">Not Submitted</span>
        <span class="text-[9px] font-black px-2.5 py-1 rounded-full" style="background:#FEF3C722;color:#B45309;">Awaiting Sign-off</span>
    </div>

    {{-- ═══════ STAFF × QUARTER TABLE ═══════ --}}
    <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden flex flex-col">
        <div class="p-4 pb-2 flex items-center justify-between flex-wrap gap-2">
            <div>
                <p class="text-[11px] font-black text-slate-800">All Staff — Quarterly Evaluation Summary</p>
                <p class="text-[9px] text-slate-400 mt-0.5">Grouped by seniority (SLT → VP → Manager → Executive), each staff member's Q1–Q4 appraisal status/score</p>
            </div>
            <input
                type="text"
                id="staffSearch"
                onkeyup="filterStaffSearch()"
                placeholder="Search name or ID…"
                class="text-xs rounded-lg px-3 py-1.5 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 no-print"
            >
        </div>
        <div class="overflow-y-auto overflow-x-auto thin-scroll flex-1" style="max-height:600px;">
            <table class="w-full min-w-[860px] text-left">
                <thead class="sticky top-0 bg-white z-10">
                    <tr class="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-500 font-black border-b border-[#E5E7EB]">
                        <th class="px-3 py-2">ID</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">Department</th>
                        @foreach($quarters as $q)
                            <th class="px-3 py-2 text-center">{{ $q }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-center">Average</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50" id="staffListBody">
                    @forelse($staffRows as $row)
                        @php
                            $group = $roleGroups[$row['role']] ?? null;
                        @endphp
                        @if($group !== $currentRoleGroup)
                            @php $currentRoleGroup = $group; @endphp
                            <tr class="role-group-row">
                                <td colspan="{{ 4 + count($quarters) + 1 }}" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400 border-t border-[#E5E7EB]">
                                    {{ $group ?? 'Other Staff' }}
                                </td>
                            </tr>
                        @endif
                        @php $roleStyle = $roleBadgeStyle[$row['role']] ?? 'background:#F1F5F9;color:#64748B;'; @endphp
                        <tr class="hover:bg-slate-50 staff-row" data-search="{{ strtolower($row['name'] . ' ' . $row['employee_id']) }}">
                            <td class="px-3 py-2 text-[10px] font-bold text-slate-500">{{ $row['employee_id'] }}</td>
                            <td class="px-3 py-2 text-[11px] font-black text-slate-900">{{ $row['name'] }}</td>
                            <td class="px-3 py-2"><span class="text-[9px] font-black px-2 py-0.5 rounded" style="{{ $roleStyle }}">{{ $row['role'] }}</span></td>
                            <td class="px-3 py-2 text-[10px] text-slate-500">{{ $row['department'] }}</td>
                            @foreach($quarters as $q)
                                @php $cell = $row['quarters'][$q]; @endphp
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        onclick="openQuarterModal('{{ $row['uuid'] }}', '{{ $q }}', '{{ addslashes($row['name']) }}')"
                                        title="View {{ $row['name'] }}'s {{ $q }} evaluation"
                                        class="cursor-pointer hover:-translate-y-0.5 hover:shadow-md transition"
                                    >
                                    @if($cell['score'] !== null)
                                        <span class="q-pill" style="background:{{ $cell['bg'] }}22;">
                                            <span class="score-pill-score" style="color:{{ $cell['bg'] === '#FFD700' ? '#8a6d00' : $cell['bg'] }};">{{ number_format($cell['score'], 1) }}</span>
                                            <span class="score-pill-label" style="color:{{ $cell['bg'] === '#FFD700' ? '#8a6d00' : $cell['bg'] }};">{{ $cell['label'] }}</span>
                                        </span>
                                    @else
                                        @php $sm = $statusMeta[$cell['status_key']] ?? $statusMeta['not_submitted']; @endphp
                                        <span class="q-pill" style="background:{{ $sm['bg'] }};">
                                            <span class="score-pill-score" style="color:{{ $sm['text'] }};">—</span>
                                            <span class="score-pill-label" style="color:{{ $sm['text'] }};">{{ $cell['label'] }}</span>
                                        </span>
                                    @endif
                                    </button>
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-center">
                                @if($row['average_score'] !== null)
                                    <span class="text-[12px] font-black" style="color:{{ $row['average_band']['bg'] === '#FFD700' ? '#8a6d00' : $row['average_band']['bg'] }};">{{ number_format($row['average_score'], 1) }}</span>
                                @else
                                    <span class="text-[12px] font-black text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($quarters) + 1 }}" class="px-3 py-8 text-center text-[11px] text-slate-400">No staff match this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>

{{-- ═══════ QUARTER EVALUATION POPUP ═══════ --}}
{{-- Reuses the existing /performance/appraise/{employeeId}/{quarter} page --
     the same read access BTS/HR already have via isCrossCompanyMonitorSession()
     in PerformanceController::appraiserReport() -- just opened as an overlay
     instead of a full navigation, so the summary list stays behind it. --}}
<div
    id="quarterModalOverlay"
    class="hidden fixed inset-0 z-[10000] bg-slate-950 flex items-center justify-center p-3 sm:p-6 no-print"
    onclick="if (event.target === this) closeQuarterModal();"
>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[92vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-[#D4AF37]/30 bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white shrink-0">
            <div>
                <p class="text-[9px] uppercase tracking-widest text-[#D4AF37] font-black">Quarterly Evaluation</p>
                <p id="quarterModalTitle" class="text-sm font-black"></p>
            </div>
            <button type="button" onclick="closeQuarterModal()" aria-label="Close"
                class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="relative flex-1 bg-slate-50">
            <div id="quarterModalLoading" class="absolute inset-0 flex items-center justify-center text-xs font-bold text-slate-400">Loading…</div>
            <iframe id="quarterModalFrame" src="about:blank" onload="onQuarterFrameLoad(this)" class="relative z-10 w-full h-full border-0"></iframe>
        </div>
    </div>
</div>

<script>
function openQuarterModal(employeeUuid, quarter, staffName) {
    document.getElementById('quarterModalTitle').textContent = staffName + ' — ' + quarter;
    document.getElementById('quarterModalLoading').style.display = 'flex';
    document.getElementById('quarterModalFrame').src = '/performance/appraise/' + employeeUuid + '/' + quarter.toLowerCase();
    document.getElementById('quarterModalOverlay').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeQuarterModal() {
    document.getElementById('quarterModalOverlay').classList.add('hidden');
    document.getElementById('quarterModalFrame').src = 'about:blank';
    document.body.style.overflow = '';
}
// The embedded page is a full standalone page with its own sidebar + "ml-[230px]"
// main content offset + ANIRA chat widget -- fine on a normal navigation, but
// redundant/cramped inside this popup, so strip them once the frame loads.
// Same-origin, so this always succeeds; wrapped in try/catch anyway in case the
// frame ever lands on about:blank or an error page with no such elements.
function onQuarterFrameLoad(frame) {
    document.getElementById('quarterModalLoading').style.display = 'none';
    try {
        var doc = frame.contentDocument || frame.contentWindow.document;
        doc.getElementById('sidebar')?.remove();
        doc.getElementById('aiChatBubble')?.remove();
        doc.getElementById('aiChatPanel')?.remove();
        var main = doc.getElementById('mainContent');
        if (main) main.classList.remove('ml-[230px]');
    } catch (e) {
        // ignore -- cross-origin or empty frame, popup still shows the full page
    }
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeQuarterModal();
});

function filterStaffSearch() {
    var q = document.getElementById('staffSearch').value.trim().toLowerCase();
    var lastGroupVisible = {};
    document.querySelectorAll('#staffListBody tr.staff-row').forEach(function (tr) {
        var matches = q === '' || tr.dataset.search.indexOf(q) !== -1;
        tr.style.display = matches ? '' : 'none';
    });
    document.querySelectorAll('#staffListBody .role-group-row').forEach(function (header) {
        var next = header.nextElementSibling;
        var anyVisible = false;
        while (next && !next.classList.contains('role-group-row')) {
            if (next.style.display !== 'none') anyVisible = true;
            next = next.nextElementSibling;
        }
        header.style.display = anyVisible ? '' : 'none';
    });
}
</script>

</body>
</html>
