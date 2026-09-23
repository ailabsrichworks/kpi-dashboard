<!DOCTYPE html>
<html>
<head>
    <title>Titan KPI Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        #sidebar, #sidebar * { font-family: 'Inter', sans-serif; }
        .month-row:nth-child(even) { background: rgba(248,250,252,.7); }
        .score-bar { height: 4px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
        .score-fill { height: 100%; border-radius: 999px; transition: width .4s ease; }
        @media print {
            #sidebar, .no-print { display: none !important; }
            #mainContent { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-[#f0f2f7]">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300 bg-[#f0f2f7]">
<div class="p-6 space-y-5">

    {{-- ── COLLAPSE ALL / EXPAND ALL ──────────────────────────────────── --}}
    @if(count($viewStaff) > 1)
    <div class="flex items-center gap-2 no-print">
        <button onclick="collapseAll()"
            class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-black border-2 border-[#1a3d34] text-[#1a3d34] hover:bg-[#1a3d34] hover:text-white transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
            </svg>
            Collapse All
        </button>
        <button onclick="expandAll()"
            class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-black border-2 border-[#1a3d34] text-[#1a3d34] hover:bg-[#1a3d34] hover:text-white transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
            Expand All
        </button>
    </div>
    @endif

    {{-- ── STAFF TABS (manager sees all; executive sees only self) ─────── --}}
    @if($isManager && count($viewStaff) > 1)
    <div class="flex flex-wrap gap-2 no-print" id="staffTabs">
        @foreach($viewStaff as $i => $emp)
        <button onclick="switchStaff('{{ $emp['id'] }}')" id="tab-{{ $emp['id'] }}"
            class="staff-tab px-4 py-2 rounded-xl text-xs font-black border-2 transition
            {{ $i === 0
                ? 'active-tab border-[#1a3d34] bg-[#1a3d34] text-white'
                : 'border-slate-200 text-slate-600 hover:border-[#1a3d34] hover:text-[#1a3d34]' }}">
            {{ $emp['short_name'] ?? $emp['employee_id'] }}
        </button>
        @endforeach
    </div>
    @endif

    {{-- ── KPI TABLES PER EMPLOYEE ────────────────────────────────────── --}}
    @php
        $kpiDefs = \App\Http\Controllers\TitanKpiController::KPIS;
        $months  = \App\Http\Controllers\TitanKpiController::MONTHS;
        $currentMonth = (int) now()->format('n');
    @endphp

    @foreach($viewStaff as $emp)
    @php
        $empData = $monthlyData[$emp['id']] ?? [];

        // Compute YTD totals for summary cards
        $ytdRevActual = $ytdRevTarget = $ytdRetActual = $ytdRetTarget = 0;
        $ytdFinalScore = 0; $scoreCount = 0;
        foreach (['revenue','retention'] as $kk) {
            foreach ($empData[$kk] ?? [] as $mn => $r) {
                if ($kk === 'revenue') { $ytdRevActual += $r['actual']; $ytdRevTarget += $r['base_target']; }
                else                  { $ytdRetActual += $r['actual']; $ytdRetTarget += $r['base_target']; }
                $sc = $r['base_target'] > 0 ? min(100, ($r['actual'] / $r['base_target']) * 100) : 0;
                $ytdFinalScore += $sc * $r['weightage'] / 100;
                $scoreCount++;
            }
        }
        $ytdRevScore = $ytdRevTarget > 0 ? min(100, ($ytdRevActual / $ytdRevTarget) * 100) : 0;
        $ytdRetScore = $ytdRetTarget > 0 ? min(100, ($ytdRetActual / $ytdRetTarget) * 100) : 0;
    @endphp

    <div class="staff-block bg-white rounded-2xl shadow-sm border border-[#6B9080]/30 overflow-hidden {{ $loop->first ? '' : 'hidden' }}"
         data-emp="{{ $emp['id'] }}">

        {{-- Employee header (click to toggle) --}}
        <div class="px-6 py-4 theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] flex items-center justify-between cursor-pointer select-none"
             onclick="toggleCard('{{ $emp['id'] }}')">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center text-white font-black text-sm shrink-0">
                    {{ strtoupper(substr($emp['short_name'] ?? 'X', 0, 1)) }}
                </div>
                <div>
                    <p class="text-white font-black text-sm">{{ $emp['short_name'] ?? $emp['employee_id'] }}</p>
                    <p class="text-[#A4C3B2] text-[10px] font-semibold uppercase tracking-wide">{{ $emp['role'] }}</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <p class="text-[9px] text-[#A4C3B2] uppercase font-black tracking-wide">YTD Collection</p>
                    <p class="text-white font-black text-sm">RM {{ number_format($ytdRevActual) }}</p>
                </div>
                <div class="text-right hidden sm:block">
                    <p class="text-[9px] text-[#A4C3B2] uppercase font-black tracking-wide">YTD Retention</p>
                    <p class="text-white font-black text-sm">{{ $ytdRetActual }} / {{ $ytdRetTarget }}</p>
                </div>
                {{-- Chevron toggle --}}
                <div id="chevron-{{ $emp['id'] }}"
                     class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition shrink-0">
                    <svg class="w-4 h-4 text-white transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- KPI table (collapsible) --}}
        <div id="body-{{ $emp['id'] }}" class="card-body overflow-x-auto transition-all duration-300">
        <div class="overflow-x-auto">
            <table class="w-full text-[12px]">
                <thead>
                    <tr class="bg-emerald-700 text-white">
                        <th class="px-4 py-2.5 text-left font-black text-[10px] uppercase tracking-wider w-16">Category</th>
                        <th class="px-3 py-2.5 text-left font-black text-[10px] uppercase tracking-wider w-10">#</th>
                        <th class="px-4 py-2.5 text-left font-black text-[10px] uppercase tracking-wider">KPI / Month</th>
                        <th class="px-4 py-2.5 text-right font-black text-[10px] uppercase tracking-wider w-32">Actual</th>
                        <th class="px-4 py-2.5 text-right font-black text-[10px] uppercase tracking-wider w-32">Base Target</th>
                        <th class="px-4 py-2.5 text-right font-black text-[10px] uppercase tracking-wider w-24">Score (%)</th>
                        <th class="px-4 py-2.5 text-right font-black text-[10px] uppercase tracking-wider w-28">Weightage (%)</th>
                        <th class="px-4 py-2.5 text-right font-black text-[10px] uppercase tracking-wider w-28">Final Score</th>
                    </tr>
                </thead>
                <tbody>

                    {{-- Category header row: Financial (60%) — coloured by the same
                         category scheme used everywhere else (Financial = emerald),
                         not the accent theme, so it stays readable whatever accent
                         colour is picked. --}}
                    <tr class="bg-emerald-50/60 border-b border-slate-200">
                        <td rowspan="{{ count($kpiDefs) * count($months) + count($kpiDefs) + 1 }}"
                            class="px-4 py-3 align-middle text-center border-r border-emerald-200 bg-emerald-100">
                            <div class="writing-vertical font-black text-[11px] text-emerald-800 uppercase tracking-wide"
                                 style="writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap">
                                Financial (60%)
                            </div>
                        </td>
                        <td colspan="7" class="px-4 py-2 bg-emerald-50/80">
                            <span class="text-[10px] text-emerald-700 font-semibold italic">Category: Financial</span>
                        </td>
                    </tr>

                    @foreach($kpiDefs as $kpiKey => $kpiDef)

                    {{-- KPI title row --}}
                    <tr class="border-b border-slate-300 bg-slate-50">
                        <td class="px-3 py-2 text-slate-700 font-black text-[11px] align-middle">{{ $kpiDef['no'] }}</td>
                        <td colspan="6" class="px-4 py-3">
                            <p class="font-black text-slate-800 text-[13px]">{{ $kpiDef['title'] }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5 italic">{{ $kpiDef['desc'] }}</p>
                        </td>
                    </tr>

                    {{-- Month rows --}}
                    @foreach($months as $monthNum => $monthName)
                    @php
                        $row       = $empData[$kpiKey][$monthNum] ?? null;
                        $actual    = (float)($row['actual']      ?? 0);
                        $base      = (float)($row['base_target'] ?? 0);
                        $weightage = (float)($row['weightage']   ?? 10);
                        $score     = $base > 0 ? min(100, round($actual / $base * 100, 1)) : 0;
                        $final     = round($score * $weightage / 100, 2);
                        $isFuture  = $monthNum > $currentMonth;
                        $hasData   = $row !== null && ($actual > 0 || $base > 0);
                        $scoreColor = $score >= 100 ? 'text-emerald-600' : ($score >= 80 ? 'text-blue-600' : ($score >= 50 ? 'text-amber-600' : 'text-red-500'));
                        $fillColor  = $score >= 100 ? 'bg-emerald-500' : ($score >= 80 ? 'bg-blue-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-red-400'));
                    @endphp
                    <tr class="month-row border-b border-slate-100 {{ $isFuture && !$hasData ? 'opacity-40' : '' }}">
                        <td class="px-3 py-2 text-slate-400 text-[10px]"></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full {{ $hasData ? 'bg-emerald-500' : ($isFuture ? 'bg-slate-200' : 'bg-amber-400') }} shrink-0"></span>
                                <span class="font-semibold text-slate-700">{{ $monthName }}</span>
                                @if($monthNum === $currentMonth)
                                <span class="text-[9px] bg-emerald-100 text-emerald-800 font-black px-1.5 py-0.5 rounded-full">Current</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right font-black text-slate-800">
                            @if($hasData)
                                @if($kpiKey === 'revenue') RM {{ number_format($actual) }}
                                @else {{ number_format($actual) }}
                                @endif
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right text-slate-600">
                            @if($hasData)
                                @if($kpiKey === 'revenue') RM {{ number_format($base) }}
                                @else {{ number_format($base) }}
                                @endif
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @if($hasData)
                            <div>
                                <span class="font-black {{ $scoreColor }}">{{ $score }}%</span>
                                <div class="score-bar mt-1 w-16 ml-auto">
                                    <div class="score-fill {{ $fillColor }}" style="width:{{ $score }}%"></div>
                                </div>
                            </div>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right text-slate-600 font-semibold">{{ $weightage }}%</td>
                        <td class="px-4 py-2.5 text-right">
                            @if($hasData)
                                <span class="font-black {{ $scoreColor }}">{{ $final }}</span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    @endforeach

                    {{-- YTD total row --}}
                    <tr class="bg-emerald-800 text-white border-t-2 border-emerald-800">
                        <td colspan="2" class="px-4 py-3 font-black text-[11px] uppercase tracking-wider text-[#A4C3B2]">YTD Total</td>
                        <td class="px-4 py-3 text-right font-black">RM {{ number_format($ytdRevActual) }}</td>
                        <td class="px-4 py-3 text-right font-black text-[#A4C3B2]">RM {{ number_format($ytdRevTarget) }}</td>
                        <td class="px-4 py-3 text-right font-black">
                            {{ $ytdRevTarget > 0 ? round($ytdRevActual/$ytdRevTarget*100,1) : 0 }}%
                        </td>
                        <td class="px-4 py-3 text-right text-[#A4C3B2] font-semibold">—</td>
                        <td class="px-4 py-3 text-right font-black text-emerald-300">
                            {{ round($ytdFinalScore, 2) }}
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
    </div>
    @endforeach

    @if(empty($viewStaff))
    <div class="bg-white rounded-2xl p-10 text-center shadow-sm border border-[#6B9080]/20">
        <p class="text-[#6B9080] text-sm">No staff data found for Titan department.</p>
    </div>
    @endif

</div>
</main>

<script>
// ── Card collapse / expand ─────────────────────────────────────────────────
function toggleCard(empId) {
    const body    = document.getElementById('body-' + empId);
    const chevron = document.getElementById('chevron-' + empId)?.querySelector('svg');
    const isHidden = body.classList.contains('hidden');
    body.classList.toggle('hidden', !isHidden);
    if (chevron) chevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
}

function collapseAll() {
    document.querySelectorAll('.card-body').forEach(b => b.classList.add('hidden'));
    document.querySelectorAll('[id^="chevron-"] svg').forEach(s => s.style.transform = 'rotate(-90deg)');
}

function expandAll() {
    document.querySelectorAll('.card-body').forEach(b => b.classList.remove('hidden'));
    document.querySelectorAll('[id^="chevron-"] svg').forEach(s => s.style.transform = 'rotate(0deg)');
}

// ── Staff tab switching ─────────────────────────────────────────────────────
function switchStaff(empId) {
    const blocks = document.querySelectorAll('.staff-block');
    const tabs   = document.querySelectorAll('.staff-tab');

    blocks.forEach(b => {
        b.classList.toggle('hidden', b.dataset.emp !== empId);
    });

    tabs.forEach(t => {
        const active = t.id === 'tab-' + empId;
        t.classList.toggle('active-tab', active);
        t.classList.toggle('border-[#1a3d34]', active);
        t.classList.toggle('bg-[#1a3d34]', active);
        t.classList.toggle('text-white', active);
        t.classList.toggle('border-slate-200', !active);
        t.classList.toggle('text-slate-600', !active);
    });
}

</script>

</body>
</html>
