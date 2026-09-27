<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Dashboard</title>

    {{-- Preconnect to external hosts so DNS+TLS is resolved before requests fire --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://ui-avatars.com">

    {{-- Inter font — loaded here once for the whole page --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tailwind Play CDN — must be sync (it generates styles by scanning DOM) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        .brand-panel { background: radial-gradient(circle at top left,rgba(59,130,246,.16),transparent 30%), radial-gradient(circle at bottom right,rgba(20,184,166,.13),transparent 34%), linear-gradient(135deg,#06142f 0%,#0b1f45 52%,#020617 100%); }
        .soft-card   { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .thin-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .line-clamp-1 { display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden; }
        .line-clamp-2 { display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
        .dept-body { overflow:hidden; transition: max-height .4s ease, opacity .3s ease; }
        .dept-body.open   { max-height: 9999px; opacity:1; }
        .dept-body.closed { max-height: 0;      opacity:0; }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">

{{-- ═══════ HEADER (sticky) ════════════════════════════════════════════════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-6 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="pointer-events-none absolute -top-10 -right-10 w-48 h-48 rounded-full bg-[#D4AF37]/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-16 left-1/3 w-56 h-56 rounded-full bg-white/10 blur-3xl"></div>
        @php
            $greetHour = now()->timezone('Asia/Kuala_Lumpur')->hour;
            $greeting  = $greetHour < 12 ? 'Good Morning' : ($greetHour < 18 ? 'Good Afternoon' : 'Good Evening');
        @endphp
        <div class="relative">
            <h1 class="text-2xl font-black tracking-tight leading-tight">
                <span class="theme-header-text text-white/90">Hi, {{ $greeting }}</span>
                <span class="theme-header-text text-white/90">{{ $currentUserName }}</span>
                👋
            </h1>
        </div>
    </div>
</div>

<div class="px-4 pb-4 space-y-3">

@if(session('success'))<div class="bg-emerald-50 text-emerald-700 px-3 py-2 rounded-xl text-xs border border-emerald-200">{{ session('success') }}</div>@endif
@if(session('error'))<div class="bg-red-50 text-red-700 px-3 py-2 rounded-xl text-xs border border-red-200">{{ session('error') }}</div>@endif
@if($errors->any())<div class="bg-red-50 text-red-700 px-3 py-2 rounded-xl text-xs border border-red-200">{{ $errors->first() }}</div>@endif

{{-- ═══════ MY PERFORMANCE ══════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl overflow-hidden soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
@if($individualKpiCount === 0)
    {{-- Nothing to measure yet — one clear message + the same two actions,
         instead of a grid of stat boxes that would all just read "0" and
         four quarter cards that would all falsely read as "Critical" (red)
         for having no target rather than for missing one. --}}
    <div class="p-6 sm:p-7 flex flex-col sm:flex-row items-center gap-5">
        <div class="w-14 h-14 rounded-2xl bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
            <svg class="w-7 h-7 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h4m-7 5h10a2 2 0 002-2V7.828a2 2 0 00-.586-1.414l-3.828-3.828A2 2 0 0011.172 2H6a2 2 0 00-2 2v14a2 2 0 002 2Z"/>
            </svg>
        </div>
        <div class="flex-1 text-center sm:text-left">
            <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-1">My Performance · {{ $currentFinancialYear }}</p>
            <h2 class="text-base font-black text-slate-800">No KPIs set for {{ $currentFinancialYear }} yet</h2>
            <p class="text-xs text-slate-500 mt-1">Your score, quarterly progress and at-risk alerts will appear here as soon as your KPIs are created.</p>
        </div>
        <div class="flex gap-2 shrink-0">
            <a href="{{ route('kpi.index') }}" class="bg-[#D4AF37] hover:bg-[#c19c2f] text-[#1a1a1a] px-4 py-2.5 rounded-xl text-xs font-black transition">My KPIs</a>
            <a href="{{ route('weightage') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-black transition">Weightage</a>
        </div>
    </div>
@else
    <div class="flex flex-col lg:flex-row">

        {{-- Left: score panel --}}
        <div class="theme-perf-card p-5 lg:min-w-[240px] xl:min-w-[260px] flex flex-col justify-between">
            <div>
                <p class="theme-perf-accent-text text-[9px] uppercase tracking-widest font-black mb-3">My Performance · {{ $currentFinancialYear }}</p>
                <div class="flex items-center gap-3 mb-4">
                    <div class="theme-header-accent-ring w-10 h-10 rounded-full overflow-hidden shrink-0 ring-2 ring-[#D4AF37]/60">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($currentUserName) }}&background=D4AF37&color=1a1a1a&size=40" class="w-full h-full object-cover"/>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-slate-800 leading-tight">{{ $currentUserName }}</h2>
                        <p class="text-[9px] text-slate-500 mt-0.5">{{ $userPosition }} · {{ $currentDepartment }}</p>
                    </div>
                </div>
                @if($individualWeightage <= 0)
                    <div class="bg-white rounded-xl p-3">
                        <p class="text-3xl font-black text-slate-300 mb-1">—</p>
                        <p class="text-xs text-slate-400">{{ $individualKpiCount }} KPIs · weightage not set</p>
                        <a href="{{ route('weightage') }}" class="theme-header-dark-text inline-block mt-2 text-xs font-black text-[#7A0019] underline">Set weightage →</a>
                    </div>
                @else
                    <div class="bg-white rounded-xl p-3">
                        <div class="flex items-end gap-1.5 mb-2">
                            <span class="text-4xl font-black leading-none {{ $individualScoreStyle['text'] }}">{{ number_format($individualPerformance,1) }}</span>
                            <span class="text-lg font-black text-slate-300 mb-0.5">%</span>
                        </div>
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
                            <div class="h-1.5 rounded-full {{ $individualScoreStyle['bar'] }}" style="width:{{ min($individualPerformance,100) }}%"></div>
                        </div>
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-black border {{ $individualScoreStyle['badge'] }}">{{ $individualScoreStyle['label'] }}</span>
                        <p class="text-[9px] text-slate-400 mt-1.5">{{ $individualKpiCount }} KPIs · {{ number_format($individualWeightage,0) }}% weightage</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Stats + quarterly completion --}}
        <div class="flex-1 p-5 flex flex-col gap-5">
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                    <p class="text-3xl font-black text-slate-900">{{ $individualKpiCount }}</p>
                    <p class="text-[9px] text-slate-400 uppercase tracking-wide mt-1.5">Total KPIs</p>
                </div>
                <div class="bg-emerald-50 rounded-2xl p-4 text-center border border-emerald-100">
                    <p class="text-3xl font-black text-emerald-600">{{ $myOnTrack }}</p>
                    <p class="text-[9px] text-emerald-500 uppercase tracking-wide mt-1.5">On Track</p>
                </div>
                @if($myAtRisk > 0)
                <div class="bg-red-50 rounded-2xl p-4 text-center border border-red-100">
                    <p class="text-3xl font-black text-red-600">{{ $myAtRisk }}</p>
                    <p class="text-[9px] text-red-400 uppercase tracking-wide mt-1.5">At Risk</p>
                </div>
                @else
                <div class="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                    <p class="text-3xl font-black text-slate-300">0</p>
                    <p class="text-[9px] text-slate-400 uppercase tracking-wide mt-1.5">At Risk</p>
                </div>
                @endif
            </div>

            @if($myAtRiskKpis->isNotEmpty())
            <div class="bg-red-50 border border-red-100 rounded-2xl p-3">
                <p class="text-[9px] font-black text-red-500 uppercase tracking-widest mb-1.5">⚠ Needs Attention</p>
                <ul class="space-y-0.5">
                    @foreach($myAtRiskKpis as $title)
                    <li class="text-[11px] text-red-700 font-semibold truncate">· {{ $title }}</li>
                    @endforeach
                </ul>
                @if($myAtRisk > $myAtRiskKpis->count())
                <a href="{{ route('kpi.index') }}" class="text-[9px] text-red-500 underline font-bold">+{{ $myAtRisk - $myAtRiskKpis->count() }} more →</a>
                @endif
            </div>
            @endif

            <div>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">My Quarterly Progress</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach(['Q1','Q2','Q3','Q4'] as $qi)
                    @php
                        $qc  = $myCompletedByQ[$qi]; $qt = $myTotalByQ[$qi];
                        $ppct = $myProgressByQ[$qi];
                        // Colour only reflects a REPORTED result. A quarter with no KPI
                        // target (qt=0), or one where nothing has been signed off yet
                        // (qc=0 — likely not started/evaluated), isn't a "Critical" 0% —
                        // it's simply pending, so it gets neutral grey. Red/amber/green
                        // only kick in once at least one KPI has actually been signed off.
                        $qPending = $qc === 0;
                        $pstyle = $qPending ? ['bar' => 'bg-slate-200', 'text' => 'text-slate-400'] : $scoreStyle($ppct);
                    @endphp
                    <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-black text-slate-700">{{ $qi }}</span>
                            <span class="text-[10px] font-black {{ $pstyle['text'] }}">{{ $qPending ? '—' : $ppct.'%' }}</span>
                        </div>
                        <div class="h-1.5 bg-slate-200 rounded-full overflow-hidden mb-1.5">
                            <div class="h-1.5 rounded-full {{ $pstyle['bar'] }}" style="width:{{ $qPending ? 100 : min($ppct,100) }}%"></div>
                        </div>
                        <p class="text-[8px] text-slate-400">
                            {{ $qt === 0 ? 'No KPIs' : ($qPending ? 'Pending' : number_format($ppct,0).'% of target') }}
                            @if($qt > 0)
                                · {{ $qc == $qt ? '✓ Signed off' : $qc.'/'.$qt.' signed off' }}
                            @endif
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
</div>

{{-- ═══════ COMPANY OVERVIEW TOGGLE ════════════════════════════════════════ --}}
@php $rankingCount = count($companyDeptRanking ?? []); @endphp
@if($rankingCount > 0 || $deptRows->count() > 0)
<div>
    <button onclick="toggleCompanySection()"
        class="w-full flex items-center justify-between bg-white rounded-2xl px-5 py-4 border border-[#E5E7EB] border-l-[4px] border-l-[#D4AF37] soft-card hover:bg-slate-50/60 transition">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div class="text-left">
                <p class="text-sm font-black text-slate-800">Company Overview</p>
                <p class="text-[9px] text-slate-400 mt-0.5">Department ranking · team performance · quarterly trends</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span id="companyToggleBadge" class="text-[9px] font-black text-[#B8860B] bg-[#D4AF37]/10 px-2.5 py-1 rounded-full">Show</span>
            <svg id="companyChevron" class="w-4 h-4 text-slate-400 transition-transform duration-300" style="transform:rotate(-90deg)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </button>

    {{-- Collapsible company content --}}
    <div id="companySectionWrapper" class="space-y-3 mt-3" style="display:none">

{{-- ── DEPT RANKING CONTENT ──────────────────────────────────────────── --}}
@if($rankingCount > 0 || $deptRows->count() > 0)
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 items-start">

    {{-- Card 1: Department Annual Ranking --}}
    <div class="{{ $isManager ? 'xl:col-span-2' : 'sm:col-span-2 xl:col-span-5' }} bg-white rounded-2xl overflow-hidden soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
        <div class="p-4">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-[11px] font-black text-slate-800 leading-tight">Department Annual Ranking</h3>
                    <p class="text-[9px] text-slate-400 mt-0.5">{{ $rankingCount }} departments · by achievement</p>
                </div>
                <span class="text-[9px] font-bold text-[#B8860B] bg-[#D4AF37]/10 px-2 py-0.5 rounded-full">{{ $currentFinancialYear }}</span>
            </div>
            <div style="height:{{ max(80, $rankingCount * 28) }}px; position:relative;">
                <canvas id="chartDeptRanking"></canvas>
            </div>
        </div>
    </div>

    @if($isManager)

    {{-- Card 2: Department Achievement --}}
    <div class="bg-white rounded-2xl overflow-hidden soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] flex flex-col">
        <div class="p-4 flex flex-col items-center text-center flex-1">
            {{-- label --}}
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">{{ $currentDepartment }} Achievement</p>
            {{-- donut centred --}}
            <div class="relative mb-3" style="width:88px;height:88px;">
                <canvas id="chartCompanyDonut" width="88" height="88"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <p class="text-[15px] font-black leading-none {{ $myDeptScoreStyle['text'] }}">{{ number_format($myDeptPerformance,1) }}%</p>
                </div>
            </div>
            {{-- status badge --}}
            <span class="inline-block text-[9px] font-black px-3 py-1 rounded-full border {{ $myDeptScoreStyle['badge'] }} mb-1">
                {{ $myDeptScoreStyle['label'] }}
            </span>
            <p class="text-[8px] text-slate-400 mb-3">{{ $totalStaffCount }} staff · {{ $currentFinancialYear }}</p>
            {{-- band breakdown --}}
            @php $bandList = [['#059669','Excellent'],['#D4AF37','Good'],['#F97316','Watch'],['#EF4444','Critical']]; @endphp
            <div class="w-full grid grid-cols-2 gap-x-3 gap-y-1.5 pt-3 border-t border-slate-100">
                @foreach($bandList as $bi => $b)
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $b[0] }}"></span>
                    <span class="text-[9px] font-bold text-slate-700">{{ $myDeptBands[$bi] }}</span>
                    <span class="text-[8px] text-slate-400 ml-0.5">{{ $b[1] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Card 3: Total Staff --}}
    <div class="bg-white rounded-2xl overflow-hidden soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] flex flex-col">
        <div class="p-4 flex flex-col items-center text-center flex-1 justify-between">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest w-full text-left mb-4">Total Staff</p>
            <div class="flex flex-col items-center flex-1 justify-center">
                {{-- icon badge --}}
                <div class="w-12 h-12 rounded-2xl bg-[#D4AF37]/10 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.768-.231-1.48-.634-2.072M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.768.231-1.48.634-2.072m9.732 0A6.001 6.001 0 0012 6a6 6 0 00-4.366 9.928"/>
                    </svg>
                </div>
                <p class="text-5xl font-black text-slate-900 leading-none">{{ $companyTotalStaff ?: $totalStaffCount }}</p>
                <p class="text-[10px] text-slate-400 mt-2">staff members</p>
            </div>
            <div class="w-full mt-4 pt-3 border-t border-slate-100 flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span class="text-[10px] font-bold text-slate-500">{{ $companyDeptCount ?: $deptRows->count() }} Departments</span>
            </div>
        </div>
    </div>

    {{-- Card 4: Completed Quarters --}}
    <div class="bg-white rounded-2xl overflow-hidden soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
        <div class="p-4">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-4">Completed Quarters</p>
            @foreach(['Q1','Q2','Q3','Q4'] as $qi)
            @php $qc = $totalCompletedByQ[$qi]; $qt = $totalByQ[$qi]; $pct = $qt > 0 ? round(($qc/$qt)*100) : 0; @endphp
            <div class="mb-3">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black text-slate-700">{{ $qi }}</span>
                        <span class="text-[8px] text-slate-400">{{ $qc }}/{{ $qt }} KPIs</span>
                    </div>
                    <span class="text-[10px] font-black {{ $pct >= 100 ? 'text-[#B8860B]' : ($pct > 0 ? 'text-amber-500' : 'text-slate-300') }}">{{ $pct }}%</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-2 rounded-full transition-all {{ $qc > 0 ? 'bg-[#D4AF37]' : 'bg-slate-200' }}" style="width:{{ $pct }}%"></div>
                </div>
            </div>
            @endforeach
            <div class="mt-3 pt-3 border-t border-slate-100">
                @php $annualPct = $totalKpisVisible > 0 ? round(($totalCompletedAnnual/$totalKpisVisible)*100) : 0; @endphp
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[9px] font-black text-slate-500">Annual Total</span>
                    <span class="text-[10px] font-black {{ $annualPct > 0 ? 'text-[#B8860B]' : 'text-slate-300' }}">{{ $annualPct }}%</span>
                </div>
                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-2.5 rounded-full {{ $totalCompletedAnnual > 0 ? 'theme-header-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019]' : 'bg-slate-200' }}" style="width:{{ $annualPct }}%"></div>
                </div>
                <p class="text-[8px] text-slate-400 mt-1 text-right">{{ $totalCompletedAnnual }}/{{ $totalKpisVisible }} KPIs done</p>
            </div>
        </div>
    </div>

    @endif

</div>
@endif

{{-- ═══════ TIER 2: DEPT ANALYTICS — managers+ only ══════════════════════ --}}
@if($isManager && $deptRows->count() > 0)

    {{-- Quarterly trend --}}
    <div class="bg-white rounded-2xl p-4 soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
        <h3 class="text-xs font-black text-slate-900">Quarterly Performance — All Departments</h3>
        <p class="text-[10px] text-slate-400 mt-0.5 mb-3">Q1 → Q4 avg score per dept · {{ $currentFinancialYear }}</p>
        <div style="height:130px; position:relative;">
            <canvas id="chartQuarterTrend"></canvas>
        </div>
    </div>

    {{-- Department Staff Breakdown accordion (starts collapsed) --}}
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-black text-slate-900">Department Staff Breakdown</h2>
                <p class="text-[10px] text-slate-400 mt-0.5">All staff · quarterly scores · sorted by annual achievement @if($isSltOffice) · click a staff row for full KPI breakdown @endif</p>
            </div>
            <button onclick="toggleAllDepts()" id="toggleAllBtn"
                    class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-black hover:bg-slate-200 transition">
                Expand All
            </button>
        </div>

        @foreach($deptRows as $dept)
            @php
                $dstyle   = $scoreStyle($dept['performance']);
                $safeCode = preg_replace('/[^A-Za-z0-9]/', '_', $dept['department_code']);
            @endphp

            <div class="bg-white rounded-2xl border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden soft-card">

                {{-- Dept accordion header --}}
                <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none hover:bg-slate-50/60 transition"
                     onclick="toggleDept('{{ $safeCode }}')">
                    <div class="flex items-center gap-3">
                        <div class="relative w-10 h-10 shrink-0">
                            <canvas id="ring-{{ $safeCode }}" width="40" height="40"></canvas>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-[8px] font-black {{ $dstyle['text'] }} leading-tight text-center">{{ number_format($dept['performance'],1) }}%</span>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xs font-black text-slate-900">{{ $dept['department_code'] }}</h3>
                            <p class="text-[9px] text-slate-400">{{ $dept['staff_count'] }} staff · {{ $dept['kpi_count'] }} KPIs</p>
                        </div>
                        <div class="hidden md:flex items-center gap-3 ml-1">
                            @foreach(['q1'=>'Q1','q2'=>'Q2','q3'=>'Q3','q4'=>'Q4'] as $qk => $ql)
                                @php $qst = $scoreStyle($dept[$qk]); @endphp
                                <div class="text-center">
                                    <p class="text-[8px] text-slate-400">{{ $ql }}</p>
                                    <p class="text-[9px] font-black {{ $qst['text'] }}">{{ $dept[$qk] > 0 ? number_format($dept[$qk],1).'%' : '—' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[9px] px-2 py-0.5 rounded-lg border font-black {{ $dstyle['badge'] }}">{{ $dstyle['label'] }}</span>
                        @if($dept['risk_count'] > 0)
                            <span class="text-[9px] px-2 py-0.5 rounded-lg bg-red-50 text-red-600 font-black border border-red-100">{{ $dept['risk_count'] }} risk</span>
                        @endif
                        <svg id="chev-{{ $safeCode }}" class="w-4 h-4 text-slate-400 transition-transform duration-300" style="transform:rotate(-90deg)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                {{-- Dept body — starts CLOSED --}}
                <div id="dept-body-{{ $safeCode }}" class="dept-body closed border-t border-[#E5E7EB]">
                    <div class="p-4">

                        {{-- Staff table --}}
                        <div>
                            <div class="overflow-x-auto thin-scroll">
                                <table class="w-full min-w-[540px]">
                                    <thead>
                                        <tr class="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-500 font-black border-b border-[#E5E7EB]">
                                            <th class="px-2 py-1.5 text-left">#</th>
                                            <th class="px-2 py-1.5 text-left">Name</th>
                                            <th class="px-2 py-1.5 text-left">Role</th>
                                            <th class="px-2 py-1.5 text-center">KPIs</th>
                                            <th class="px-2 py-1.5 text-center">Q1</th>
                                            <th class="px-2 py-1.5 text-center">Q2</th>
                                            <th class="px-2 py-1.5 text-center">Q3</th>
                                            <th class="px-2 py-1.5 text-center">Q4</th>
                                            <th class="px-2 py-1.5 text-left">Annual</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @foreach($dept['staff_list'] as $si => $staff)
                                            @php
                                                $sstyle   = $scoreStyle($staff['performance']);
                                                $isMe     = strtolower(trim($staff['name']??'')) === strtolower(trim($currentUserName));
                                                $roleUpper = strtoupper(trim($staff['role'] ?? '-'));
                                                $roleColor = match($roleUpper) {
                                                    'SLT'       => 'bg-purple-100 text-purple-700',
                                                    'VP'        => 'bg-[#F5EAE0] text-[#6B3F2A]',
                                                    'MANAGER'   => 'bg-indigo-100 text-indigo-700',
                                                    'EXECUTIVE' => 'bg-slate-100 text-slate-600',
                                                    default     => 'bg-slate-100 text-slate-500',
                                                };
                                            @endphp
                                            <tr class="{{ $isMe ? 'bg-indigo-50/70' : 'hover:bg-slate-50' }} transition{{ $isSltOffice ? ' cursor-pointer' : '' }}"
                                                @if($isSltOffice) onclick="window.location.href='{{ route('dashboard.staff.kpis', $staff['employee_id']) }}'" @endif>
                                                <td class="px-2 py-2 text-[9px] text-slate-400 font-bold">{{ $si+1 }}</td>
                                                <td class="px-2 py-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="w-5 h-5 rounded-full overflow-hidden bg-slate-200 shrink-0">
                                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($staff['name']??'U') }}&background=0f172a&color=fff&size=20" class="w-full h-full"/>
                                                        </div>
                                                        <span class="text-[10px] font-black text-slate-900">{{ $staff['name'] ?? 'Unknown' }}@if($isMe)<span class="text-indigo-400 font-normal"> (you)</span>@endif</span>
                                                        @if($isSltOffice)
                                                            <svg class="w-3 h-3 text-slate-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-2 py-2">
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black {{ $roleColor }}">{{ $roleUpper !== '-' ? $roleUpper : '—' }}</span>
                                                </td>
                                                <td class="px-2 py-2 text-center text-[9px] font-bold text-slate-600">{{ $staff['kpi_count'] }}</td>
                                                @foreach(['q1','q2','q3','q4'] as $qk)
                                                    @php $qst2 = $scoreStyle($staff[$qk]); @endphp
                                                    <td class="px-2 py-2 text-center">
                                                        <span class="text-[9px] font-black {{ $qst2['text'] }}">{{ $staff[$qk] > 0 ? number_format($staff[$qk],1).'%' : '—' }}</span>
                                                    </td>
                                                @endforeach
                                                <td class="px-2 py-2">
                                                    <div class="flex items-center gap-1">
                                                        <div class="w-10 h-1 bg-slate-100 rounded-full overflow-hidden">
                                                            <div class="h-1 rounded-full {{ $sstyle['bar'] }}" style="width:{{ min($staff['performance'],100) }}%"></div>
                                                        </div>
                                                        <span class="text-[9px] font-black {{ $sstyle['text'] }}">{{ number_format($staff['performance'],1) }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        @endforeach
    </div>

@endif

    </div>{{-- /companySectionWrapper --}}
</div>{{-- /companySection --}}
@endif

{{-- ═══════ KPI TARGET LINKAGES (compact summary — full tool lives on its own page) ═══ --}}
@if($hasAnyLinkage || $canAssignTarget)
@php
    $lnkTotalCount = $myLinkageMap->count() + $outgoingWithCoverage->count();
    $lnkGapCount   = $myLinkageMap->where('met', false)->count() + $outgoingWithCoverage->where('met', false)->count();
@endphp
<a href="{{ route('linkages') }}" class="flex items-center justify-between gap-3 bg-white rounded-2xl px-5 py-4 border border-[#E5E7EB] border-l-[4px] border-l-[#D4AF37] soft-card hover:bg-slate-50/60 transition">
    <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-xl bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-[#B8860B]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17H7A5 5 0 017 7h2M15 7h2a5 5 0 010 10h-2M8 12h8"/>
            </svg>
        </div>
        <div class="text-left min-w-0">
            <p class="text-sm font-black text-slate-800">Target Linkages</p>
            <p class="text-[9px] text-slate-400 mt-0.5">
                @if($hasAnyLinkage)
                    {{ $lnkTotalCount }} cascading target{{ $lnkTotalCount === 1 ? '' : 's' }}@if($lnkGapCount > 0) · {{ $lnkGapCount }} gap{{ $lnkGapCount === 1 ? '' : 's' }}@endif
                @else
                    No cascading targets yet — assign one to your team
                @endif
            </p>
        </div>
    </div>
    <span class="text-[9px] font-black text-[#B8860B] bg-[#D4AF37]/10 px-2.5 py-1 rounded-full shrink-0">View →</span>
</a>
@endif

{{-- ═══════ MY KPIs (compact preview — full grid, search & filter live on KPI List) ═══ --}}
<div>
    <div class="flex items-center justify-between mb-3">
        <div>
            <h2 class="text-sm font-black text-slate-900 inline-block border-b-2 border-[#D4AF37] pb-1">My KPIs <span class="font-normal text-slate-400 text-xs">· {{ $currentFinancialYear }}</span></h2>
            @if($individualKpiCount > 0)
            <p class="text-[9px] text-slate-400 mt-0.5">{{ $individualKpiCount }} KPIs · {{ number_format($individualWeightage,0) }}% total weightage</p>
            @endif
        </div>
        <a href="{{ route('kpi.create') }}" class="px-3 py-1.5 theme-soft-btn rounded-xl text-xs font-black transition">+ Add KPI</a>
    </div>

    @if($individualKpiCount === 0)
        <div class="bg-white rounded-2xl border border-dashed border-[#E5E7EB] p-10 soft-card text-center">
            <p class="text-slate-400 text-sm font-bold">No KPIs yet for {{ $currentFinancialYear }}</p>
            <p class="text-slate-300 text-xs mt-1">Create your first KPI to start tracking performance</p>
            <a href="{{ route('kpi.create') }}" class="inline-block mt-4 px-4 py-2 theme-soft-btn rounded-xl text-xs font-black transition">+ Create KPI</a>
        </div>
    @else
        <a href="{{ route('kpi.index') }}" class="flex flex-wrap items-center gap-2 bg-white rounded-2xl border border-[#E5E7EB] soft-card p-4 hover:bg-slate-50/60 transition">
            @foreach($orderedCategoryGroups as $category => $categoryKpis)
                @php $catStyle = $categoryStyles[$category] ?? $categoryStyles['Default']; @endphp
                <span class="px-2.5 py-1 rounded-lg text-xs font-black shadow-sm {{ $catStyle['bg'] }}">{{ $category ?: 'General' }} · {{ $categoryKpis->count() }}</span>
            @endforeach
            <span class="ml-auto text-xs font-black text-[#B8860B] shrink-0">View All KPIs →</span>
        </a>
    @endif
</div>

</div>{{-- /.p-4 --}}
</main>

{{-- Chart.js loaded here (end of body) so it never blocks first paint --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── DATA FROM PHP ───────────────────────────────────────────────────────────
const deptData = @json($deptChartData);
const companyRankingData = @json($companyDeptRanking ?? []);

// ── SCORE COLOR HELPER ──────────────────────────────────────────────────────
function scoreHex(v) {
    v = parseFloat(v);
    if (v <= 25)  return '#ef4444';
    if (v <= 50)  return '#f97316';
    if (v <= 75)  return '#f59e0b';
    if (v <= 100) return '#10b981';
    return '#059669';
}

const palette = ['#3b82f6','#8b5cf6','#f59e0b','#10b981','#ef4444','#06b6d4','#f97316','#ec4899','#14b8a6','#a855f7'];
const bandColors = ['#059669','#D4AF37','#F97316','#EF4444'];

// Appearance > Chart accent — a single colour for achievement/progress bars,
// kept deliberately separate from the main dashboard Accent (banners,
// buttons) so picking one doesn't force the other, and user-choosable via
// its own swatch in Settings rather than hardcoded.
const THEME_ACCENT2 = '{{ session('theme_accent2') ?: '#6B9080' }}';

// ── CHART: DEPT RANKING (horizontal bar — all company depts) ────────────────
(function() {
    const ctx = document.getElementById('chartDeptRanking');
    if (!ctx) return;
    const src = companyRankingData.length ? companyRankingData : deptData.map(d=>({code:d.code,score:d.annual,staff:d.staff}));
    if (!src.length) return;
    const sorted = [...src].sort((a,b) => b.score - a.score);
    // Axis scales to the actual top score (+15% headroom, rounded to a clean
    // multiple of 10) instead of always to 100 — a fixed 0-100 axis left most
    // of the chart empty whenever every department scored well under that.
    const maxScore = Math.max(0, ...sorted.map(d => d.score));
    const axisMax  = Math.max(10, Math.ceil((maxScore * 1.15) / 10) * 10);
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sorted.map(d => d.code),
            datasets: [{
                label: 'Annual Score (%)',
                data: sorted.map(d => d.score),
                backgroundColor: THEME_ACCENT2 + 'cc',
                borderColor:     THEME_ACCENT2,
                borderWidth: 1.5,
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: c => ` ${c.parsed.x.toFixed(1)}%  ·  ${src.find(d=>d.code===c.label)?.staff || 0} staff`
                    }
                }
            },
            scales: {
                x: { min: 0, max: axisMax, ticks: { callback: v => v+'%', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                y: { ticks: { font: { size: 11, weight: 'bold' } }, grid: { display: false } }
            }
        }
    });
})();

// ── CHART: COMPANY DONUT ────────────────────────────────────────────────────
(function() {
    const ctx = document.getElementById('chartCompanyDonut');
    if (!ctx) return;
    const bands = @json($myDeptBands);
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Excellent ≥90%', 'Good 75–89%', 'Watch 50–74%', 'Critical <50%'],
            datasets: [{ data: bands, backgroundColor: bandColors, borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
            cutout: '70%',
            responsive: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` ${c.label}: ${c.parsed} staff` } } }
        }
    });
})();

// ── CHART: QUARTERLY TREND (grouped bar) ────────────────────────────────────
(function() {
    const ctx = document.getElementById('chartQuarterTrend');
    if (!ctx || !deptData.length) return;
    const datasets = deptData.map((d, i) => ({
        label: d.code,
        data: [d.q1, d.q2, d.q3, d.q4],
        backgroundColor: palette[i % palette.length] + 'bb',
        borderColor:     palette[i % palette.length],
        borderWidth: 1.5,
        borderRadius: 4,
    }));
    new Chart(ctx, {
        type: 'bar',
        data: { labels: ['Q1', 'Q2', 'Q3', 'Q4'], datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 12 } },
                tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${c.parsed.y.toFixed(1)}%` } }
            },
            scales: {
                x: { ticks: { font: { size: 11, weight: 'bold' } }, grid: { display: false } },
                y: { min: 0, max: 100, ticks: { callback: v => v+'%', font: { size: 10 } }, grid: { color: '#f1f5f9' } }
            }
        }
    });
})();

// ── CHARTS: PER-DEPT DONUT + MINI RING ──────────────────────────────────────
deptData.forEach(function(dept) {
    const safe = dept.code.replace(/[^A-Za-z0-9]/g, '_');

    // Mini ring in accordion header (40×40)
    const ringCtx = document.getElementById('ring-' + safe);
    if (ringCtx) {
        new Chart(ringCtx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [dept.annual, Math.max(0, 100 - dept.annual)],
                    backgroundColor: [scoreHex(dept.annual), '#f1f5f9'],
                    borderWidth: 0,
                }]
            },
            options: { cutout: '68%', responsive: false, plugins: { legend: { display:false }, tooltip: { enabled:false } }, events: [] }
        });
    }


});

// ── COMPANY OVERVIEW TOGGLE ─────────────────────────────────────────────────
function toggleCompanySection() {
    const wrapper = document.getElementById('companySectionWrapper');
    const badge   = document.getElementById('companyToggleBadge');
    const chevron = document.getElementById('companyChevron');
    if (!wrapper) return;
    const isHidden = wrapper.style.display === 'none' || wrapper.style.display === '';
    wrapper.style.display = isHidden ? 'block' : 'none';
    if (badge)   badge.textContent = isHidden ? 'Hide' : 'Show';
    if (chevron) chevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
    localStorage.setItem('companyOverviewOpen', isHidden ? 'true' : 'false');
}
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('companyOverviewOpen') === 'true') {
        const wrapper = document.getElementById('companySectionWrapper');
        const badge   = document.getElementById('companyToggleBadge');
        const chevron = document.getElementById('companyChevron');
        if (wrapper) {
            wrapper.style.display = 'block';
            if (badge)   badge.textContent = 'Hide';
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    }
});

// ── ACCORDION TOGGLE ────────────────────────────────────────────────────────
let allOpen = false;

function toggleDept(safe) {
    const body = document.getElementById('dept-body-' + safe);
    const chev = document.getElementById('chev-' + safe);
    if (!body) return;
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open',   !isOpen);
    body.classList.toggle('closed',  isOpen);
    if (chev) chev.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(-90deg)';
}

function toggleAllDepts() {
    const btn = document.getElementById('toggleAllBtn');
    allOpen = !allOpen;
    deptData.forEach(function(dept) {
        const safe = dept.code.replace(/[^A-Za-z0-9]/g, '_');
        const body = document.getElementById('dept-body-' + safe);
        const chev = document.getElementById('chev-' + safe);
        if (body) { body.classList.toggle('open', allOpen); body.classList.toggle('closed', !allOpen); }
        if (chev)  chev.style.transform = allOpen ? '' : 'rotate(-90deg)';
    });
    if (btn) btn.textContent = allOpen ? 'Collapse All' : 'Expand All';
}

// ── MODAL HELPERS ───────────────────────────────────────────────────────────
function openQuarterModal(id)  { const m = document.getElementById('quarter-modal-'+id); if(m){ m.classList.remove('hidden'); m.classList.add('flex'); document.body.classList.add('overflow-hidden'); } }
function closeQuarterModal(id) { const m = document.getElementById('quarter-modal-'+id); if(m){ m.classList.add('hidden');    m.classList.remove('flex'); document.body.classList.remove('overflow-hidden'); } }
function openHistoryModal(id)  { const m = document.getElementById('history-modal-'+id); if(m){ m.classList.remove('hidden'); m.classList.add('flex'); document.body.classList.add('overflow-hidden'); } }
function closeHistoryModal(id) { const m = document.getElementById('history-modal-'+id); if(m){ m.classList.add('hidden');    m.classList.remove('flex'); document.body.classList.remove('overflow-hidden'); } }
</script>

</body>
</html>
