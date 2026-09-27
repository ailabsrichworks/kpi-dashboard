<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLT Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .thin-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .band-pill.active { outline: 2px solid #1e293b; outline-offset: 1px; }
        .role-group-row td { background: #F8FAFC; }
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
        'not_submitted'    => ['label' => 'Not Submitted',      'bg' => '#FEE2E2', 'text' => '#DC2626'],
        'pending'          => ['label' => 'Awaiting Appraisal', 'bg' => '#F1F5F9', 'text' => '#64748B'],
        'awaiting_signoff' => ['label' => 'Awaiting Sign-off',  'bg' => '#FEF3C7', 'text' => '#B45309'],
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
@endphp

<main id="mainContent" class="ml-[230px] min-h-screen">

{{-- ═══════ HEADER (sticky) ═══════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="relative">
            <h1 class="text-xl font-black tracking-tight leading-tight">
                SLT Dashboard | {{ $quarter }} {{ $currentFinancialYear }}
            </h1>
            <p class="text-[11px] text-white/60 mt-1">{{ now()->timezone('Asia/Kuala_Lumpur')->format('d M Y') }} · Who has completed their quarterly appraisal, and how the team scored</p>
        </div>

        <form method="GET" action="{{ route('slt-dashboard') }}" class="relative flex flex-wrap items-center gap-2">
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Quarter</label>
                <select name="quarter" onchange="this.form.submit()" class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20">
                    @foreach(['Q1','Q2','Q3','Q4'] as $q)
                        <option value="{{ $q }}" @selected($quarter === $q)>{{ $q }}</option>
                    @endforeach
                </select>
            </div>
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

<div class="px-4 pb-4 space-y-3">

    {{-- ═══════ STAT CARDS ═══════ --}}
    @php
        $stages = [
            ['key' => 'not_submitted',    'label' => 'Not Submitted',      'count' => $notSubmittedCount,    'bg' => '#DC2626', 'soft' => '#FEE2E2', 'text' => '#DC2626', 'hint' => 'Staff hasn\'t started'],
            ['key' => 'pending',          'label' => 'Awaiting Appraisal', 'count' => $pendingCount,         'bg' => '#94A3B8', 'soft' => '#F1F5F9', 'text' => '#64748B', 'hint' => 'Waiting on their boss'],
            ['key' => 'awaiting_signoff', 'label' => 'Awaiting Sign-off',  'count' => $awaitingSignoffCount, 'bg' => '#F59E0B', 'soft' => '#FEF3C7', 'text' => '#B45309', 'hint' => 'Waiting on staff to sign'],
            ['key' => 'completed',        'label' => 'Completed',          'count' => $completedCount,       'bg' => '#10B981', 'soft' => '#D1FAE5', 'text' => '#047857', 'hint' => 'Fully signed off & scored'],
        ];
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">

        <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-7 h-7 rounded-lg bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.768-.231-1.48-.634-2.072M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.768.231-1.48.634-2.072m9.732 0A6.001 6.001 0 0012 6a6 6 0 00-4.366 9.928"/></svg>
                </span>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Staff Engagement</p>
            </div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-slate-500 font-semibold">Total Staff</span>
                <span class="text-2xl font-black text-slate-900">{{ $totalStaff }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500 font-semibold">Have Submitted</span>
                <span class="text-2xl font-black text-[#B8860B]">{{ $participationRate }}%</span>
            </div>
            <p class="text-[9px] text-slate-400 mt-2">% of staff who have submitted their self-assessment for {{ $quarter }}</p>
        </div>

        {{-- APPRAISAL PROGRESS FUNNEL — shows exactly what stage every staff member is at --}}
        <div class="md:col-span-2 bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Appraisal Progress</p>
            </div>

            {{-- Segmented stage bar --}}
            <div class="h-3 rounded-full overflow-hidden flex bg-slate-100">
                @foreach($stages as $s)
                    @if($s['count'] > 0)
                    <div title="{{ $s['label'] }}: {{ $s['count'] }}" style="width:{{ $totalStaff > 0 ? max(2, round($s['count'] / $totalStaff * 100, 1)) : 0 }}%;background:{{ $s['bg'] }};"></div>
                    @endif
                @endforeach
            </div>

            {{-- 4-stage legend, clickable to filter the staff list below --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3">
                @foreach($stages as $s)
                <button type="button" onclick="filterBand('{{ $s['key'] }}')" data-band="{{ $s['key'] }}"
                    class="band-pill stage-pill text-left rounded-xl px-2.5 py-2 border transition hover:-translate-y-0.5"
                    style="background:{{ $s['soft'] }};border-color:{{ $s['bg'] }}33;">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:{{ $s['bg'] }};"></span>
                        <span class="text-[9px] font-black uppercase tracking-wide truncate" style="color:{{ $s['text'] }};">{{ $s['label'] }}</span>
                    </div>
                    <div class="flex items-baseline justify-between mt-0.5">
                        <span class="text-lg font-black" style="color:{{ $s['text'] }};">{{ $s['count'] }}</span>
                        <span class="text-[8px] text-slate-400">{{ $totalStaff > 0 ? round($s['count'] / $totalStaff * 100) : 0 }}%</span>
                    </div>
                </button>
                @endforeach
            </div>
            <p class="text-[9px] text-slate-400 mt-2.5">Click a stage to filter the staff list · a name only counts as "Completed" once staff has signed the appraiser's review back</p>
        </div>

        <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-7 h-7 rounded-lg bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Average Score</p>
                </div>
                <p class="text-4xl font-black leading-none" style="color:{{ $averageBand['bg'] === '#FFD700' ? '#8a6d00' : $averageBand['bg'] }};">{{ number_format($averageScore, 1) }}</p>
                <p class="text-[10px] text-slate-400 mt-1">out of 100 · across {{ $completedCount }} completed appraisals</p>
            </div>
            <span class="text-[10px] font-black px-3 py-1.5 rounded-full" style="background:{{ $averageBand['bg'] }};color:{{ $averageBand['text'] }};">
                {{ $averageBand['label'] }}
            </span>
        </div>

    </div>

    {{-- ═══════ SCORE DISTRIBUTION + STAFF LIST ═══════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-3">

        {{-- Distribution chart --}}
        <div class="xl:col-span-2 bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
            <p class="text-[11px] font-black text-slate-800 mb-1">Performance Score Distribution</p>
            <p class="text-[9px] text-slate-400 mb-3">How many staff landed in each rating band this quarter</p>
            <div style="height:180px;position:relative;">
                <canvas id="chartBandDist"></canvas>
            </div>
            <div class="mt-4 space-y-1.5">
                @foreach($bandMeta as $key => $b)
                <button type="button" onclick="filterBand('{{ $key }}')" data-band="{{ $key }}"
                    class="band-pill w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-[10px] font-black transition"
                    style="background:{{ $b['bg'] }}22;color:{{ $b['bg'] === '#FFD700' ? '#8a6d00' : $b['bg'] }};">
                    <span>{{ $b['label'] }} <span class="font-normal opacity-70">({{ $b['range'] }})</span></span>
                    <span>{{ $bandCounts[$key] }} staff</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- Staff list --}}
        <div class="xl:col-span-3 bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden flex flex-col">
            <div class="p-4 pb-2 flex items-center justify-between flex-wrap gap-2">
                <div>
                    <p class="text-[11px] font-black text-slate-800">Staff List</p>
                    <p class="text-[9px] text-slate-400 mt-0.5" id="staffListSubtitle">All staff, grouped by seniority (SLT → VP → Manager → Executive)</p>
                </div>
                <div class="flex gap-1.5 flex-wrap justify-end">
                    <button type="button" onclick="filterBand('all')" data-band="all" class="band-pill active px-2.5 py-1 rounded-lg text-[9px] font-black bg-slate-100 text-slate-600">All ({{ $totalStaff }})</button>
                    <button type="button" onclick="filterBand('not_submitted')" data-band="not_submitted" class="band-pill px-2.5 py-1 rounded-lg text-[9px] font-black bg-red-50 text-red-600">Not Submitted ({{ $notSubmittedCount }})</button>
                    <button type="button" onclick="filterBand('pending')" data-band="pending" class="band-pill px-2.5 py-1 rounded-lg text-[9px] font-black bg-slate-100 text-slate-500">Awaiting Appraisal ({{ $pendingCount }})</button>
                    <button type="button" onclick="filterBand('awaiting_signoff')" data-band="awaiting_signoff" class="band-pill px-2.5 py-1 rounded-lg text-[9px] font-black bg-amber-50 text-amber-700">Awaiting Sign-off ({{ $awaitingSignoffCount }})</button>
                </div>
            </div>
            <div class="overflow-y-auto overflow-x-auto thin-scroll flex-1" style="max-height:460px;">
                <table class="w-full min-w-[620px] text-left">
                    <thead class="sticky top-0 bg-white z-10">
                        <tr class="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-500 font-black border-b border-[#E5E7EB]">
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Role</th>
                            <th class="px-3 py-2">Department</th>
                            <th class="px-3 py-2">Manager</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Score</th>
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
                                    <td colspan="7" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400 border-t border-[#E5E7EB]">
                                        {{ $group ?? 'Other Staff' }}
                                    </td>
                                </tr>
                            @endif
                            @php
                                $statusKey = $row['status_key'];
                                $isBand = isset($bandMeta[$statusKey]);
                                $meta = $isBand ? $bandMeta[$statusKey] : $statusMeta[$statusKey];
                                $roleStyle = $roleBadgeStyle[$row['role']] ?? 'background:#F1F5F9;color:#64748B;';
                            @endphp
                            <tr data-band="{{ $statusKey }}" class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-[10px] font-bold text-slate-500">{{ $row['employee_id'] }}</td>
                                <td class="px-3 py-2 text-[11px] font-black text-slate-900">{{ $row['name'] }}</td>
                                <td class="px-3 py-2"><span class="text-[9px] font-black px-2 py-0.5 rounded" style="{{ $roleStyle }}">{{ $row['role'] }}</span></td>
                                <td class="px-3 py-2 text-[10px] text-slate-500">{{ $row['department'] }}</td>
                                <td class="px-3 py-2 text-[10px] text-slate-500">{{ $row['manager'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-[9px] font-black px-2 py-0.5 rounded-full" style="background:{{ $isBand ? $meta['bg'].'22' : $meta['bg'] }};color:{{ $isBand && $meta['bg'] === '#FFD700' ? '#8a6d00' : $meta['text'] }};">{{ $meta['label'] }}</span>
                                </td>
                                <td class="px-3 py-2 text-right text-[11px] font-black" style="color:{{ $row['score'] !== null ? ($meta['bg'] === '#FFD700' ? '#8a6d00' : $meta['bg']) : '#cbd5e1' }};">
                                    {{ $row['score'] !== null ? number_format($row['score'], 1) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-[11px] text-slate-400">No staff match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const bandCounts = @json($bandCounts);
const bandMeta = @json($bandMeta);

new Chart(document.getElementById('chartBandDist'), {
    type: 'bar',
    data: {
        labels: Object.keys(bandMeta).map(k => bandMeta[k].label + ' (' + bandMeta[k].range + ')'),
        datasets: [{
            data: Object.keys(bandMeta).map(k => bandCounts[k]),
            backgroundColor: Object.keys(bandMeta).map(k => bandMeta[k].bg),
            borderRadius: 6,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
            y: { ticks: { font: { size: 10, weight: 'bold' } }, grid: { display: false } }
        }
    }
});

function filterBand(band) {
    var scoreBandKeys = Object.keys(bandMeta); // unsatisfactory, below_average, meets_expectations, outstanding
    document.querySelectorAll('.band-pill').forEach(function (btn) {
        btn.classList.toggle('active', btn.dataset.band === band);
    });
    document.querySelectorAll('#staffListBody tr').forEach(function (tr) {
        if (tr.classList.contains('role-group-row')) return; // group headers always follow their rows
        var rowBand = tr.dataset.band;
        var matches = band === 'all'
            || rowBand === band
            || (band === 'completed' && scoreBandKeys.indexOf(rowBand) !== -1);
        tr.style.display = matches ? '' : 'none';
    });
    // Hide a group header if every row under it is now hidden
    document.querySelectorAll('#staffListBody .role-group-row').forEach(function (header) {
        var next = header.nextElementSibling;
        var anyVisible = false;
        while (next && !next.classList.contains('role-group-row')) {
            if (next.style.display !== 'none') anyVisible = true;
            next = next.nextElementSibling;
        }
        header.style.display = anyVisible ? '' : 'none';
    });
    var subtitle = document.getElementById('staffListSubtitle');
    if (band === 'all') {
        subtitle.textContent = 'All staff, grouped by seniority (SLT → VP → Manager → Executive)';
    } else {
        var statusLabels = { not_submitted: 'Not Submitted', pending: 'Awaiting Appraisal', awaiting_signoff: 'Awaiting Sign-off', completed: 'Completed' };
        var label = bandMeta[band] ? bandMeta[band].label : (statusLabels[band] || band);
        subtitle.textContent = 'Showing: ' + label;
    }
}
</script>

</body>
</html>
