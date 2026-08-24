<!DOCTYPE html>
<html>
<head>
    <title>KPI List</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    <style>
        .glass {
            background: rgba(255,255,255,.9);
            backdrop-filter: blur(14px);
        }
        .card-hover {
            transition: .2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 35px rgba(15,23,42,.08);
        }
        .modal-bg {
            background: rgba(15,23,42,.65);
            backdrop-filter: blur(8px);
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #6B3F2A;
            box-shadow: 0 0 0 3px rgba(107,63,42,.12);
        }
        .weightage-input::-webkit-outer-spin-button,
        .weightage-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .weightage-input {
            -moz-appearance: textfield;
        }
    </style>
</head>

<body class="min-h-screen bg-[#F5F5F3]">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300 bg-[#F5F5F3]">

    @php
        /*
            KPI Score Formula:
            1. KPI Score = Σ per quarter (quarter_actual / base_target × 100).
            2. Weighted KPI Score = KPI Score × weightage / 100.
            3. Individual KPI Score = sum of weighted KPI scores for current user's own KPIs.
        */
        $currentUserId = (string) ($user['id'] ?? '');
        $currentEmployeeId = (string) ($user['id'] ?? '');
        $currentUserName = strtolower(trim($user['short_name'] ?? $user['full_name'] ?? $user['name'] ?? ''));

        $calculateDashboardKpiScore = function ($item) {
            $quarters    = collect($item['quarters'] ?? $item['quarter_plans'] ?? []);
            $baseTarget  = max(0, (float) ($item['base_target'] ?? 0));
            $actualTotal = 0;

            // Only count quarters that have a target configured (target = 0 means not set up)
            foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarterName) {
                $quarter = $quarters->firstWhere('quarter', $quarterName) ?? [];
                if ((float)($quarter['quarter_target'] ?? 0) > 0) {
                    $actualTotal += max(0, (float) ($quarter['quarter_actual'] ?? 0));
                }
            }

            // KPI Score per quarter = Actual / base_target × 100, summed across quarters
            return $baseTarget > 0 ? round(($actualTotal / $baseTarget) * 100, 2) : 0;
        };

        $isCurrentUserKpi = function ($item) use ($currentUserId, $currentEmployeeId, $currentUserName) {
            $kpiEmployeeId = (string) ($item['employee_id'] ?? '');
            $kpiCreatedBy = (string) ($item['created_by'] ?? '');
            $kpiOwnerName = strtolower(trim($item['employee_name'] ?? $item['owner_name'] ?? ''));

            return ($currentUserId !== '' && ($kpiEmployeeId === $currentUserId || $kpiCreatedBy === $currentUserId))
                || ($currentEmployeeId !== '' && $kpiEmployeeId === $currentEmployeeId)
                || ($currentUserName !== '' && $kpiOwnerName === $currentUserName);
        };

        $individualKpiRows = collect($kpis ?? [])->filter(fn($item) => $isCurrentUserKpi($item));
        $myKpis = $individualKpiRows;
        $individualKpiCount = $individualKpiRows->count();
        $individualTotalWeightage = round($individualKpiRows->sum(fn($item) => (float) ($item['weightage'] ?? 0)), 2);
        $individualPerformance = $individualKpiRows->sum(
            function ($item) use ($calculateDashboardKpiScore) {
                $score = $calculateDashboardKpiScore($item);
                $weightage = max(
                    0,
                    (float) ($item['weightage'] ?? 0)
                );
                return ($score * $weightage) / 100;
            }
        );

        $individualPerformanceDisplay =
        round(
            $individualPerformance,
            1
        );

        $individualPerformanceWidth =
        max(
            0,
            min(
                100,
                $individualPerformanceDisplay
            )
        );

        // Quarter scores: weighted per quarter (same formula as annual but per Q)
        $quarterScores = ['Q1' => 0.0, 'Q2' => 0.0, 'Q3' => 0.0, 'Q4' => 0.0];
        foreach ($myKpis as $item) {
            $qItems = collect($item['quarters'] ?? $item['quarter_plans'] ?? []);
            $weight = max(0, (float)($item['weightage'] ?? 0));
            foreach (['Q1','Q2','Q3','Q4'] as $q) {
                $qRow = $qItems->firstWhere('quarter', $q) ?? [];
                $qTarget = (float)($qRow['quarter_target'] ?? 0);
                $qActual = max(0, (float)($qRow['quarter_actual'] ?? 0));
                if ($qTarget > 0) {
                    $quarterScores[$q] += ($qActual / $qTarget * 100) * $weight / 100;
                }
            }
        }
        foreach ($quarterScores as $q => $v) $quarterScores[$q] = round($v, 1);

        if ($individualPerformance < 50) {
            $individualPerformanceBar = 'bg-gradient-to-r from-red-500 to-red-600';
            $individualPerformanceText = 'text-red-700';
            $individualPerformanceLabel = 'Critical';
            $individualPerformanceBadge = 'bg-red-50 text-red-700 border-red-100';
        } elseif ($individualPerformance < 75) {
            $individualPerformanceBar = 'bg-gradient-to-r from-orange-400 to-yellow-400';
            $individualPerformanceText = 'text-yellow-700';
            $individualPerformanceLabel = 'Watch';
            $individualPerformanceBadge = 'bg-yellow-50 text-yellow-700 border-yellow-100';
        } elseif ($individualPerformance < 90) {
            $individualPerformanceBar = 'bg-gradient-to-r from-[#8B5E4A] to-[#6B3F2A]';
            $individualPerformanceText = 'text-indigo-700';
            $individualPerformanceLabel = 'Good';
            $individualPerformanceBadge = 'bg-indigo-50 text-indigo-700 border-indigo-100';
        } else {
            $individualPerformanceBar = 'bg-gradient-to-r from-emerald-400 to-green-500';
            $individualPerformanceText = 'text-emerald-700';
            $individualPerformanceLabel = 'Excellent';
            $individualPerformanceBadge = 'bg-emerald-50 text-emerald-700 border-emerald-100';
        }
    @endphp

{{-- ═══════ HEADER (sticky, with live score ring) ═════════════════════════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[20px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] via-[#3d0511] to-[#7A0019] text-white px-7 py-6 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-row items-center gap-4">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="pointer-events-none absolute -top-14 -right-14 w-64 h-64 rounded-full bg-[#D4AF37]/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 left-1/3 w-64 h-64 rounded-full bg-white/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.05]" style="background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:20px 20px;"></div>

        <!-- LIVE SCORE RING -->
        <div class="relative w-[52px] h-[52px] rounded-full shrink-0 p-[3px]" style="background: conic-gradient(var(--user-theme-accent, #D4AF37) {{ $individualPerformanceWidth * 3.6 }}deg, rgba(255,255,255,.18) 0deg);">
            <div class="w-full h-full rounded-full bg-[#2A0910] flex flex-col items-center justify-center">
                <span class="text-xs font-black leading-none">{{ number_format($individualPerformanceDisplay,0) }}%</span>
                <span class="text-[6px] text-[#D4AF37] font-black uppercase tracking-wider mt-0.5">Score</span>
            </div>
        </div>

        <a href="/dashboard" class="relative text-[11px] text-[#D4AF37] hover:text-white transition shrink-0">← Dashboard</a>

        <span class="relative hidden sm:inline-flex items-center gap-1.5 text-[10px] font-black px-3 py-2 rounded-xl bg-white/10 border border-white/20 text-white/90 shrink-0">
            {{ $individualPerformanceLabel }} Performance
        </span>

        <div class="relative flex-1"></div>

        @if($permission['can_create'])
            <a href="{{ route('kpi.create') }}"
               class="relative bg-[#D4AF37] hover:bg-[#c19c2f] text-[#1a1a1a] px-4 py-2.5 rounded-xl shadow font-black text-xs transition hover:-translate-y-0.5 shrink-0">
                + Create KPI
            </a>
        @endif
    </div>
</div>

<div class="px-4 pb-4 space-y-4">

    <!-- MESSAGES -->
    @if(session('success'))
        <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-2.5 rounded-xl text-xs">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-2.5 rounded-xl text-xs">
            {{ session('error') }}
        </div>
    @endif

    <!-- SUMMARY -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-2.5">

        <!-- INDIVIDUAL PERFORMANCE SPLASH CARD -->
        <div class="card-hover px-3.5 py-2.5 rounded-2xl bg-white border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm md:col-span-2 xl:col-span-1">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-slate-400 text-[9px] font-semibold uppercase">KPI Score</p>
                    <h3 id="individualPerformanceText" class="text-lg font-black {{ $individualPerformanceText }} mt-0.5">{{ number_format($individualPerformanceDisplay, 1) }}%</h3>
                </div>
                <span id="individualPerformanceBadge" class="text-[9px] font-black px-2 py-0.5 rounded-full border {{ $individualPerformanceBadge }}">{{ $individualPerformanceLabel }}</span>
            </div>
            <div class="mt-1.5 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div id="individualPerformanceBar" class="h-1.5 rounded-full transition-all duration-300 {{ $individualPerformanceBar }}" style="width: {{ $individualPerformanceWidth }}%"></div>
            </div>
        </div>

        <!-- FY -->
        <div class="card-hover px-3.5 py-2.5 rounded-2xl bg-white border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm">
            <p class="text-slate-400 text-[9px] font-semibold uppercase">Financial Year</p>
            <h3 class="text-base font-black text-[#7A0019] mt-0.5">{{ $fy }}</h3>
        </div>

        <!-- TOTAL KPI -->
        <div class="card-hover px-3.5 py-2.5 rounded-2xl bg-white border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm">
            <p class="text-slate-400 text-[9px] font-semibold uppercase">Total KPI</p>
            <h3 class="text-base font-black text-slate-900 mt-0.5">{{ $individualKpiCount }}</h3>
        </div>

        <!-- WEIGHTAGE -->
        <div class="card-hover px-3.5 py-2.5 rounded-2xl bg-white border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm">
            <p class="text-slate-400 text-[9px] font-semibold uppercase">Weightage</p>
            <h3 class="text-base font-black mt-0.5 {{ $individualTotalWeightage == 100 ? 'text-emerald-700' : ($individualTotalWeightage > 100 ? 'text-red-700' : 'text-[#B8860B]') }}">{{ number_format($individualTotalWeightage,2) }}%</h3>
        </div>

        <!-- QUARTERLY SCORES -->
        <div class="card-hover px-3.5 py-2.5 rounded-2xl bg-white border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm">
            <p class="text-slate-400 text-[9px] font-semibold uppercase mb-1">Quarter Score</p>
            <div class="space-y-0.5">
                @foreach(['Q1','Q2','Q3','Q4'] as $qi)
                @php $qv = $quarterScores[$qi]; $qtxt = $qv <= 0 ? 'text-slate-400' : ($qv < 50 ? 'text-red-600' : ($qv < 75 ? 'text-amber-600' : ($qv < 90 ? 'text-[#6B3F2A]' : 'text-emerald-600'))); @endphp
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-semibold text-slate-400">{{ $qi }}</span>
                    <span class="text-[11px] font-black {{ $qtxt }}">{{ $qv > 0 ? number_format($qv,1).'%' : '—' }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>


    <!-- FILTER -->
    <div class="bg-white rounded-[20px] shadow-sm border border-[#E5E7EB] border-l-[4px] border-l-[#D4AF37] p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <p class="text-xs font-bold text-slate-500">
                <span id="visibleCount">{{ $individualKpiCount }}</span> of {{ $individualKpiCount }} My KPI shown
            </p>
            <button type="button" id="clearFiltersBtn" class="text-[11px] font-black text-slate-400 hover:text-slate-700 transition hidden">✕ Clear Filters</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Search</label>
                <div class="relative mt-2">
                    <svg class="w-3.5 h-3.5 text-slate-300 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input id="searchInput" type="text" placeholder="Title, staff, category..."
                           class="w-full border border-[#E5E7EB] bg-white rounded-xl pl-9 pr-4 py-2.5 text-xs">
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Category</label>
                <select id="categoryFilter" class="w-full mt-2 border border-[#E5E7EB] bg-white rounded-xl px-4 py-2.5 text-xs">
                    <option value="">All</option>
                    <option value="Financial">Financial</option>
                    <option value="Growth & Customer">Growth & Customer</option>
                    <option value="Initiatives">Initiatives</option>
                    <option value="People">People</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-500 uppercase">Status</label>
                <select id="statusFilter" class="w-full mt-2 border border-[#E5E7EB] bg-white rounded-xl px-4 py-2.5 text-xs">
                    <option value="">All</option>
                    <option value="not_started">Not Started</option>
                    <option value="on_track">On Track</option>
                    <option value="at_risk">At Risk</option>
                    <option value="in_trouble">In Trouble</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>

        {{-- COLOUR LEGEND --}}
        <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-4">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest shrink-0">Colour Guide</p>
            <div class="flex flex-wrap gap-1.5">
                <span class="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[9px] font-black"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>Financial</span>
                <span class="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800 text-[9px] font-black"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>Growth</span>
                <span class="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-[9px] font-black"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>Initiatives</span>
                <span class="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-pink-50 border border-pink-200 text-pink-800 text-[9px] font-black"><span class="w-1.5 h-1.5 rounded-full bg-pink-500 shrink-0"></span>People</span>
            </div>
            <div class="w-px bg-slate-100 self-stretch hidden md:block"></div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded bg-yellow-100 border border-yellow-300 shrink-0"></span>
                <p class="text-[9px] text-slate-500 font-bold">📩 Assigned to Me</p>
            </div>
        </div>
    </div>

    @php
        /* ----------------------------------------------------------------
         * UNIFIED PER-CATEGORY VIEW
         * Own KPIs  → category-native colour (emerald/indigo/amber/pink)
         * Assigned  → complementary colour   (yellow/sky/rose/violet)
         * ---------------------------------------------------------------- */
        $categoryOrder = ['Financial','Growth & Customer','Initiatives','People'];

        $groupedOwn      = $individualKpiRows->groupBy('category');
        $groupedAssigned = collect($indexAssignedKpis ?? [])->groupBy('category');

        // Union of all present categories, in defined order first
        $allCategories = collect($categoryOrder)
            ->filter(fn($c) => $groupedOwn->has($c) || $groupedAssigned->has($c));
        collect($groupedOwn->keys())->merge($groupedAssigned->keys())
            ->unique()
            ->reject(fn($c) => in_array($c, $categoryOrder))
            ->each(fn($c) => $allCategories->push($c));

        // Own KPI theme (category-native colours)
        $ownThemes = [
            'Financial'         => ['headerBg'=>'from-emerald-800 to-emerald-600','icon'=>'💰','border'=>'border-l-emerald-500','cardBorder'=>'border-emerald-100','catPill'=>'bg-emerald-700 text-white','subPill'=>'bg-emerald-100 text-emerald-700','infoBg'=>'bg-emerald-50 border-emerald-100','infoText'=>'text-emerald-700','labelText'=>'text-emerald-800','labelDot'=>'bg-emerald-500','btnGrad'=>'from-emerald-600 to-emerald-700','avatarBg'=>'bg-emerald-600','personPill'=>'bg-emerald-100 text-emerald-900'],
            'Growth & Customer' => ['headerBg'=>'from-indigo-800 to-indigo-600','icon'=>'📈','border'=>'border-l-indigo-500','cardBorder'=>'border-indigo-100','catPill'=>'bg-indigo-700 text-white','subPill'=>'bg-indigo-100 text-indigo-700','infoBg'=>'bg-indigo-50 border-indigo-100','infoText'=>'text-indigo-700','labelText'=>'text-indigo-800','labelDot'=>'bg-indigo-500','btnGrad'=>'from-indigo-600 to-indigo-700','avatarBg'=>'bg-indigo-600','personPill'=>'bg-indigo-100 text-indigo-900'],
            'Initiatives'       => ['headerBg'=>'from-amber-700 to-amber-500','icon'=>'🚀','border'=>'border-l-amber-500','cardBorder'=>'border-amber-100','catPill'=>'bg-amber-600 text-white','subPill'=>'bg-amber-100 text-amber-700','infoBg'=>'bg-amber-50 border-amber-100','infoText'=>'text-amber-700','labelText'=>'text-amber-800','labelDot'=>'bg-amber-500','btnGrad'=>'from-amber-500 to-amber-700','avatarBg'=>'bg-amber-500','personPill'=>'bg-amber-100 text-amber-900'],
            'People'            => ['headerBg'=>'from-pink-800 to-pink-600','icon'=>'👥','border'=>'border-l-pink-500','cardBorder'=>'border-pink-100','catPill'=>'bg-pink-700 text-white','subPill'=>'bg-pink-100 text-pink-700','infoBg'=>'bg-pink-50 border-pink-100','infoText'=>'text-pink-700','labelText'=>'text-pink-800','labelDot'=>'bg-pink-500','btnGrad'=>'from-pink-600 to-pink-700','avatarBg'=>'bg-pink-600','personPill'=>'bg-pink-100 text-pink-900'],
        ];
        $ownThemeDefault = ['headerBg'=>'from-slate-700 to-slate-600','icon'=>'📌','border'=>'border-l-slate-400','cardBorder'=>'border-slate-100','catPill'=>'bg-slate-600 text-white','subPill'=>'bg-slate-100 text-slate-600','infoBg'=>'bg-slate-50 border-slate-100','infoText'=>'text-slate-600','labelText'=>'text-slate-700','labelDot'=>'bg-slate-400','btnGrad'=>'from-slate-600 to-slate-700','avatarBg'=>'bg-slate-500','personPill'=>'bg-slate-100 text-slate-800'];

        // Assigned KPI theme (complementary colours — clearly different)
        $assignedThemes = [
            'Financial'         => ['border'=>'border-l-yellow-400','cardBg'=>'bg-yellow-50/60','cardBorder'=>'border-yellow-100','catPill'=>'bg-yellow-500 text-white','subPill'=>'bg-yellow-100 text-yellow-800','infoBg'=>'bg-yellow-50 border-yellow-100','infoText'=>'text-yellow-700','labelText'=>'text-yellow-800','labelDot'=>'bg-yellow-400','avatarBg'=>'bg-yellow-500','personPill'=>'bg-yellow-100 text-yellow-900','qDefault'=>'bg-yellow-100 text-yellow-700'],
            'Growth & Customer' => ['border'=>'border-l-sky-400','cardBg'=>'bg-sky-50/60','cardBorder'=>'border-sky-100','catPill'=>'bg-sky-600 text-white','subPill'=>'bg-sky-100 text-sky-800','infoBg'=>'bg-sky-50 border-sky-100','infoText'=>'text-sky-700','labelText'=>'text-sky-800','labelDot'=>'bg-sky-400','avatarBg'=>'bg-sky-500','personPill'=>'bg-sky-100 text-sky-900','qDefault'=>'bg-sky-100 text-sky-700'],
            'Initiatives'       => ['border'=>'border-l-rose-400','cardBg'=>'bg-rose-50/60','cardBorder'=>'border-rose-100','catPill'=>'bg-rose-600 text-white','subPill'=>'bg-rose-100 text-rose-800','infoBg'=>'bg-rose-50 border-rose-100','infoText'=>'text-rose-700','labelText'=>'text-rose-800','labelDot'=>'bg-rose-400','avatarBg'=>'bg-rose-500','personPill'=>'bg-rose-100 text-rose-900','qDefault'=>'bg-rose-100 text-rose-700'],
            'People'            => ['border'=>'border-l-violet-400','cardBg'=>'bg-violet-50/60','cardBorder'=>'border-violet-100','catPill'=>'bg-violet-600 text-white','subPill'=>'bg-violet-100 text-violet-800','infoBg'=>'bg-violet-50 border-violet-100','infoText'=>'text-violet-700','labelText'=>'text-violet-800','labelDot'=>'bg-violet-400','avatarBg'=>'bg-violet-500','personPill'=>'bg-violet-100 text-violet-900','qDefault'=>'bg-violet-100 text-violet-700'],
        ];
        $assignedThemeDefault = ['border'=>'border-l-slate-300','cardBg'=>'bg-slate-50/60','cardBorder'=>'border-slate-100','catPill'=>'bg-slate-600 text-white','subPill'=>'bg-slate-100 text-slate-700','infoBg'=>'bg-slate-50 border-slate-100','infoText'=>'text-slate-600','labelText'=>'text-slate-700','labelDot'=>'bg-slate-400','avatarBg'=>'bg-slate-400','personPill'=>'bg-slate-100 text-slate-800','qDefault'=>'bg-slate-100 text-slate-600'];
    @endphp

    {{-- ── LINKAGE WARNINGS ─────────────────────────────────────────────── --}}
    @php
        $lnkMap       = $linkageMap ?? [];
        $lnkUnmet     = array_filter($lnkMap, fn($l) => !$l['met']);
        $lnkMet       = array_filter($lnkMap, fn($l) => $l['met']);
        $fmtLnkVal    = function($v, $u) {
            $n = (float)$v;
            if ($u === 'currency')   return 'RM ' . number_format($n, 0);
            if ($u === 'percentage') return number_format($n, 1) . '%';
            return number_format($n, 0);
        };
    @endphp
    @if(!empty($lnkMap))
    <div class="rounded-2xl border overflow-hidden {{ count($lnkUnmet) > 0 ? 'border-amber-200' : 'border-emerald-200' }}">
        <div class="flex items-center justify-between px-4 py-2.5 {{ count($lnkUnmet) > 0 ? 'bg-amber-50' : 'bg-emerald-50' }}">
            <div class="flex items-center gap-2">
                <span class="text-sm">{{ count($lnkUnmet) > 0 ? '⚠️' : '✅' }}</span>
                <p class="text-xs font-black {{ count($lnkUnmet) > 0 ? 'text-amber-800' : 'text-emerald-800' }}">
                    Linked Targets &nbsp;·&nbsp;
                    {{ count($lnkMet) }}/{{ count($lnkMap) }} met
                    @if(count($lnkUnmet) > 0)
                    &nbsp;·&nbsp; <span class="text-amber-600">{{ count($lnkUnmet) }} need attention</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-[10px] font-black text-slate-400 hover:text-slate-700 transition">Manage on Dashboard →</a>
        </div>
        <div class="divide-y {{ count($lnkUnmet) > 0 ? 'divide-amber-100' : 'divide-emerald-100' }}">
            @foreach($lnkMap as $sub => $lnk)
            <div class="flex items-center gap-3 px-4 py-2 {{ $lnk['met'] ? 'bg-white' : 'bg-amber-50/60' }}">
                <div class="w-1 h-6 rounded-full shrink-0 {{ $lnk['met'] ? 'bg-emerald-400' : 'bg-amber-400' }}"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-black text-slate-800">{{ $sub }}</span>
                        <span class="text-[9px] text-slate-400">{{ $lnk['category'] }} · from {{ $lnk['assigner_name'] }}</span>
                        @if(!$lnk['met'])
                        <span class="text-[9px] font-black bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200">Gap {{ $fmtLnkVal($lnk['gap'], $lnk['unit']) }}</span>
                        @else
                        <span class="text-[9px] font-black bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-200">Met ✓</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-1.5 rounded-full {{ $lnk['met'] ? 'bg-emerald-400' : 'bg-amber-400' }}" style="width:{{ $lnk['pct'] }}%"></div>
                    </div>
                    <span class="text-[9px] font-black {{ $lnk['met'] ? 'text-emerald-700' : 'text-amber-700' }} w-7 text-right">{{ $lnk['pct'] }}%</span>
                    <span class="text-[9px] text-slate-400 w-28 text-right">{{ $fmtLnkVal($lnk['covered'], $lnk['unit']) }} / {{ $fmtLnkVal($lnk['target'], $lnk['unit']) }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="space-y-4">

    @if($allCategories->isEmpty())
        <div class="bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center">
            <h3 class="text-xl font-black text-slate-900">No KPI Created Yet</h3>
            <p class="text-sm text-slate-500 mt-2">Start creating KPI for your yearly execution tracking.</p>
            @if($permission['can_create'])
            <a href="{{ route('kpi.create') }}"
               class="theme-header-accent-btn inline-flex items-center gap-1.5 mt-4 bg-[#D4AF37] hover:bg-[#c19c2f] text-[#1a1a1a] px-5 py-2.5 rounded-xl shadow font-black text-xs transition hover:-translate-y-0.5">
                + Create KPI
            </a>
            @endif
        </div>
    @endif

    @foreach($allCategories as $category)
    @php
        $ownCatKpis      = $groupedOwn->get($category, collect())->values()->all();
        $assignedCatKpis = $groupedAssigned->get($category, collect([]))->values()->all();
        $ot = $ownThemes[$category] ?? $ownThemeDefault;
        $at = $assignedThemes[$category] ?? $assignedThemeDefault;
        $ownCount      = count($ownCatKpis);
        $assignedCount = count($assignedCatKpis);
        $categoryKpis  = $ownCatKpis; // alias for existing code below
    @endphp

    <div class="category-group">

        {{-- UNIFIED CATEGORY HEADER --}}
        <div class="flex items-center justify-between gap-3 bg-white rounded-xl border border-slate-200 border-l-4 {{ $ot['border'] }} px-4 py-2.5 mb-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-7 h-7 rounded-lg {{ $ot['infoBg'] }} border flex items-center justify-center text-sm shrink-0">{{ $ot['icon'] }}</div>
                <h2 class="text-xs font-black tracking-wide {{ $ot['labelText'] }} uppercase truncate">{{ strtoupper($category) }}</h2>
                <span class="text-[10px] text-slate-400 font-semibold shrink-0">{{ $ownCount + $assignedCount }} KPI</span>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                @if($ownCount > 0)
                <span class="px-2 py-0.5 rounded-full {{ $ot['catPill'] }} font-black text-[9px]">✍️ {{ $ownCount }} My KPI</span>
                @endif
                @if($assignedCount > 0)
                <span class="px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 font-black text-[9px]">📩 {{ $assignedCount }} Assigned</span>
                @endif
            </div>
        </div>

        {{-- ── ASSIGNED KPI SUB-SECTION ── --}}
        @if($assignedCount > 0)
        <div class="bg-yellow-50 border border-yellow-200 rounded-[24px] p-4 mb-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-2.5 h-2.5 rounded-full {{ $ot['labelDot'] }} shrink-0"></span>
            <p class="text-xs font-black {{ $ot['labelText'] }} uppercase tracking-wider">📩 Assigned to Me &nbsp;·&nbsp; {{ $assignedCount }}</p>
            <div class="h-px flex-1 bg-yellow-200"></div>
            <span class="text-[10px] {{ $ot['infoText'] }} font-bold bg-white px-2 py-0.5 rounded-lg border border-yellow-200">👁 View Only</span>
        </div>

        @foreach($assignedCatKpis as $akpi)
        @php
            $aqtrs      = collect($akpi['quarters'] ?? []);
            $aBase      = (float)($akpi['base_target'] ?? 0);
            $aWeightage = (float)($akpi['weightage'] ?? 0);

            // Only count quarters that have a target set
            $aActual = 0;
            foreach (['Q1','Q2','Q3','Q4'] as $_aq) {
                $_aqd = $aqtrs->firstWhere('quarter', $_aq) ?? [];
                if ((float)($_aqd['quarter_target'] ?? 0) > 0) {
                    $aActual += max(0, (float)($_aqd['quarter_actual'] ?? 0));
                }
            }

            // KPI Score = (Σ quarter_actual / base_target × 100) × weightage / 100
            $aRawScore = $aBase > 0 ? round(($aActual / $aBase) * 100, 1) : 0;
            $aAchv     = round(($aRawScore * $aWeightage) / 100, 2);

            $aProgressBar = match(true) {
                $aAchv >= 90 => 'from-emerald-400 to-green-500',
                $aAchv >= 75 => 'from-yellow-400 to-yellow-500',
                $aAchv >= 50 => 'from-orange-400 to-orange-500',
                default      => 'from-red-400 to-red-500',
            };
            $aProgressText = match(true) {
                $aAchv >= 90 => 'text-emerald-600',
                $aAchv >= 75 => 'text-yellow-600',
                $aAchv >= 50 => 'text-orange-600',
                default      => 'text-red-600',
            };
            $aQColors = [];
            foreach (['Q1','Q2','Q3','Q4'] as $aq) {
                $aqd = $aqtrs->firstWhere('quarter', $aq) ?? [];
                $aqt = (float)($aqd['quarter_target'] ?? 0);
                $aqa = (float)($aqd['quarter_actual'] ?? 0);
                $aqs = $aqt > 0 ? ($aqa / $aqt) * 100 : 0;
                $aQColors[$aq] = match(true) {
                    $aqs >= 90 => 'bg-emerald-500 text-white',
                    $aqs >= 75 => 'bg-yellow-500 text-white',
                    $aqs >= 50 => 'bg-orange-500 text-white',
                    $aqs > 0   => 'bg-red-500 text-white',
                    default    => 'bg-slate-200 text-slate-500',
                };
            }
            $assignerName = $akpi['employee_name'] ?? '-';
        @endphp

        <div
            onclick="openAssignedKpiDetail(this)"
            class="assigned-kpi-card cursor-pointer bg-white border-l-4 {{ $ot['border'] }} border border-yellow-200 rounded-2xl p-4 shadow-sm hover:shadow-xl hover:scale-[1.005] hover:-translate-y-0.5 transition-all duration-200 mb-2"
            data-kpi='@json($akpi)'
        >
            <!-- ROW 1: Tags + KPI Score -->
            <div class="flex items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="px-2.5 py-0.5 rounded-full {{ $ot['catPill'] }} text-[10px] font-black">{{ $akpi['category'] ?? '-' }}</span>
                    <span class="px-2.5 py-0.5 rounded-full {{ $ot['subPill'] }} text-[10px] font-black">{{ $akpi['sub_category'] ?? '-' }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-white border border-yellow-200 text-yellow-700 text-[10px] font-black">👁 View Only</span>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[9px] uppercase text-slate-400 font-black leading-none">KPI Score</p>
                    <span class="text-xl font-black {{ $aProgressText }}">{{ number_format($aAchv, 1) }}%</span>
                </div>
            </div>

            <!-- ROW 2: Progress bar -->
            <div class="my-2">
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-1.5 rounded-full bg-gradient-to-r {{ $aProgressBar }}" style="width: {{ max(3, min(100, $aAchv)) }}%"></div>
                </div>
            </div>

            <!-- ROW 3: Title + description + assigner -->
            <h3 class="text-base font-black text-slate-900 leading-snug">{{ $akpi['kpi_title'] ?? 'Untitled' }}</h3>
            @if(!empty($akpi['kpi_description']))
            <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed line-clamp-1">{{ $akpi['kpi_description'] }}</p>
            @endif
            <p class="text-[10px] text-slate-400 mt-1">From: <span class="font-bold text-slate-600">{{ $assignerName }}</span>@if(!empty($akpi['employee_role'])) &middot; {{ $akpi['employee_role'] }}@endif</p>

            <!-- ROW 4: Quarter badges + meta -->
            <div class="flex items-center gap-3 mt-2.5">
                <div class="flex gap-1.5">
                    @foreach(['Q1','Q2','Q3','Q4'] as $aq)
                    <span class="w-7 h-7 rounded-lg text-[9px] font-black flex items-center justify-center {{ $aQColors[$aq] }}">{{ $aq }}</span>
                    @endforeach
                </div>
                <div class="flex-1 grid grid-cols-3 gap-2">
                    <div class="rounded-xl {{ $ot['infoBg'] }} border px-3 py-1.5">
                        <p class="text-[9px] uppercase {{ $ot['infoText'] }} font-black">Weightage</p>
                        <p class="text-xs font-black text-slate-900">{{ number_format($akpi['weightage'] ?? 0, 0) }}%</p>
                    </div>
                    <div class="rounded-xl {{ $ot['infoBg'] }} border px-3 py-1.5">
                        <p class="text-[9px] uppercase {{ $ot['infoText'] }} font-black">Target</p>
                        <p class="text-xs font-black text-slate-900">{{ number_format($aBase, 0) }}</p>
                    </div>
                    <div class="rounded-xl {{ $ot['infoBg'] }} border px-3 py-1.5">
                        <p class="text-[9px] uppercase {{ $ot['infoText'] }} font-black">Actual</p>
                        <p class="text-xs font-black text-slate-900">{{ number_format($aActual, 0) }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
        </div>{{-- closes yellow bg wrapper --}}
        @endif

        {{-- ── OWN KPI SUB-SECTION ── --}}
        @if($ownCount > 0)
        <div class="flex items-center gap-2 mb-3">
            <span class="w-2.5 h-2.5 rounded-full {{ $ot['labelDot'] }} shrink-0"></span>
            <p class="text-xs font-black {{ $ot['labelText'] }} uppercase tracking-wider">✍️ My KPIs &nbsp;·&nbsp; {{ $ownCount }}</p>
            <div class="h-px flex-1 bg-slate-100"></div>
        </div>

        @foreach($ownCatKpis as $kpi)

        @php
            $quarters     = collect($kpi['quarters'] ?? []);
            $baseTotal    = (float)($kpi['base_target'] ?? 0);
            $kpiWeightage = (float)($kpi['weightage'] ?? 0);

            // Only count quarters that have a target set (target = 0 means not yet configured)
            $latestActual = 0;
            foreach (['Q1','Q2','Q3','Q4'] as $_q) {
                $_qd = $quarters->firstWhere('quarter', $_q) ?? [];
                if ((float)($_qd['quarter_target'] ?? 0) > 0) {
                    $latestActual += max(0, (float)($_qd['quarter_actual'] ?? 0));
                }
            }

            // KPI Score per quarter = Actual / base_target × 100  →  sum across quarters
            $rawScore   = $baseTotal > 0 ? round(($latestActual / $baseTotal) * 100, 2) : 0;
            // Performance Score = (KPI Score × weightage) / 100
            $achievement = round(($rawScore * $kpiWeightage) / 100, 2);

            $progressText = match(true) {
                $achievement >= 90 => 'text-emerald-600',
                $achievement >= 75 => 'text-indigo-600',
                $achievement >= 50 => 'text-yellow-600',
                default            => 'text-red-600',
            };
            [$achievementBadge, $achievementLabel] = match(true) {
                $achievement >= 90 => ['bg-emerald-100 text-emerald-700', 'Excellent'],
                $achievement >= 75 => ['bg-indigo-100 text-indigo-700',   'Good'],
                $achievement >= 50 => ['bg-yellow-100 text-yellow-700',   'Watch'],
                default            => ['bg-red-100 text-red-700',         'Critical'],
            };
            $progressBar = match(true) {
                $achievement >= 90 => 'from-emerald-500 to-green-600',
                $achievement >= 75 => 'from-yellow-400 to-yellow-600',
                $achievement >= 50 => 'from-orange-400 to-orange-600',
                default            => 'from-red-500 to-red-700',
            };
            $quarterColors = [];
            foreach (['Q1','Q2','Q3','Q4'] as $q) {
                $qd = $quarters->firstWhere('quarter', $q) ?? [];
                $qt = (float)($qd['quarter_target'] ?? 0);
                $qa = (float)($qd['quarter_actual'] ?? 0);
                $qs = $qt > 0 ? ($qa / $qt) * 100 : 0;
                $quarterColors[$q] = match(true) {
                    $qs >= 90 => 'bg-green-500 text-white',
                    $qs >= 75 => 'bg-yellow-500 text-white',
                    $qs >= 50 => 'bg-orange-500 text-white',
                    $qs > 0   => 'bg-red-500 text-white',
                    default   => 'bg-slate-200 text-slate-500',
                };
            }
            $actualDisplay = match($kpi['unit'] ?? '') {
                'currency'   => number_format($latestActual, 2),
                'percentage' => number_format($latestActual, 2) . ' %',
                default      => number_format($latestActual, 0),
            };
            $targetDisplay = match($kpi['unit'] ?? '') {
                'currency'   => number_format($baseTotal, 2),
                'percentage' => number_format($baseTotal, 2) . ' %',
                default      => number_format($baseTotal, 0),
            };
        @endphp

        <div
            onclick="openKpiDetail(this)"
            class="kpi-card cursor-pointer bg-white border-l-4 {{ $ot['border'] }} border {{ $ot['cardBorder'] }} rounded-2xl p-4 shadow-sm hover:shadow-xl hover:scale-[1.005] hover:-translate-y-0.5 transition-all duration-200 mb-2"
            data-search="{{ strtolower(($kpi['kpi_title'] ?? '') . ' ' . ($kpi['category'] ?? '')) }}"
            data-category="{{ $kpi['category'] ?? '' }}"
            data-status="{{ $kpi['status'] ?? '' }}"
            data-kpi='@json($kpi)'
        >
            <!-- ROW 1: Tags + KPI Score -->
            <div class="flex items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="px-2.5 py-0.5 rounded-full {{ $ot['catPill'] }} text-[10px] font-black">{{ $kpi['category'] ?? 'General' }}</span>
                    <span class="px-2.5 py-0.5 rounded-full {{ $ot['subPill'] }} text-[10px] font-black">{{ $kpi['sub_category'] ?? 'Sub Category' }}</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-white border {{ $ot['cardBorder'] }} {{ $ot['infoText'] }} text-[10px] font-black">⚖️ {{ number_format($kpi['weightage'] ?? 0, 0) }}%</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black">{{ $kpi['financial_year'] ?? '-' }}</span>
                    <span class="px-2 py-0.5 rounded-full bg-white border border-slate-200 text-slate-600 text-[10px] font-black">✍️ My KPI</span>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[9px] uppercase text-slate-400 font-black leading-none">KPI Score</p>
                    <span class="text-xl font-black {{ $progressText }}">{{ number_format($achievement, 1) }}%</span>
                    <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black {{ $achievementBadge }}">{{ $achievementLabel }}</span>
                </div>
            </div>

            <!-- ROW 2: Progress bar (between score and title) -->
            <div class="my-2">
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-1.5 rounded-full bg-gradient-to-r {{ $progressBar }}" style="width: {{ max(3, min(100, $achievement)) }}%"></div>
                </div>
            </div>

            <!-- ROW 3: Title + description -->
            <h3 class="text-base font-black text-slate-900 leading-snug">{{ $kpi['kpi_title'] ?? 'Untitled KPI' }}</h3>
            @if(!empty($kpi['kpi_description']))
            <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed line-clamp-1">{{ $kpi['kpi_description'] }}</p>
            @endif

            <!-- ROW 4: Quarter badges + meta -->
            <div class="flex items-center gap-3 mt-2.5">
                <div class="flex gap-1.5">
                    @foreach(['Q1','Q2','Q3','Q4'] as $q)
                    <span class="w-7 h-7 rounded-lg text-[9px] font-black flex items-center justify-center {{ $quarterColors[$q] }}">{{ $q }}</span>
                    @endforeach
                </div>
                <div class="flex-1 grid grid-cols-4 gap-2">
                    <div class="rounded-xl {{ $ot['infoBg'] }} border px-3 py-1.5">
                        <p class="text-[9px] uppercase {{ $ot['infoText'] }} font-black">Target</p>
                        <p class="text-xs font-black text-slate-900">{{ $targetDisplay }}</p>
                    </div>
                    <div class="rounded-xl {{ $ot['infoBg'] }} border px-3 py-1.5">
                        <p class="text-[9px] uppercase {{ $ot['infoText'] }} font-black">Actual</p>
                        <p class="text-xs font-black text-slate-900">{{ $actualDisplay }}</p>
                    </div>
                    <button
                        onclick="event.stopPropagation(); openEditKpiModal(@json($kpi));"
                        class="px-3 py-1.5 rounded-xl bg-gradient-to-r {{ $ot['btnGrad'] }} hover:opacity-90 text-white text-[10px] font-black"
                    >Edit KPI</button>
                    <button
                        onclick="event.stopPropagation(); openDeleteKpiModal('{{ $kpi['id'] }}', '{{ addslashes($kpi['kpi_title']) }}');"
                        class="px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-[10px] font-black"
                    >Delete KPI</button>
                </div>
            </div>
        </div>

        @endforeach
        @endif

    </div>
    @endforeach

    <!-- NO RESULT -->
    <div id="noFilterResult"
        class="hidden glass rounded-[18px] border border-slate-200 p-10 text-center mt-4">
        <h3 class="text-xl font-black text-slate-900">No KPI Found</h3>
        <p class="text-xs text-slate-500 mt-2">Try changing search, category or status filter.</p>
    </div>

    </div>

</main>

<script>
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const visibleCount = document.getElementById('visibleCount');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const cards = document.querySelectorAll('.kpi-card');
    const noFilterResult = document.getElementById('noFilterResult');

    function filterRows() {
        const searchValue = searchInput.value.toLowerCase().trim();
        const categoryValue = categoryFilter.value;
        const statusValue = statusFilter.value;

        clearFiltersBtn.classList.toggle('hidden', !searchValue && !categoryValue && !statusValue);

        let count = 0;

        cards.forEach(function(card) {
            const categoryData = card.dataset.category || '';
            const statusData = card.dataset.status || '';
            const searchData = card.dataset.search || '';
            const matchesSearch = searchData.includes(searchValue);
            const matchesCategory = !categoryValue || categoryData === categoryValue;
            const matchesStatus = !statusValue || statusData === statusValue;

            if (matchesSearch && matchesCategory && matchesStatus) {
                card.classList.remove('hidden');
                count++;
            } else {
                card.classList.add('hidden');
            }
        });

        document
            .querySelectorAll('.category-group')
            .forEach(group => {

                const visibleCards =
                    group.querySelectorAll(
                        '.kpi-card:not(.hidden)'
                    );

                if(visibleCards.length === 0){

                    group.classList.add('hidden');

                }else{

                    group.classList.remove('hidden');
                }

            });

        if (cards.length > 0 && count === 0) {
            noFilterResult.classList.remove('hidden');
        } else {
            noFilterResult.classList.add('hidden');
        }

        visibleCount.textContent = count;
    }

    searchInput.addEventListener('input', filterRows);
    categoryFilter.addEventListener('change', filterRows);
    statusFilter.addEventListener('change', filterRows);

    clearFiltersBtn.addEventListener('click', function() {
        searchInput.value = '';
        categoryFilter.value = '';
        statusFilter.value = '';
        filterRows();
    });

    // Prefill from the top bar's global search box (?q=...) when arriving
    // here from another page via Enter, so that search feels continuous
    // instead of landing on an unfiltered list.
    (function prefillSearchFromQuery() {
        const q = new URLSearchParams(window.location.search).get('q');
        if (q) {
            searchInput.value = q;
            filterRows();
        }
    })();

    function closeQuarterModal(id) {

        const modal = document.getElementById('quarterUpdateModal-' + id);

        if (!modal) return;

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.body.classList.remove('overflow-hidden');
    }

    function openEditApprovalModal(id) {

        const modal = document.getElementById('editApprovalModal-' + id);

        if (!modal) return;

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.classList.add('overflow-hidden');
    }

    let activeKpi = null;

function openKpiDetail(card){

    const modal = document.getElementById('kpiDetailModal');
    const content = document.getElementById('kpiDetailContent');

    if(!modal || !content || !card) return;

    activeKpi = JSON.parse(
        card.dataset.kpi || '{}'
    );

    renderKpiDetail('Q1');

    modal.classList.remove('hidden');

    document.body.classList.add('overflow-hidden');
}

function renderKpiDetail(activeQuarter){

    const content = document.getElementById(
        'kpiDetailContent'
    );

    const kpi = activeKpi;

    const timeline =
    kpi.approval_timeline || [];

    let timelineHtml = '';

    timeline.forEach(item => {

        let dotColor = 'bg-yellow-500';

        if(
            (item.status || '').toLowerCase()
            === 'approved'
        ){
            dotColor = 'bg-emerald-500';
        }

        if(
            (item.status || '').toLowerCase()
            === 'rejected'
        ){
            dotColor = 'bg-red-500';
        }

        timelineHtml += `

            <div class="flex gap-4">

                <div
                    class="w-3 h-3 rounded-full mt-2 ${dotColor}">
                </div>

                <div class="flex-1">

                   <div class="font-black">
                        ${(item.status || '-')
                            .charAt(0)
                            .toUpperCase()
                            +
                            (item.status || '-')
                            .slice(1)}
                    </div>

                    <div class="text-xs text-slate-500">

                        ${item.type || '-'}

                    </div>

                    <div class="text-xs text-slate-500">

                        Requested By:
                        ${item.by || '-'}

                    </div>

                    <div class="text-xs text-slate-500">

                        Approver:
                        ${item.approver || '-'}

                    </div>

                    <div class="text-xs text-slate-400">

                        ${item.date || '-'}

                    </div>

                </div>

            </div>

        `;
    });

    if(!kpi || !content) return;

    const quarters = kpi.quarters || [];

    const quarter =
        quarters.find(
            q => q.quarter === activeQuarter
        ) || {};

    const today =
        new Date();

    const startDate =
        new Date(
            quarter.start_date
        );

    const endDate =
        new Date(
            quarter.end_date
        );

    const beforeQuarter =
        today < startDate;

    const afterQuarter =
        today > endDate;

    const completed =
        quarter.status === 'completed';

    const reasonRequired =
        afterQuarter || completed;

    const target = parseFloat(
        quarter.quarter_target || 0
    );

    const actual = parseFloat(
        quarter.quarter_actual || 0
    );

    const score =
        target > 0
        ? Number(((actual / target) * 100).toFixed(1))
        : 0;

    let scoreColor = 'text-red-600';
    let progressBar = 'from-red-500 to-red-700';

    if(score >= 90){

        scoreColor = 'text-emerald-600';
        progressBar = 'from-emerald-500 to-green-600';

    }
    else if(score >= 75){

        scoreColor = 'text-yellow-600';
        progressBar = 'from-yellow-400 to-yellow-600';

    }
    else if(score >= 50){

        scoreColor = 'text-orange-600';
        progressBar = 'from-orange-400 to-orange-600';

    }

    let categoryClass = 'bg-slate-700 text-white';
    let categoryLight = 'bg-slate-100 text-slate-700';
    let categoryGradient = 'from-slate-500 to-slate-700';
    let categoryButton = 'bg-slate-700 hover:bg-slate-800';
    let ownerCardGradient = 'from-slate-700 via-slate-800 to-slate-900';

    if(kpi.category === 'Financial'){
        categoryClass = 'bg-emerald-700 text-white';
        categoryLight = 'bg-emerald-100 text-emerald-700';
        categoryGradient = 'from-emerald-500 to-emerald-700';
        categoryButton = 'bg-emerald-600 hover:bg-emerald-700';
        ownerCardGradient = 'from-emerald-700 via-emerald-800 to-green-900';
    }
    else if(kpi.category === 'Growth & Customer'){
        categoryClass = 'bg-indigo-700 text-white';
        categoryLight = 'bg-indigo-100 text-indigo-700';
        categoryGradient = 'from-indigo-500 to-indigo-700';
        categoryButton = 'bg-indigo-600 hover:bg-indigo-700';
        ownerCardGradient = 'from-indigo-700 via-indigo-800 to-blue-900';
    }
    else if(kpi.category === 'Initiatives'){
        categoryClass = 'bg-amber-600 text-white';
        categoryLight = 'bg-amber-100 text-amber-700';
        categoryGradient = 'from-amber-500 to-amber-700';
        categoryButton = 'bg-amber-600 hover:bg-amber-700';
        ownerCardGradient = 'from-amber-600 via-amber-700 to-orange-900';
    }
    else if(kpi.category === 'People'){
        categoryClass = 'bg-pink-700 text-white';
        categoryLight = 'bg-pink-100 text-pink-700';
        categoryGradient = 'from-pink-500 to-pink-700';
        categoryButton = 'bg-pink-600 hover:bg-pink-700';
        ownerCardGradient = 'from-pink-700 via-pink-800 to-rose-900';
    }

    let tabs = '';

    ['Q1','Q2','Q3','Q4'].forEach(q => {

        const active =
            q === activeQuarter;

        tabs += `
            <button
                onclick="renderKpiDetail('${q}')"
                class="
                    quarter-tab
                    px-4
                    py-3
                    rounded-2xl
                    font-black
                    text-sm
                    ${
                        active
                        ? 'bg-slate-900 text-white'
                        : 'bg-white border border-slate-200 text-slate-600'
                    }
                ">
                ${q}
            </button>
        `;
    });

    content.innerHTML = `

    <div class="min-h-screen bg-[#f8fafc]">

        <!-- HEADER -->
        <div class="sticky top-0 z-30 bg-white border-b border-slate-200 px-6 py-5">

            <div class="flex items-start justify-between gap-4">

                <div>

                    <div class="flex flex-wrap items-center gap-2 mb-3">

                        <div class="px-3 py-1 rounded-full text-[10px] font-black ${categoryClass}">
                            ${kpi.category || 'General'}
                        </div>

                        <div class="px-3 py-1 rounded-full text-[10px] font-black bg-slate-100 text-slate-600">
                            ${kpi.sub_category || '-'}
                        </div>

                        <div class="px-3 py-1 rounded-full text-[10px] font-black bg-slate-100 text-slate-600">
                            ${kpi.financial_year || '-'}
                        </div>

                    </div>

                    <h2 class="text-3xl font-black text-slate-900">
                        ${kpi.kpi_title || '-'}
                    </h2>

                    <p class="text-sm text-slate-500 mt-2 max-w-3xl">
                        ${kpi.kpi_description || 'No description'}
                    </p>

                </div>

                <button
                    onclick="closeKpiDetail()"
                    class="w-11 h-11 rounded-2xl bg-slate-100 hover:bg-slate-200 text-xl font-black">

                    ×

                </button>

            </div>

        </div>

        <!-- BODY -->
        <div
            class="
            p-6
            flex
            gap-6
            items-start
            "
        >

            <!-- LEFT -->
            <div
                class="
                flex-1
                min-w-0
                space-y-5
                "
            >

                <!-- QUARTER TABS -->
                <div class="flex flex-wrap gap-3">

                    ${tabs}

                </div>

                <!-- MAIN QUARTER CARD -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6">

                    <div class="flex items-start justify-between gap-5">

                        <div>

                            <div class="flex items-center gap-3">

                                <div class="w-14 h-14 rounded-2xl ${categoryLight} flex items-center justify-center text-lg font-black">
                                    ${activeQuarter}
                                </div>

                                <div>

                                    <h3 class="text-xl font-black text-slate-900">
                                        ${quarter.quarter_title || 'Quarter KPI'}
                                    </h3>

                                    <p class="text-sm text-slate-500 mt-1">
                                        ${quarter.quarter_description || 'No description'}
                                    </p>

                                </div>

                            </div>

                        </div>

                        <div class="text-right">

                            <p class="text-[10px] uppercase text-slate-400 font-black">
                                Achievement
                            </p>

                            <h2 class="text-4xl font-black ${scoreColor} mt-1">
                                ${score}%
                            </h2>

                        </div>

                    </div>

                    <!-- PERFORMANCE -->
                    <div class="mt-6">

                        <div class="h-3 bg-slate-100 rounded-full overflow-hidden">

                            <div
                                class="h-3 rounded-full bg-gradient-to-r ${progressBar}"
                                style="width:${Math.max(3,Math.min(score,100))}%">
                            </div>

                        </div>

                    </div>

                    <!-- KPI DETAILS -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">

                        <div class="rounded-2xl bg-slate-50 p-4">

                            <p class="text-[10px] uppercase text-slate-400 font-black">
                                Quarter Target
                            </p>

                            <h3 class="text-2xl font-black text-slate-900 mt-2">
                                ${Number(target).toLocaleString()}
                            </h3>

                        </div>

                        <div class="rounded-2xl bg-slate-50 p-4">

                            <p class="text-[10px] uppercase text-slate-400 font-black">
                                Actual
                            </p>

                            <h3 class="text-2xl font-black text-slate-900 mt-2">
                                ${Number(actual).toLocaleString()}
                            </h3>

                        </div>

                        <div class="rounded-2xl bg-slate-50 p-4">

                            <p class="text-[10px] uppercase text-slate-400 font-black">
                                Start Date
                            </p>

                            <h3 class="text-sm font-black text-slate-900 mt-3">
                                ${quarter.start_date || '-'}
                            </h3>

                        </div>

                        <div class="rounded-2xl bg-slate-50 p-4">

                            <p class="text-[10px] uppercase text-slate-400 font-black">
                                End Date
                            </p>

                            <h3 class="text-sm font-black text-slate-900 mt-3">
                                ${quarter.end_date || '-'}
                            </h3>

                        </div>

                    </div>

                    <!-- UPDATE FLOW -->
                    <div class="grid grid-cols-1 gap-4 mt-6">

                        <!-- STATUS -->

                        <div>

                            <label class="text-[10px] uppercase text-slate-400 font-black">

                                Status

                            </label>

                            <select
                                id="status-${quarter.id}"
                                class="
                                    w-full
                                    mt-2
                                    h-[48px]
                                    rounded-2xl
                                    border
                                    border-slate-200
                                    px-4
                                "
                            >

                                <option
                                    value="not_started"
                                    ${(quarter.status === 'not_started')
                                        ? 'selected'
                                        : ''}
                                >
                                    Not Started
                                </option>

                                <option
                                    value="on_track"
                                    ${(quarter.status === 'on_track')
                                        ? 'selected'
                                        : ''}
                                >
                                    On Track
                                </option>

                                <option
                                    value="at_risk"
                                    ${(quarter.status === 'at_risk')
                                        ? 'selected'
                                        : ''}
                                >
                                    At Risk
                                </option>

                                <option
                                    value="in_trouble"
                                    ${(quarter.status === 'in_trouble')
                                        ? 'selected'
                                        : ''}
                                >
                                    In Trouble
                                </option>

                                <option
                                    value="completed"
                                    ${(quarter.status === 'completed')
                                        ? 'selected'
                                        : ''}
                                >
                                    Completed
                                </option>

                            </select>

                        </div>

                        <button
                            onclick="saveQuarterStatus('${quarter.id}')"
                            class="
                                h-[50px]
                                rounded-2xl
                                bg-slate-800
                                text-white
                                font-black
                            "
                        >

                            Save Status

                        </button>

                        <hr class="my-5">

                            ${!beforeQuarter ? `

                                <button
                                    onclick="
                                        toggleActualUpdate(
                                            '${quarter.id}'
                                        )
                                    "
                                    class="
                                        h-[50px]
                                        rounded-2xl
                                        bg-amber-600
                                        text-white
                                        font-black
                                    "
                                >
                                    Update Actual
                                </button>

                                ` : ''}

                            <div
                                id="actual-update-${quarter.id}"
                                class="hidden space-y-4"
                            >

                                <div>

                                    <label>

                                        New Actual

                                    </label>

                                    <input
                                        id="newActual-${quarter.id}"
                                        type="number"
                                        min="0"
                                        class="
                                            w-full
                                            mt-2
                                            h-[48px]
                                            rounded-2xl
                                            border
                                            border-slate-200
                                            px-4
                                        "
                                    >

                                </div>

                                ${reasonRequired
                                    ? `
                                    <div>

                                        <label>

                                            Reason
                                            <span class="text-red-500">*</span>

                                        </label>

                                        <textarea
                                            id="reason-${quarter.id}"
                                            rows="4"
                                            class="
                                                w-full
                                                mt-2
                                                rounded-2xl
                                                border
                                                border-slate-200
                                                p-4
                                            "
                                        ></textarea>

                                    </div>
                                    `
                                    : ''
                                    }
                                <button
                                    onclick="
                                        ${
                                            reasonRequired
                                            ?
                                            `
                                            submitActualUpdateRequest(
                                                '${kpi.id}',
                                                '${quarter.id}'
                                            )
                                            `
                                            :
                                            `
                                            updateQuarterActual(
                                                '${quarter.id}'
                                            )
                                            `
                                        }
                                    "
                                >
                                    ${
                                        reasonRequired
                                        ? 'Submit Actual Update Request'
                                        : 'Save Actual'
                                    }
                                </button>
                            </div>

                        </div>

                </div>

            </div>

            <!-- RIGHT -->
            <div
                class="
                w-[360px]
                shrink-0
                "
            >

                <div
                    class="
                    sticky
                    top-6
                    space-y-5
                    "
                >

                <!-- OWNER -->
                <div class="rounded-3xl overflow-hidden bg-gradient-to-br ${ownerCardGradient} text-white p-6">

                    <p class="text-xs uppercase text-blue-200 font-black">
                        KPI Owner
                    </p>

                    <h2 class="text-2xl font-black mt-2">
                        ${kpi.employee_name || '-'}
                    </h2>

                    <div class="grid grid-cols-2 gap-4 mt-6">

                        <div class="bg-white/10 rounded-2xl p-4">

                            <p class="text-[10px] uppercase text-blue-200 font-black">
                                Weightage
                            </p>

                            <h3 class="text-2xl font-black mt-2">
                                ${kpi.weightage || 0}%
                            </h3>

                        </div>

                        <div class="bg-white/10 rounded-2xl p-4">

                            <p class="text-[10px] uppercase text-blue-200 font-black">
                                Status
                            </p>

                            <h3 class="text-sm font-black mt-3">
                                ${(quarter.status || '-').replaceAll('_',' ')}
                            </h3>

                        </div>

                    </div>

                </div>

                <!-- HISTORY -->
                <div class="bg-white rounded-3xl border border-slate-200 p-5">

                    <h3 class="text-sm font-black text-slate-900 mb-5">
                        KPI History
                    </h3>

                    <div class="space-y-4">

                        <div class="border-l-2 border-slate-300 pl-4">

                            <p class="text-xs font-black text-slate-900">
                                Quarter Updated
                            </p>

                            <p class="text-xs text-slate-500 mt-1">
                                ${quarter.updated_at || 'No update yet'}
                            </p>

                        </div>

                    </div>

                </div>

                <div class="bg-white rounded-3xl border border-slate-200 p-5">

                    <h3 class="text-sm font-black text-slate-900 mb-5">
                        Approval Timeline
                    </h3>

                    ${timelineHtml || `
                        <div class="text-xs text-slate-500">
                            No approval history
                        </div>
                    `}

                </div>

                    </div>
                </div>

            </div>

        </div>

    </div>
    `;
}

async function saveQuarterStatus(
    quarterId
){

    const status =
        document.getElementById(
            'status-' + quarterId
        ).value;

    const response =
        await fetch(

            '/kpi/quarter/' +
            quarterId +
            '/status',

            {

                method : 'POST',

                headers : {

                    'Content-Type'
                        : 'application/json',

                    'X-CSRF-TOKEN'
                        : '{{ csrf_token() }}',

                    'Accept'
                        : 'application/json'

                },

                body : JSON.stringify({

                    status : status

                })

            }

        );

    const result =
        await response.json();

    alert(
        result.message
    );

    if(result.success){

        setTimeout(() => {

            location.reload(true);

        },1000);

    }
}

function closeKpiDetail(){

    const modal = document.getElementById(
        'kpiDetailModal'
    );

    if(!modal) return;

    modal.classList.add('hidden');

    document.body.classList.remove(
        'overflow-hidden'
    );
}

    async function submitActualUpdateRequest(
        kpiId,
        quarterId
    ){

        try{

            const actual =
                document.getElementById(
                    'newActual-' + quarterId
                ).value;

            if(!actual){

                alert(
                    'New Actual required'
                );

                return;
            }

            const reasonField =
                document.getElementById(
                    'reason-' + quarterId
                );

            const reason =
                reasonField
                    ? reasonField.value.trim()
                    : '';

            const quarter =
                activeKpi.quarters.find(
                    q => q.id == quarterId
                ) || {};

            const today =
                new Date();

            const endDate =
                new Date(
                    quarter.end_date
                );

            const reasonRequired =
                (
                    today > endDate
                )
                ||
                (
                    quarter.status ===
                    'completed'
                );

            if(
                reasonRequired &&
                reason.length < 20
            ){

                alert(
                    'Reason minimum 20 characters'
                );

                return;
            }

            console.log(
                'KPI ID',
                kpiId
            );

            console.log(
                'QUARTER ID',
                quarterId
            );

            console.log(
                'PAYLOAD',
                {
                    requested_actual:
                        actual,
                    reason:
                        reason
                }
            );

            const response =
                await fetch(

                    '/kpi/' +
                    kpiId +
                    '/quarter/' +
                    quarterId +
                    '/actual-request',

                    {

                        method : 'POST',

                        headers : {

                            'Content-Type'
                                : 'application/json',

                            'X-CSRF-TOKEN'
                                : '{{ csrf_token() }}',

                            'Accept'
                                : 'application/json'

                        },

                        body : JSON.stringify({

                            requested_actual:
                                actual,

                            reason:
                                reason

                        })

                    }

                );

            console.log(
                'HTTP STATUS',
                response.status
            );

            const text =
                await response.text();

            console.log(
                'SERVER RESPONSE',
                text
            );

            let result = {};

            try{

                result =
                    JSON.parse(text);

            }catch(e){

                alert(
                    'Backend returned non-JSON response. Check console.'
                );

                return;
            }

            alert(
                result.message ||
                'Completed'
            );

            if(result.success){

                location.reload();

            }

        }catch(error){

            console.error(
                'ACTUAL UPDATE ERROR',
                error
            );

            alert(
                'Check browser console (F12)'
            );

        }

    }

    function openEditTargetModal(
        kpiId,
        baseTarget,
        stretchTarget
    ){

        document.getElementById('editKpiId').value = kpiId;

        document.getElementById('editBaseTarget').value = baseTarget;

        document.getElementById('editStretchTarget').value = stretchTarget;

        document.getElementById('editReason').value = '';

        document
            .getElementById('editTargetModal')
            .classList.remove('hidden');

        document.body.classList.add('overflow-hidden');
    }

    function closeEditTargetModal(){

        document
            .getElementById('editTargetModal')
            .classList.add('hidden');

        document.body.classList.remove('overflow-hidden');
    }

    let currentKpi = null;

    function openEditKpiModal(kpi){

        currentKpi = kpi;
        currentKpi.original_base    = Number(kpi.base_target    || 0);
        currentKpi.original_stretch = Number(kpi.stretch_target || 0);

        // ── CATEGORY THEME ─────────────────────────────────────────
        const catThemes = {
            'Financial':         { grad: 'linear-gradient(to right,#065f46,#059669)', icon: '💰', accent: '#059669', btnGrad: 'linear-gradient(to right,#059669,#047857)' },
            'Growth & Customer': { grad: 'linear-gradient(to right,#3730a3,#4f46e5)', icon: '📈', accent: '#4f46e5', btnGrad: 'linear-gradient(to right,#4f46e5,#4338ca)' },
            'Initiatives':       { grad: 'linear-gradient(to right,#92400e,#f59e0b)', icon: '🚀', accent: '#f59e0b', btnGrad: 'linear-gradient(to right,#d97706,#b45309)' },
            'People':            { grad: 'linear-gradient(to right,#9d174d,#db2777)', icon: '👥', accent: '#db2777', btnGrad: 'linear-gradient(to right,#db2777,#be185d)' },
        };
        const theme = catThemes[kpi.category] || { grad: 'linear-gradient(to right,#1e3a5f,#1e40af)', icon: '📌', accent: '#3b82f6', btnGrad: 'linear-gradient(to right,#2563eb,#1d4ed8)' };

        // ── HEADER GRADIENT ─────────────────────────────────────────
        document.getElementById('editModalHeader').style.background = theme.grad;
        document.getElementById('editSaveBtn').style.background     = theme.btnGrad;
        document.getElementById('editAccentBar').style.background   = theme.accent;

        // ── HEADER INFO ─────────────────────────────────────────────
        document.getElementById('editModalCatIcon').textContent  = theme.icon;
        document.getElementById('editModalCatPill').textContent  = kpi.category      || '-';
        document.getElementById('editModalSubPill').textContent  = kpi.sub_category  || '-';
        document.getElementById('editModalFy').textContent       = kpi.financial_year || '-';
        document.getElementById('editModalOwner').textContent    = '✍ ' + (kpi.employee_name || '-');
        document.getElementById('editModalKpiTitle').textContent = kpi.kpi_title      || 'Untitled KPI';
        document.getElementById('editModalKpiDesc').textContent  = kpi.kpi_description || 'No description.';

        // ── STATS ───────────────────────────────────────────────────
        document.getElementById('editModalWeightage').textContent = (kpi.weightage    || 0) + '%';
        document.getElementById('editModalBase').textContent      = Number(kpi.base_target    || 0).toLocaleString();
        document.getElementById('editModalStretch').textContent   = Number(kpi.stretch_target || 0).toLocaleString();

        // ── KPI SCORE ───────────────────────────────────────────────
        const quarters   = kpi.quarters || [];
        const base       = parseFloat(kpi.base_target    || 0);
        const weightage  = parseFloat(kpi.weightage      || 0);
        let   actualSum  = 0;
        quarters.forEach(q => {
            if (parseFloat(q.quarter_target || 0) > 0) {
                actualSum += parseFloat(q.quarter_actual || 0);
            }
        });
        const rawScore  = base > 0 ? (actualSum / base) * 100 : 0;
        const kpiScore  = (rawScore * weightage) / 100;
        document.getElementById('editModalScore').textContent = kpiScore.toFixed(1) + '%';

        const scoreBadge = kpiScore >= 90 ? 'Excellent' : kpiScore >= 75 ? 'Good' : kpiScore >= 50 ? 'Watch' : 'Critical';
        document.getElementById('editModalScoreBadge').textContent = scoreBadge;

        // ── QUARTER DOTS ────────────────────────────────────────────
        ['Q1','Q2','Q3','Q4'].forEach(qn => {
            const qd  = quarters.find(q => q.quarter === qn) || {};
            const qt  = parseFloat(qd.quarter_target || 0);
            const qa  = parseFloat(qd.quarter_actual  || 0);
            const qs  = qt > 0 ? (qa / qt) * 100 : 0;
            const el  = document.getElementById('edit' + qn);
            if (!el) return;
            el.className = 'w-9 h-9 rounded-xl flex items-center justify-center text-[10px] font-black ';
            if (qt <= 0)    el.className += 'bg-white/15 text-white/60';
            else if (qs >= 90) el.className += 'bg-emerald-500 text-white';
            else if (qs >= 75) el.className += 'bg-yellow-400 text-slate-900';
            else if (qs >= 50) el.className += 'bg-orange-500 text-white';
            else               el.className += 'bg-red-500 text-white';
        });

        // ── FORM FIELDS ─────────────────────────────────────────────
        document.getElementById('edit_kpi_title').value       = kpi.kpi_title       || '';
        document.getElementById('edit_kpi_description').value = kpi.kpi_description || '';
        document.getElementById('edit_status').value          = kpi.status           || 'not_started';
        document.getElementById('edit_base_target').value     = kpi.base_target      || 0;
        document.getElementById('edit_stretch_target').value  = kpi.stretch_target   || 0;
        document.getElementById('targetReasonBox').classList.add('hidden');
        document.getElementById('target_change_reason').value = '';

        // ── OPEN ─────────────────────────────────────────────────────
        const modal = document.getElementById('editKpiModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeEditKpiModal(){
        const modal = document.getElementById('editKpiModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.getElementById('edit_base_target')
            ?.addEventListener('input', checkTargetChanged);
        document.getElementById('edit_stretch_target')
            ?.addEventListener('input', checkTargetChanged);
    });

    function checkTargetChanged(){

        const base =
            Number(
                document.getElementById(
                    'edit_base_target'
                ).value || 0
            );

        const stretch =
            Number(
                document.getElementById(
                    'edit_stretch_target'
                ).value || 0
            );

        const changed =
            base !== currentKpi.original_base
            ||
            stretch !== currentKpi.original_stretch;

        document
            .getElementById(
                'targetReasonBox'
            )
            .classList[
                changed
                    ? 'remove'
                    : 'add'
            ]('hidden');
    }

    async function submitKpiEdit(){

        try {

        const title =
            document.getElementById('edit_kpi_title').value.trim();

        const description =
            document.getElementById('edit_kpi_description').value.trim();

        const status =
            document.getElementById('edit_status').value;

        const base =
            Number(document.getElementById('edit_base_target').value);

        const stretch =
            Number(document.getElementById('edit_stretch_target').value);

        if(!title){
            alert('KPI Title is required.');
            return;
        }

        const targetChanged =
            base !== currentKpi.original_base
            || stretch !== currentKpi.original_stretch;

        /*
        |--------------------------------------------------------------------------
        | TARGET CHANGE REQUEST
        |--------------------------------------------------------------------------
        */

        if(targetChanged){

            const reason =
                document.getElementById(
                    'target_change_reason'
                ).value;

            if(reason.trim().length < 30){

                alert(
                    'Reason minimum 30 characters'
                );

                return;
            }

            const response =
                await fetch(

                    `/kpi/${currentKpi.id}/request-target-change`,

                    {

                        method : 'POST',

                        headers : {

                            'Content-Type'
                                : 'application/json',

                            'Accept'
                                : 'application/json',

                            'X-CSRF-TOKEN'
                                : '{{ csrf_token() }}'

                        },

                        body : JSON.stringify({

                            new_base_target:
                                base,

                            new_stretch_target:
                                stretch,

                            reason:
                                reason

                        })

                    }

                );

            const result =
                await response.json();

            if(result.success){
                alert(result.message ?? 'Target change request submitted.');
                closeEditKpiModal();
                location.reload();
            } else {
                alert(result.message ?? 'Failed to submit target change request.');
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | NORMAL KPI UPDATE
        |--------------------------------------------------------------------------
        */

        const response =
            await fetch(

                `/kpi/${currentKpi.id}`,

                {

                    method : 'PUT',

                    headers : {

                        'Content-Type'
                            : 'application/json',

                        'Accept'
                            : 'application/json',

                        'X-CSRF-TOKEN'
                            : '{{ csrf_token() }}'

                    },

                    body : JSON.stringify({

                        category:
                            currentKpi.category,

                        sub_category:
                            currentKpi.sub_category,

                        unit:
                            currentKpi.unit,

                        actual_value:
                            currentKpi.actual_value,

                        kpi_title:
                            title,

                        kpi_description:
                            description,

                        status:
                            status

                    })

                }

            );

        const result =
            await response.json();

        if(result.success){
            closeEditKpiModal();
            location.reload();
        } else {
            alert(result.message ?? 'Failed to update KPI. Please try again.');
        }

        } catch(err) {
            console.error('submitKpiEdit error:', err);
            alert('An error occurred. Please try again.');
        }

    }
    async function submitEditTargetRequest(){

        const kpiId = document.getElementById('editKpiId').value;

        const baseTarget = document.getElementById('editBaseTarget').value;

        const stretchTarget = document.getElementById('editStretchTarget').value;

        const reason = document.getElementById('editReason').value;

        if(
            baseTarget === '' ||
            stretchTarget === ''
        ){
            alert('Please fill all target fields.');
            return;
        }

        if(reason.trim() === ''){
            alert('Reason is required.');
            return;
        }

        try{

            const response = await fetch(
                '/kpi/' + kpiId + '/request-edit',
                {

                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },

                    body: JSON.stringify({

                        base_target: baseTarget,

                        stretch_target: stretchTarget,

                        reason: reason

                    })
                }
            );

            const result = await response.json();

            if(result.success){

                alert('Edit request submitted.');

                location.reload();

            } else {

                alert(result.message || 'Request failed.');
            }

        } catch(error){

            console.error(error);

            alert('System error.');
        }
    }

    function openDeleteKpiModal(
        kpiId,
        title
    ){

        document.getElementById(
            'deleteKpiId'
        ).value = kpiId;

        document.getElementById(
            'deleteKpiTitle'
        ).innerText = title;

        document.getElementById(
            'deleteReason'
        ).value = '';

        const modal =
            document.getElementById(
                'deleteKpiModal'
            );

        modal.classList.remove(
            'hidden'
        );

        modal.classList.add(
            'flex'
        );

        document.body.classList.add(
            'overflow-hidden'
        );
    }

    function closeDeleteKpiModal(){

        const modal =
            document.getElementById(
                'deleteKpiModal'
            );

        modal.classList.add(
            'hidden'
        );

        modal.classList.remove(
            'flex'
        );

        document.body.classList.remove(
            'overflow-hidden'
        );
    }

    function toggleActualUpdate(
        quarterId
    ){

        const panel =
            document.getElementById(
                'actual-update-' +
                quarterId
            );

        if(!panel){
            return;
        }

        panel.classList.toggle(
            'hidden'
        );
    }



    async function updateQuarterActual(
        quarterId
    ){

        const actual =
            document.getElementById(
                'newActual-' + quarterId
            ).value;

        if(!actual){

            alert(
                'New Actual required'
            );

            return;
        }

        try{

            const response =
                await fetch(

                    '/kpi/update-quarter',

                    {

                        method : 'POST',

                        headers : {

                            'Content-Type'
                                : 'application/json',

                            'X-CSRF-TOKEN'
                                : '{{ csrf_token() }}',

                            'Accept'
                                : 'application/json'

                        },

                        body : JSON.stringify({

                            quarter_id :
                                quarterId,

                            quarter_actual :
                                actual

                        })

                    }

                );

            const result =
                await response.json();

            alert(
                result.message
            );

            if(result.success){

                location.reload();

            }

        }
        catch(error){

            console.error(error);

            alert(
                'System error'
            );

        }

    }

    async function submitDeleteRequest(){

        const kpiId = document.getElementById('deleteKpiId').value;

        const reason = document.getElementById('deleteReason').value;

        if(reason.trim().length < 30){

            alert(
                'Reason minimum 30 characters'
            );

            return;
        }

        try{

            const response = await fetch(
                '/kpi/' + kpiId + '/request-delete',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ reason: reason })
                }
            );

            const result = await response.json();

            if(result.success){
                closeDeleteKpiModal();
                alert('Delete request submitted successfully.');
                location.reload();
            } else {
                alert(result.message || 'Request failed.');
            }

        }
        catch(error){

            console.error(error);

            alert('System error.');
        }
    }


/* ═══════════════════════════════════════════════════════════════
   REDESIGNED renderKpiDetail — inline-editable, no approval needed
   ═══════════════════════════════════════════════════════════════ */
function renderKpiDetail(activeQuarter) {
    const content = document.getElementById('kpiDetailContent');
    const kpi = activeKpi;
    if (!kpi || !content) return;

    const timeline = kpi.approval_timeline || [];
    const quarters = kpi.quarters || [];
    const quarter  = quarters.find(q => q.quarter === activeQuarter) || {};

    // ── dates & flags ─────────────────────────────────────────
    const today         = new Date();
    const startDate     = new Date(quarter.start_date);
    const endDate       = new Date(quarter.end_date);
    const beforeQuarter = today < startDate;
    // Q1/Q2 2026 stay updatable without an approval reason through 31 Jul
    // 2026 (mid-year appraisal grace period), even past their normal end
    // date — reverts to the standard past-quarter behaviour on its own
    // from 1 Aug 2026, no manual toggle needed.
    const inQ1Q2GracePeriod = ['Q1', 'Q2'].includes(quarter.quarter) && today <= new Date('2026-07-31T23:59:59');
    const afterQuarter  = today > endDate && !inQ1Q2GracePeriod;
    const completed     = quarter.status === 'completed' || quarter.status === 'pending_completion';
    const reasonRequired = afterQuarter || completed;

    // ── score ─────────────────────────────────────────────────
    const target = parseFloat(quarter.quarter_target || 0);
    const actual = parseFloat(quarter.quarter_actual || 0);
    const score  = target > 0 ? Number(((actual / target) * 100).toFixed(1)) : 0;
    const gap    = target > 0 ? Number((target - actual).toFixed(1)) : 0;

    // ── category theme ────────────────────────────────────────
    const themes = {
        'Financial':         { grad:'from-emerald-800 to-emerald-600', light:'bg-emerald-100 text-emerald-800', ringColor:'#10b981', ownerGrad:'from-emerald-900 via-emerald-800 to-teal-900', icon:'💰' },
        'Growth & Customer': { grad:'from-indigo-800 to-indigo-600',   light:'bg-indigo-100 text-indigo-800',   ringColor:'#6366f1', ownerGrad:'from-indigo-900 via-indigo-800 to-blue-900',   icon:'📈' },
        'Initiatives':       { grad:'from-amber-700 to-amber-500',     light:'bg-amber-100 text-amber-800',     ringColor:'#f59e0b', ownerGrad:'from-amber-900 via-amber-700 to-orange-900',   icon:'🚀' },
        'People':            { grad:'from-pink-800 to-pink-600',       light:'bg-pink-100 text-pink-800',       ringColor:'#ec4899', ownerGrad:'from-pink-900 via-pink-800 to-rose-900',       icon:'👥' },
    };
    const t = themes[kpi.category] || { grad:'from-slate-800 to-slate-600', light:'bg-slate-100 text-slate-700', ringColor:'#64748b', ownerGrad:'from-slate-900 via-slate-800 to-slate-900', icon:'📌' };

    // ── score colours ─────────────────────────────────────────
    const scoreColor    = score >= 90 ? '#10b981' : score >= 75 ? '#f59e0b' : score >= 50 ? '#f97316' : '#ef4444';
    const scoreTextCls  = score >= 90 ? 'text-emerald-600' : score >= 75 ? 'text-amber-500' : score >= 50 ? 'text-orange-500' : 'text-red-500';
    const scoreBadgeCls = score >= 90 ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : score >= 75 ? 'bg-amber-50 text-amber-700 border-amber-200'
                        : score >= 50 ? 'bg-orange-50 text-orange-700 border-orange-200'
                        :               'bg-red-50 text-red-700 border-red-200';
    const scoreLabel    = score >= 90 ? 'Excellent' : score >= 75 ? 'Good' : score >= 50 ? 'Watch' : 'Critical';

    // ── SVG donut ─────────────────────────────────────────────
    const radius = 48;
    const circ   = 2 * Math.PI * radius;
    const dashOff = circ - (Math.min(score, 100) / 100) * circ;

    // ── status badge ──────────────────────────────────────────
    const statusMap = {
        on_track:           ['On Track',          'bg-emerald-100 text-emerald-700 border-emerald-200', '🟢'],
        at_risk:            ['At Risk',            'bg-amber-100 text-amber-700 border-amber-200',       '🟡'],
        in_trouble:         ['In Trouble',         'bg-red-100 text-red-700 border-red-200',             '🔴'],
        completed:          ['Completed',          'bg-[#F5EAE0] text-[#6B3F2A] border-[#6B3F2A]/30',          '🔵'],
        not_started:        ['Not Started',        'bg-slate-100 text-slate-600 border-slate-200',       '⚪'],
        pending_completion: ['Pending Approval',   'bg-yellow-100 text-yellow-700 border-yellow-200',   '⏳'],
    };
    const [sLabel, sCls] = statusMap[quarter.status] ?? ['Not Started', 'bg-slate-100 text-slate-600 border-slate-200'];

    // ── quarter dot helper ────────────────────────────────────
    const getQInfo = q => {
        const qd = quarters.find(x => x.quarter === q) || {};
        const qt = parseFloat(qd.quarter_target || 0);
        const qa = parseFloat(qd.quarter_actual  || 0);
        const qs = qt > 0 ? (qa / qt) * 100 : -1;
        if (qs < 0)  return { dot:'bg-slate-300', text:'text-slate-400', score:null };
        if (qs >= 90) return { dot:'bg-emerald-500', text:'text-emerald-600', score:qs.toFixed(1) };
        if (qs >= 75) return { dot:'bg-amber-400',   text:'text-amber-600',   score:qs.toFixed(1) };
        if (qs >= 50) return { dot:'bg-orange-500',  text:'text-orange-600',  score:qs.toFixed(1) };
        return { dot:'bg-red-500', text:'text-red-600', score:qs.toFixed(1) };
    };

    // ── all-quarters sidebar summary ──────────────────────────
    let quarterSummaryHtml = '';
    ['Q1','Q2','Q3','Q4'].forEach(q => {
        const qd  = quarters.find(x => x.quarter === q) || {};
        const qt  = parseFloat(qd.quarter_target || 0);
        const qa  = parseFloat(qd.quarter_actual  || 0);
        const inf = getQInfo(q);
        const isActive = q === activeQuarter;
        quarterSummaryHtml += `
        <div class="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0 ${isActive ? 'font-black' : ''}">
            <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full ${inf.dot} shrink-0"></span>
                <span class="text-xs ${isActive ? 'font-black text-slate-900' : 'font-semibold text-slate-600'}">${q}</span>
                ${qt > 0
                    ? `<span class="text-[10px] text-slate-400">${Number(qa).toLocaleString()} / ${Number(qt).toLocaleString()}</span>`
                    : '<span class="text-[10px] text-slate-300 italic">not set</span>'}
            </div>
            <span class="text-xs font-black ${inf.text}">${inf.score !== null ? inf.score + '%' : '—'}</span>
        </div>`;
    });

    // ── approval timeline ─────────────────────────────────────
    const fmtMY = d => {
        if (!d) return '-';
        const str = String(d).replace('T', ' ');
        const [datePart, timePart = '00:00:00'] = str.split(' ');
        const [y, mo, day] = datePart.split('-');
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const [h, min] = timePart.split(':');
        const hour = parseInt(h, 10);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const h12  = String(hour % 12 || 12).padStart(2, '0');
        return `${parseInt(day, 10)} ${months[parseInt(mo, 10) - 1]} ${y}, ${h12}:${min} ${ampm}`;
    };

    let timelineHtml = '';
    timeline.forEach(item => {
        const s  = (item.status || '').toLowerCase();
        const dc = s === 'approved' ? 'bg-emerald-500' : s === 'rejected' ? 'bg-red-500' : 'bg-amber-400';
        const bc = s === 'approved' ? 'bg-emerald-50 border-emerald-100' : s === 'rejected' ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100';
        const tc = s === 'approved' ? 'text-emerald-700' : s === 'rejected' ? 'text-red-700' : 'text-amber-700';
        const lbl = (item.status || '-').charAt(0).toUpperCase() + (item.status || '-').slice(1).replace('_',' ');
        const showApprover = item.approver && item.approver !== '-';
        timelineHtml += `
        <div class="flex gap-3 mb-3">
            <div class="flex flex-col items-center shrink-0">
                <div class="w-3 h-3 rounded-full ${dc} mt-0.5"></div>
                <div class="w-px flex-1 bg-slate-200 mt-1"></div>
            </div>
            <div class="rounded-2xl border ${bc} p-3 flex-1">
                <p class="text-xs font-black ${tc}">${lbl}</p>
                ${item.type         ? `<p class="text-[10px] text-slate-500 mt-0.5">${item.type}</p>` : ''}
                ${item.by           ? `<p class="text-[10px] text-slate-500">By: ${item.by}</p>` : ''}
                ${showApprover      ? `<p class="text-[10px] text-slate-500">Approver: ${item.approver}</p>` : ''}
                <p class="text-[10px] text-slate-400 mt-1">${fmtMY(item.date)}</p>
            </div>
        </div>`;
    });

    // ── quarter tabs ──────────────────────────────────────────
    let tabs = '';
    ['Q1','Q2','Q3','Q4'].forEach(q => {
        const active = q === activeQuarter;
        const inf    = getQInfo(q);
        tabs += `
        <button onclick="renderKpiDetail('${q}')"
            class="flex items-center gap-2 px-5 py-2.5 rounded-2xl font-black text-sm transition-all
            ${active ? 'bg-slate-900 text-white shadow-lg' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'}">
            <span class="w-2 h-2 rounded-full ${active ? 'bg-white' : inf.dot}"></span>${q}
        </button>`;
    });

    // ── owner avatar ──────────────────────────────────────────
    const ownerName     = kpi.employee_name || '-';
    const ownerInitials = ownerName.split(' ').filter(Boolean).slice(0,2).map(p => p[0]).join('').toUpperCase() || '?';

    // ── date formatter ────────────────────────────────────────
    const fmtDate = d => {
        if (!d) return '-';
        const dt = new Date(d);
        return isNaN(dt) ? d : dt.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
    };

    const gapDisplay = target <= 0 ? '—' : gap <= 0 ? '🎯 On target!' : `${Number(gap).toLocaleString()} to go`;
    const gapCls     = gap <= 0 && target > 0 ? 'text-emerald-600 font-black' : 'text-amber-600 font-black';

    // ── unit formatting ───────────────────────────────────────
    const unitRaw   = (kpi.unit || '').toLowerCase();
    const unitLabel = unitRaw === 'currency' ? 'Currency' : (unitRaw === 'percentage' || unitRaw === 'percent') ? 'Percentage' : 'Number';
    const fmtVal    = v => unitRaw === 'currency' ? 'RM ' + Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                         : (unitRaw === 'percentage' || unitRaw === 'percent') ? Number(v).toLocaleString() + '%'
                         : Number(v).toLocaleString();

    // ── CSRF token ────────────────────────────────────────────
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
              || '{{ csrf_token() }}';

    content.innerHTML = `
    <div class="min-h-screen bg-[#f4f7fb]">

        <!-- ══ STICKY GRADIENT HEADER ══ -->
        <div class="sticky top-0 z-30 bg-gradient-to-r ${t.grad} text-white px-6 py-5 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="text-lg">${t.icon}</span>
                        <span class="px-2.5 py-1 rounded-full bg-white/20 text-[10px] font-black">${kpi.category || '-'}</span>
                        <span class="px-2.5 py-1 rounded-full bg-white/15 text-[10px] font-black text-white/90">${kpi.sub_category || '-'}</span>
                        <span class="px-2.5 py-1 rounded-full bg-white/10 text-[10px] font-black text-white/70">${kpi.financial_year || '-'}</span>
                    </div>
                    <h2 class="text-2xl font-black leading-tight">${kpi.kpi_title || '-'}</h2>
                    <p class="text-white/65 text-xs mt-1 max-w-2xl leading-relaxed">${kpi.kpi_description || 'No description'}</p>
                </div>
                <button onclick="closeKpiDetail()"
                    class="w-11 h-11 rounded-2xl bg-white/20 hover:bg-white/30 flex items-center justify-center text-xl font-black shrink-0 transition-all">×</button>
            </div>
        </div>

        <!-- ══ BODY ══ -->
        <div class="p-6 grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-6 items-start">

            <!-- ── LEFT COLUMN ── -->
            <div class="space-y-5 min-w-0">

                <!-- QUARTER TABS -->
                <div class="flex flex-wrap gap-2.5">${tabs}</div>

                <!-- ① EDIT KPI INFO CARD -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-1 h-5 rounded-full bg-slate-800 shrink-0"></span>
                        <p class="text-xs font-black text-slate-700 uppercase tracking-wider">KPI Details — Edit Directly</p>
                        <span class="ml-auto text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-lg font-bold">No approval needed</span>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">KPI Title</label>
                            <input id="kpiTitleInput" type="text" value="${(kpi.kpi_title || '').replace(/"/g,'&quot;')}"
                                class="w-full mt-1.5 h-11 rounded-2xl border border-slate-200 px-4 text-sm font-semibold text-slate-900 focus:border-slate-400 transition">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Description</label>
                            <textarea id="kpiDescInput" rows="2"
                                class="w-full mt-1.5 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 resize-none focus:border-slate-400 transition"
                            >${kpi.kpi_description || ''}</textarea>
                        </div>
                        <button onclick="saveKpiInline('${kpi.id}')"
                            class="w-full h-11 rounded-2xl bg-slate-900 hover:bg-slate-700 text-white font-black text-sm transition-all flex items-center justify-center gap-2 shadow-lg shadow-slate-900/20">
                            💾 Save KPI Details
                        </button>
                    </div>
                </div>

                <!-- ② ACHIEVEMENT HERO CARD -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-4">
                        <!-- Left: title + status + progress bar -->
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-2xl ${t.light} flex items-center justify-center font-black shrink-0">${activeQuarter}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-black text-slate-700">${quarter.quarter_title || activeQuarter + ' Plan'}</p>
                                <span class="inline-flex items-center gap-1 mt-0.5 px-2.5 py-0.5 rounded-lg border text-[10px] font-black ${sCls}">${sLabel}</span>
                                <!-- progress bar -->
                                <div class="mt-3">
                                    <div class="flex justify-between text-[9px] text-slate-400 font-black mb-1">
                                        <span>0%</span><span>50%</span><span>75%</span><span>90%</span><span>100%</span>
                                    </div>
                                    <div class="relative h-2.5 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                                        <div class="h-2.5 rounded-full transition-all duration-700"
                                            style="width:${Math.max(2,Math.min(score,100))}%; background:linear-gradient(to right,${scoreColor}99,${scoreColor})"></div>
                                        <div class="absolute top-0 bottom-0 w-px bg-white/70" style="left:50%"></div>
                                        <div class="absolute top-0 bottom-0 w-px bg-white/70" style="left:75%"></div>
                                        <div class="absolute top-0 bottom-0 w-px bg-white/70" style="left:90%"></div>
                                    </div>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-[10px] text-slate-400">Current: <strong class="${scoreTextCls}">${score}%</strong></span>
                                        <span class="text-[10px] ${gapCls}">${gapDisplay}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Right: donut ring -->
                        <div class="shrink-0 flex flex-col items-center">
                            <div class="relative w-[90px] h-[90px]">
                                <svg viewBox="0 0 120 120" class="w-full h-full" style="transform:rotate(-90deg)">
                                    <circle cx="60" cy="60" r="${radius}" fill="none" stroke="#f1f5f9" stroke-width="14"/>
                                    <circle cx="60" cy="60" r="${radius}" fill="none" stroke="${scoreColor}" stroke-width="14"
                                        stroke-linecap="round"
                                        stroke-dasharray="${circ.toFixed(2)}"
                                        stroke-dashoffset="${dashOff.toFixed(2)}"/>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-lg font-black ${scoreTextCls}">${score}%</span>
                                    <span class="text-[8px] text-slate-400 uppercase font-black">Score</span>
                                </div>
                            </div>
                            <span class="mt-1 px-2.5 py-0.5 rounded-xl border text-[9px] font-black ${scoreBadgeCls}">${scoreLabel}</span>
                        </div>
                    </div>

                    <!-- STAT MINI-CARDS -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 px-6 py-4">
                        <div class="rounded-2xl bg-sky-50 border border-sky-100 p-3">
                            <div class="flex items-center gap-1 mb-1"><span class="text-xs">📐</span><p class="text-[9px] uppercase text-sky-500 font-black">Unit</p></div>
                            <p class="text-sm font-black text-slate-700">${unitLabel}</p>
                        </div>
                        <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-3">
                            <div class="flex items-center gap-1 mb-1"><span class="text-xs">🎯</span><p class="text-[9px] uppercase text-[#8B5E4A] font-black">Target</p></div>
                            <p class="text-xl font-black text-slate-900">${fmtVal(target)}</p>
                        </div>
                        <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-3">
                            <div class="flex items-center gap-1 mb-1"><span class="text-xs">✅</span><p class="text-[9px] uppercase text-emerald-600 font-black">Actual</p></div>
                            <p class="text-xl font-black text-slate-900">${fmtVal(actual)}</p>
                        </div>
                        <div class="rounded-2xl bg-violet-50 border border-violet-100 p-3">
                            <div class="flex items-center gap-1 mb-1"><span class="text-xs">📅</span><p class="text-[9px] uppercase text-violet-500 font-black">Start</p></div>
                            <p class="text-sm font-black text-slate-900">${fmtDate(quarter.start_date)}</p>
                        </div>
                        <div class="rounded-2xl bg-rose-50 border border-rose-100 p-3">
                            <div class="flex items-center gap-1 mb-1"><span class="text-xs">🏁</span><p class="text-[9px] uppercase text-rose-500 font-black">End</p></div>
                            <p class="text-sm font-black text-slate-900">${fmtDate(quarter.end_date)}</p>
                        </div>
                    </div>
                </div>

                <!-- ③ EDIT QUARTER CARD -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-1 h-5 rounded-full bg-indigo-500 shrink-0"></span>
                        <p class="text-xs font-black text-slate-700 uppercase tracking-wider">Edit ${activeQuarter} Quarter Details</p>
                        <span class="ml-auto text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-100 px-2 py-0.5 rounded-lg font-bold">No approval needed</span>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Quarter Title</label>
                                <input id="qTitleInput" type="text" value="${(quarter.quarter_title || '').replace(/"/g,'&quot;')}"
                                    class="w-full mt-1.5 h-11 rounded-2xl border border-slate-200 px-4 text-sm font-semibold text-slate-900 focus:border-indigo-400 transition">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</label>
                                <select id="qStatusInput" onchange="toggleCompletionProof(this.value)"
                                    class="w-full mt-1.5 h-11 rounded-2xl border border-slate-200 px-4 text-sm font-medium text-slate-800 bg-white cursor-pointer">
                                    <option value="not_started" ${quarter.status==='not_started'?'selected':''}>⚪ Not Started</option>
                                    <option value="on_track"    ${quarter.status==='on_track'   ?'selected':''}>🟢 On Track</option>
                                    <option value="at_risk"     ${quarter.status==='at_risk'    ?'selected':''}>🟡 At Risk</option>
                                    <option value="in_trouble"  ${quarter.status==='in_trouble' ?'selected':''}>🔴 In Trouble</option>
                                    <option value="completed"   ${quarter.status==='completed'  ?'selected':''}>🔵 Completed</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Quarter Description</label>
                            <textarea id="qDescInput" rows="2"
                                class="w-full mt-1.5 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 resize-none focus:border-indigo-400 transition"
                            >${quarter.quarter_description || ''}</textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Start Date</label>
                                <input id="qStartInput" type="date" value="${quarter.start_date || ''}"
                                    class="w-full mt-1.5 h-11 rounded-2xl border border-slate-200 px-4 text-sm font-medium text-slate-800 focus:border-indigo-400 transition">
                            </div>
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">End Date</label>
                                <input id="qEndInput" type="date" value="${quarter.end_date || ''}"
                                    class="w-full mt-1.5 h-11 rounded-2xl border border-slate-200 px-4 text-sm font-medium text-slate-800 focus:border-indigo-400 transition">
                            </div>
                        </div>

                        <!-- MERGED ACTUAL VALUE — only when open quarter, no approval needed -->
                        ${(!reasonRequired && !beforeQuarter) ? `
                        <div id="mergedActualSection" class="${quarter.status === 'completed' ? 'hidden' : ''}">
                            <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                                <label class="text-[10px] font-black text-amber-700 uppercase tracking-widest">New Actual Value <span class="text-slate-400 normal-case font-medium">(optional — leave blank to only update details)</span></label>
                                <input id="newActual-${quarter.id}" type="number" min="0"
                                    class="w-full mt-1.5 h-11 rounded-2xl border border-slate-200 px-4 text-sm focus:border-amber-400 transition"
                                    placeholder="Enter new actual value" value="${quarter.quarter_actual ?? ''}">
                            </div>
                        </div>
                        ` : ''}

                        <!-- COMPLETION PROOF — shown only when status = completed -->
                        <div id="completionProofSection" class="${quarter.status === 'completed' ? '' : 'hidden'}">
                            <div class="rounded-2xl border border-[#6B3F2A]/30 bg-[#FBF5EF] p-4 space-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🏆</span>
                                    <p class="text-xs font-black text-[#4a2a1a] uppercase tracking-wide">Completion Proof Required</p>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Completion Review <span class="text-red-500">*</span> <span class="text-slate-400 normal-case font-medium">(min 10 chars)</span>
                                    </label>
                                    <textarea id="qCompletionReview" rows="3" placeholder="Describe what was achieved, challenges faced, and outcome..."
                                        class="w-full mt-1.5 rounded-2xl border border-[#6B3F2A]/30 bg-white px-4 py-3 text-sm text-slate-700 resize-none focus:border-[#6B3F2A] transition"></textarea>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                        Proof Files <span class="text-red-500">*</span> <span class="text-slate-400 normal-case font-medium">(JPG, PNG, PDF · max 5 MB each · up to 5 files)</span>
                                    </label>
                                    <input id="qProofImage" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
                                        class="w-full mt-1.5 rounded-2xl border border-[#6B3F2A]/30 bg-white px-4 py-2.5 text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-[#6B3F2A] file:text-white hover:file:bg-[#5a3323] cursor-pointer">
                                    <div id="proofPreviewList" class="hidden mt-2 space-y-1.5"></div>
                                </div>
                            </div>
                        </div>

                        <button id="qSaveBtn" onclick="quarterSaveDispatch('${quarter.id}')"
                            class="w-full h-11 rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-black text-sm transition-all flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20">
                            ${quarter.status === 'completed' ? '🏆 Submit Completion' : ((!reasonRequired && !beforeQuarter) ? '💾 Save Quarter & Actual' : '💾 Save Quarter Details')}
                        </button>
                    </div>
                </div>

                <!-- ④ UPDATE ACTUAL CARD — only shown when approval is needed or quarter hasn't started -->
                ${(reasonRequired && !beforeQuarter) ? `
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
                        <span class="w-1 h-5 rounded-full bg-amber-500 shrink-0"></span>
                        <p class="text-xs font-black text-slate-700 uppercase tracking-wider">Update Actual Value</p>
                        <span class="ml-auto text-[10px] bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-lg font-bold">⚠ Requires reason (past/completed)</span>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">New Actual Value</label>
                            <input id="newActual-${quarter.id}" type="number" min="0"
                                class="w-full mt-1.5 h-11 rounded-2xl border border-slate-200 px-4 text-sm focus:border-amber-400 transition"
                                placeholder="Enter new actual value">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-red-500 uppercase tracking-widest">Reason <span class="text-red-500">*</span> (min 20 chars)</label>
                            <textarea id="reason-${quarter.id}" rows="3"
                                class="w-full mt-1.5 rounded-2xl border border-slate-200 px-4 py-3 text-sm resize-none"
                                placeholder="Explain why you are updating a past/completed quarter…"></textarea>
                        </div>
                        <button onclick="submitActualUpdateRequest('${kpi.id}','${quarter.id}')"
                            class="w-full h-11 rounded-2xl bg-gradient-to-r from-red-500 to-red-600 text-white font-black text-sm transition-all flex items-center justify-center gap-2 shadow-lg">
                            📋 Submit Update Request
                        </button>
                    </div>
                </div>
                ` : ''}
                ${beforeQuarter ? `
                <div class="bg-slate-50 rounded-3xl border border-dashed border-slate-300 p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-slate-200 flex items-center justify-center text-xl shrink-0">⏳</div>
                    <div>
                        <p class="text-sm font-black text-slate-600">Quarter Not Started Yet</p>
                        <p class="text-xs text-slate-400 mt-0.5">Actual updates available from ${fmtDate(quarter.start_date)}</p>
                    </div>
                </div>` : ''}

            </div>

            <!-- ── RIGHT SIDEBAR ── -->
            <div class="space-y-5">

                <!-- OWNER CARD -->
                <div class="rounded-3xl overflow-hidden bg-gradient-to-br ${t.ownerGrad} text-white shadow-xl">
                    <div class="p-6">
                        <p class="text-[10px] uppercase text-white/50 font-black tracking-widest mb-4">KPI Owner</p>
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/20 flex items-center justify-center text-xl font-black shrink-0">${ownerInitials}</div>
                            <div>
                                <h2 class="text-xl font-black leading-tight">${ownerName}</h2>
                                ${kpi.employee_role ? `<p class="text-white/55 text-xs mt-0.5">${kpi.employee_role}</p>` : ''}
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-white/10 rounded-2xl p-4 border border-white/10">
                                <p class="text-[10px] uppercase text-white/50 font-black">Weightage</p>
                                <h3 class="text-2xl font-black mt-1">${kpi.weightage || 0}%</h3>
                            </div>
                            <div class="bg-white/10 rounded-2xl p-4 border border-white/10">
                                <p class="text-[10px] uppercase text-white/50 font-black">Base Target</p>
                                <h3 class="text-lg font-black mt-1">${Number(kpi.base_target || 0).toLocaleString()}</h3>
                            </div>
                        </div>
                        ${kpi.unit ? `
                        <div class="mt-3 bg-white/10 rounded-2xl px-4 py-3 border border-white/10 flex items-center justify-between">
                            <p class="text-[10px] uppercase text-white/50 font-black">Unit</p>
                            <p class="text-sm font-black text-white/85 capitalize">${kpi.unit}</p>
                        </div>` : ''}
                    </div>
                </div>

                <!-- ALL QUARTERS SUMMARY -->
                <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-1 h-4 rounded-full bg-slate-300 shrink-0"></span>
                        <h3 class="text-xs font-black text-slate-600 uppercase tracking-wider">All Quarters</h3>
                    </div>
                    ${quarterSummaryHtml}
                </div>

                <!-- KPI HISTORY -->
                <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-1 h-4 rounded-full bg-slate-300 shrink-0"></span>
                        <h3 class="text-xs font-black text-slate-600 uppercase tracking-wider">KPI History</h3>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-3 h-3 rounded-full bg-[#8B5E4A] mt-0.5"></div>
                            <div class="w-px flex-1 bg-slate-100 mt-1"></div>
                        </div>
                        <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-3 flex-1">
                            <p class="text-xs font-black text-[#6B3F2A]">Quarter Updated</p>
                            <p class="text-[10px] text-slate-500 mt-1">
                                ${quarter.updated_at
                                    ? new Date(quarter.updated_at).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})
                                    : 'No update yet'}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- COMPLETION PROOF DISPLAY (if quarter already completed/pending with proof) -->
                ${(quarter.status === 'completed' || quarter.status === 'pending_completion') && (quarter.completion_review || quarter.completion_proof_url || quarter.completion_proof_urls?.length) ? `
                <div class="bg-white rounded-3xl border border-[#6B3F2A]/30 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#6B3F2A]/20 bg-[#FBF5EF] flex items-center gap-2">
                        <span class="text-lg">🏆</span>
                        <p class="text-xs font-black text-[#4a2a1a] uppercase tracking-wider">Completion Evidence</p>
                        <span class="ml-auto text-[10px] ${quarter.status === 'completed' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-amber-100 text-amber-700 border-amber-200'} border px-2 py-0.5 rounded-lg font-bold">
                            ${quarter.status === 'completed' ? 'Approved ✓' : 'Pending Approval'}
                        </span>
                    </div>
                    <div class="p-5 space-y-4">
                        ${quarter.completion_review ? `
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Completion Review</p>
                            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                <p class="text-sm text-slate-700 leading-relaxed">${quarter.completion_review.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')}</p>
                            </div>
                        </div>` : ''}
                        ${renderProofFiles(quarter)}
                        ${quarter.completion_submitted_at ? `
                        <p class="text-[10px] text-slate-400">Submitted on ${new Date(quarter.completion_submitted_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})}</p>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <!-- APPROVAL TIMELINE -->
                <div class="bg-white rounded-3xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-1 h-4 rounded-full bg-slate-300 shrink-0"></span>
                        <h3 class="text-xs font-black text-slate-600 uppercase tracking-wider">Approval Timeline</h3>
                    </div>
                    ${timelineHtml || `
                    <div class="rounded-2xl bg-slate-50 border border-dashed border-slate-200 p-4 text-center">
                        <p class="text-xs text-slate-400 font-medium">No approval history yet</p>
                    </div>`}
                </div>

            </div>
        </div>
    </div>
    `;
}

/* Save KPI title + description inline (no approval) */
async function saveKpiInline(kpiId) {
    const title = document.getElementById('kpiTitleInput')?.value?.trim();
    const desc  = document.getElementById('kpiDescInput')?.value ?? '';

    if (!title) { alert('KPI Title is required.'); return; }

    const btn = event?.currentTarget;
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

    try {
        const res = await fetch(`/kpi/${kpiId}/inline-update`, {
            method: 'PUT',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
            body: JSON.stringify({ kpi_title: title, kpi_description: desc }),
        });
        const data = await res.json();
        if (data.success) {
            activeKpi.kpi_title       = title;
            activeKpi.kpi_description = desc;
            // Refresh header text without full re-render
            const hdr = document.querySelector('#kpiDetailContent h2');
            if (hdr) hdr.textContent = title;
            showToast('KPI details saved ✓', 'emerald');
        } else {
            alert(data.message || 'Failed to save.');
        }
    } catch(e) {
        alert('Network error.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '💾 Save KPI Details'; }
    }
}

/* Save quarter title, description, status, start/end dates inline (no approval) */
async function saveQuarterInline(quarterId) {
    const title  = document.getElementById('qTitleInput')?.value?.trim()  ?? '';
    const desc   = document.getElementById('qDescInput')?.value            ?? '';
    const status = document.getElementById('qStatusInput')?.value          ?? '';
    const start  = document.getElementById('qStartInput')?.value           ?? '';
    const end    = document.getElementById('qEndInput')?.value             ?? '';

    if (start && end && start > end) { alert('Start date cannot be after end date.'); return; }

    const btn = event?.currentTarget;
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

    try {
        const res = await fetch(`/kpi/quarter/${quarterId}/inline-update`, {
            method: 'PUT',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
            body: JSON.stringify({ quarter_title: title, quarter_description: desc, status, start_date: start || null, end_date: end || null }),
        });
        const data = await res.json();
        if (data.success) {
            // patch activeKpi in memory so re-renders use updated data
            const q = activeKpi.quarters?.find(x => x.id == quarterId);
            if (q) {
                q.quarter_title       = title;
                q.quarter_description = desc;
                q.status              = status;
                if (start) q.start_date = start;
                if (end)   q.end_date   = end;
                q.updated_at = new Date().toISOString();
            }
            showToast('Quarter details saved ✓', 'indigo');
            // Re-render so status badge & progress bar update
            const activeQ = document.querySelector('#kpiDetailContent .quarter-tab-active')?.dataset?.q
                          || activeKpi.quarters?.find(x => x.id == quarterId)?.quarter
                          || 'Q1';
            renderKpiDetail(q?.quarter || 'Q1');
        } else {
            alert(data.message || 'Failed to save.');
        }
    } catch(e) {
        alert('Network error.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '💾 Save Quarter Details'; }
    }
}

/* Lightweight toast notification */
function showToast(msg, color = 'emerald') {
    const colors = {
        emerald: 'bg-emerald-600',
        indigo:  'bg-indigo-600',
        red:     'bg-red-600',
    };
    const toast = document.createElement('div');
    toast.className = `fixed bottom-6 right-6 z-[99999] px-5 py-3 rounded-2xl text-white text-sm font-black shadow-xl ${colors[color] || 'bg-slate-800'} transition-all`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 2500);
}

/* Show/hide completion proof section when status changes */
function toggleCompletionProof(status) {
    const section = document.getElementById('completionProofSection');
    const merged  = document.getElementById('mergedActualSection');
    const btn     = document.getElementById('qSaveBtn');
    if (!section || !btn) return;
    if (status === 'completed') {
        section.classList.remove('hidden');
        if (merged) merged.classList.add('hidden');
        btn.innerHTML = '🏆 Submit Completion';
        btn.className = btn.className.replace('from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-indigo-600/20',
            'from-[#6B3F2A] to-[#5a3323] hover:from-[#5a3323] hover:to-[#4a2a1a] shadow-[#6B3F2A]/20');
    } else {
        section.classList.add('hidden');
        if (merged) merged.classList.remove('hidden');
        btn.innerHTML = merged ? '💾 Save Quarter & Actual' : '💾 Save Quarter Details';
        btn.className = btn.className.replace('from-[#6B3F2A] to-[#5a3323] hover:from-[#5a3323] hover:to-[#4a2a1a] shadow-[#6B3F2A]/20',
            'from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 shadow-indigo-600/20');
    }
}

/* Render proof files list (multi-file support, backward-compat with single URL) */
function renderProofFiles(quarter) {
    // Build unified files array: prefer completion_proof_urls, fallback to single URL
    let files = [];
    if (quarter.completion_proof_urls) {
        // Could be a JSON string (from DB) or already an array (from JS state)
        const raw = quarter.completion_proof_urls;
        files = Array.isArray(raw) ? raw : (typeof raw === 'string' ? (() => { try { return JSON.parse(raw); } catch(e) { return []; } })() : []);
    }
    // Backward-compat: if no multi-file list but single URL exists, wrap it
    if (!files.length && quarter.completion_proof_url) {
        const url = quarter.completion_proof_url;
        const isImg = /\.(jpg|jpeg|png|webp|gif)$/i.test(url);
        files = [{ url, name: url.split('/').pop() || 'Proof', type: isImg ? 'image/jpeg' : 'application/pdf' }];
    }
    if (!files.length) return '';

    const canDelete = quarter.status === 'pending_completion';
    const delBtn = (url) => canDelete ? `
        <button type="button" onclick="event.preventDefault(); deleteProofFile('${quarter.id}', '${String(url).replace(/'/g, "\\'")}')"
            title="Delete attachment — cancels this pending completion"
            class="shrink-0 w-7 h-7 rounded-lg bg-red-50 hover:bg-red-100 border border-red-200 text-red-500 hover:text-red-700 flex items-center justify-center text-xs font-black transition">✕</button>` : '';

    const fileCards = files.map((f, i) => {
        const isImg = f.type?.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif)$/i.test(f.url || '');
        const name  = f.name || ('File ' + (i+1));
        if (isImg) {
            return `<div class="rounded-2xl overflow-hidden border-2 border-slate-200 hover:border-[#8B5E4A] transition bg-slate-50">
                <a href="${f.url}" target="_blank" class="block">
                    <img src="${f.url}" alt="${name}" class="w-full max-h-52 object-contain">
                </a>
                <div class="px-3 py-2 border-t border-slate-100 flex items-center gap-2">
                    <span class="text-base">🖼️</span>
                    <a href="${f.url}" target="_blank" class="text-xs font-bold text-slate-600 truncate flex-1">${name}</a>
                    <a href="${f.url}" target="_blank" class="text-[10px] text-[#8B5E4A] font-black shrink-0">Open ↗</a>
                    ${delBtn(f.url)}
                </div>
            </div>`;
        } else {
            return `<div class="flex items-center gap-3 p-3 rounded-2xl border-2 border-slate-200 hover:border-[#8B5E4A] hover:bg-[#FBF5EF] transition group">
                <a href="${f.url}" target="_blank" class="w-10 h-10 rounded-xl bg-red-50 border border-red-200 flex items-center justify-center text-xl shrink-0">📄</a>
                <a href="${f.url}" target="_blank" class="flex-1 min-w-0">
                    <p class="text-sm font-black text-slate-700 truncate">${name}</p>
                    <p class="text-[10px] text-slate-400">PDF Document</p>
                </a>
                <a href="${f.url}" target="_blank" class="text-xs font-black text-[#8B5E4A] shrink-0 group-hover:text-[#5a3323]">Open ↗</a>
                ${delBtn(f.url)}
            </div>`;
        }
    }).join('');

    return `<div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">
            Proof Files <span class="normal-case font-medium text-slate-300">(${files.length} file${files.length > 1 ? 's' : ''})</span>
        </p>
        <div class="space-y-2">${fileCards}</div>
    </div>`;
}

/* Proof files must not exceed 5 MB each — mirrors the server's max:5120 rule */
const MAX_PROOF_FILE_BYTES = 5 * 1024 * 1024;

/* File list preview when files selected — flags oversized files inline and
   disables Save instead of letting an oversized upload hit the server and
   bounce back as a raw validation error. */
document.addEventListener('change', function(e) {
    if (e.target.id !== 'qProofImage') return;
    const files   = Array.from(e.target.files);
    const list    = document.getElementById('proofPreviewList');
    const saveBtn = document.getElementById('qSaveBtn');
    if (!list) return;
    list.innerHTML = '';
    if (!files.length) {
        list.classList.add('hidden');
        if (saveBtn) saveBtn.disabled = false;
        return;
    }
    list.classList.remove('hidden');
    let hasOversized = false;
    files.forEach(file => {
        const isImg     = file.type.startsWith('image/');
        const sizeMb    = (file.size / 1024 / 1024).toFixed(1);
        const oversized = file.size > MAX_PROOF_FILE_BYTES;
        if (oversized) hasOversized = true;

        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 p-2 rounded-xl border ' +
            (oversized ? 'border-red-300 bg-red-50' : 'border-[#6B3F2A]/20 bg-[#FBF5EF]');

        const sizeLabel = oversized
            ? `<p class="text-[10px] text-red-600 font-bold">${sizeMb} MB · Too large — max 5 MB</p>`
            : `<p class="text-[10px] text-slate-400">${sizeMb} MB · ${isImg ? 'Image' : 'PDF'}</p>`;

        if (isImg) {
            const objUrl = URL.createObjectURL(file);
            row.innerHTML = `
                <img src="${objUrl}" class="w-10 h-10 rounded-lg object-cover border border-[#6B3F2A]/30 shrink-0">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-slate-700 truncate">${file.name}</p>
                    ${sizeLabel}
                </div>`;
        } else {
            row.innerHTML = `
                <div class="w-10 h-10 rounded-lg bg-red-100 border border-red-200 flex items-center justify-center text-xl shrink-0">📄</div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-slate-700 truncate">${file.name}</p>
                    ${sizeLabel}
                </div>`;
        }
        list.appendChild(row);
    });

    if (hasOversized) {
        const warn = document.createElement('p');
        warn.className = 'text-[10px] text-red-600 font-bold mt-1';
        warn.textContent = '⚠ Remove the file(s) over 5 MB to continue — they can\'t be saved.';
        list.appendChild(warn);
    }
    if (saveBtn) saveBtn.disabled = hasOversized;
});

/* Delete one completion-proof attachment. Only allowed while pending
   approval — doing so cancels the whole pending completion request, since
   the approver's pending review already points at the exact file set that
   was submitted. */
async function deleteProofFile(quarterId, url) {
    if (!confirm('Delete this attachment? This will cancel the pending completion request so you can resubmit.')) return;

    try {
        const res = await fetch(`/kpi/quarter/${quarterId}/proof-file`, {
            method: 'DELETE',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
            body: JSON.stringify({ url }),
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Failed to delete attachment.', 'red');
            return;
        }

        const q = activeKpi.quarters?.find(x => x.id == quarterId);
        if (q) {
            q.status                   = 'on_track';
            q.completion_review        = null;
            q.completion_proof_url     = null;
            q.completion_proof_urls    = null;
            q.completion_submitted_at  = null;
        }
        showToast('Attachment deleted — pending request cancelled', 'indigo');
        renderKpiDetail(q?.quarter || 'Q1');
    } catch (e) {
        showToast('Network error. Please try again.', 'red');
    }
}

/* Dispatch to the right save function based on selected status */
function quarterSaveDispatch(quarterId) {
    const status = document.getElementById('qStatusInput')?.value;
    if (status === 'completed') {
        completeQuarterSubmit(quarterId);
    } else if (document.getElementById('mergedActualSection')) {
        // Open quarter, no approval needed — one click saves details + actual together
        saveQuarterAndActualCombined(quarterId);
    } else {
        saveQuarterInline(quarterId);
    }
}

/* Combined save: quarter details + actual value in a single user action (no approval needed) */
async function saveQuarterAndActualCombined(quarterId) {
    const title  = document.getElementById('qTitleInput')?.value?.trim()  ?? '';
    const desc   = document.getElementById('qDescInput')?.value            ?? '';
    const status = document.getElementById('qStatusInput')?.value          ?? '';
    const start  = document.getElementById('qStartInput')?.value           ?? '';
    const end    = document.getElementById('qEndInput')?.value             ?? '';
    const actual = document.getElementById('newActual-' + quarterId)?.value ?? '';

    if (start && end && start > end) { alert('Start date cannot be after end date.'); return; }

    const btn = document.getElementById('qSaveBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

    try {
        const detailRes = await fetch(`/kpi/quarter/${quarterId}/inline-update`, {
            method: 'PUT',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
            body: JSON.stringify({ quarter_title: title, quarter_description: desc, status, start_date: start || null, end_date: end || null }),
        });
        const detailData = await detailRes.json();
        if (!detailData.success) {
            alert(detailData.message || 'Failed to save quarter details.');
            return;
        }

        if (actual !== '') {
            const actualRes = await fetch('/kpi/update-quarter', {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
                body: JSON.stringify({ quarter_id: quarterId, quarter_actual: actual }),
            });
            const actualData = await actualRes.json();
            if (!actualData.success) {
                alert(actualData.message || 'Quarter details saved, but failed to save actual value.');
                return;
            }
        }

        showToast('Quarter saved ✓', 'indigo');
        location.reload();
    } catch (e) {
        alert('Network error.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '💾 Save Quarter & Actual'; }
    }
}

/* Submit completion with multiple proof files (multipart) */
async function completeQuarterSubmit(quarterId) {
    const review = document.getElementById('qCompletionReview')?.value?.trim() ?? '';
    const files  = Array.from(document.getElementById('qProofImage')?.files ?? []);
    const btn    = document.getElementById('qSaveBtn');

    if (review.length < 10) {
        alert('Please write a completion review (minimum 10 characters).');
        return;
    }
    if (!files.length) {
        alert('Please upload at least one proof file.');
        return;
    }
    if (files.length > 5) {
        alert('Maximum 5 files allowed.');
        return;
    }
    if (files.some(f => f.size > MAX_PROOF_FILE_BYTES)) {
        // Belt-and-suspenders: the file-selection handler already disables
        // Save for this, but never let an oversized file reach the server
        // and bounce back as a raw validation error either way.
        showToast('Remove the file(s) over 5 MB before submitting.', 'red');
        return;
    }

    if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }

    const fd = new FormData();
    fd.append('_method', 'POST');
    fd.append('completion_review', review);
    files.forEach(f => fd.append('proof_files[]', f));

    try {
        const res = await fetch(`/kpi/quarter/${quarterId}/complete`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: fd,
        });
        const data = await res.json();
        if (data.success) {
            const q = activeKpi.quarters?.find(x => x.id == quarterId);
            if (q) {
                q.status                    = data.status || 'pending_completion';
                q.completion_review         = review;
                q.completion_submitted_at   = new Date().toISOString();
                if (data.proof_url)   q.completion_proof_url  = data.proof_url;
                if (data.proof_files) q.completion_proof_urls = data.proof_files;
            }
            if (data.status === 'completed') {
                showToast('Quarter marked as completed ✓', 'emerald');
            } else {
                showToast('Completion submitted — pending approval ⏳', 'indigo');
            }
            renderKpiDetail(q?.quarter || 'Q1');
        } else {
            alert(data.message || 'Failed to submit completion.');
        }
    } catch(e) {
        alert('Network error. Please try again.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '🏆 Submit Completion'; }
    }
}

</script>

<!-- KPI DETAIL MODAL -->
<div
    id="kpiDetailModal"
    class="hidden fixed inset-0 z-[9999]">

    <!-- BACKDROP -->
    <div
        onclick="closeKpiDetail()"
        class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm">
    </div>

    <!-- PANEL -->
    <div class="absolute right-0 top-0 h-full w-full max-w-[1400px] bg-[#f8fafc] overflow-y-auto shadow-2xl">

        <div id="kpiDetailContent">

            <div class="bg-white rounded-3xl border border-slate-200 p-5">

                <h3 class="text-sm font-black text-slate-900 mb-4">
                    KPI History
                </h3>

                <div class="space-y-4 max-h-[400px] overflow-y-auto">

                    <!-- Dynamic history here -->

                </div>

            </div>

        </div>

    </div>

</div>

<!-- KPI EDIT MODAL -->
<div id="editKpiModal" class="fixed inset-0 z-[9999] hidden items-center justify-center modal-bg p-4">
    <div class="bg-white rounded-[28px] w-full max-w-2xl max-h-[92vh] flex flex-col shadow-2xl overflow-hidden">

        {{-- COLOURED HEADER (gradient set dynamically by JS) --}}
        <div id="editModalHeader" class="rounded-t-[28px] px-6 pt-6 pb-5 text-white shrink-0" style="background: linear-gradient(to right,#1e3a5f,#1e40af)">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5 mb-3">
                        <span id="editModalCatIcon" class="text-base">📌</span>
                        <span id="editModalCatPill" class="px-2.5 py-1 rounded-full bg-white/25 text-[10px] font-black">Category</span>
                        <span id="editModalSubPill" class="px-2.5 py-1 rounded-full bg-white/15 text-[10px] font-black text-white/90">Sub Category</span>
                        <span id="editModalFy"      class="px-2.5 py-1 rounded-full bg-white/15 text-[10px] font-black text-white/80">FY</span>
                        <span id="editModalOwner"   class="px-2.5 py-1 rounded-full bg-white/10 text-[10px] font-black text-white/70">Owner</span>
                    </div>
                    <h2 id="editModalKpiTitle" class="text-xl font-black leading-snug">KPI Title</h2>
                    <p  id="editModalKpiDesc"  class="text-white/65 text-xs mt-1.5 line-clamp-2">Description</p>
                </div>
                <button onclick="closeEditKpiModal()"
                        class="w-9 h-9 rounded-xl bg-white/20 hover:bg-white/30 flex items-center justify-center text-white font-black text-xl shrink-0">×</button>
            </div>

            {{-- STATS ROW --}}
            <div class="grid grid-cols-4 gap-2.5 mt-4">
                <div class="bg-white/12 rounded-2xl px-3 py-2.5">
                    <p class="text-[9px] uppercase text-white/55 font-black">Weightage</p>
                    <p id="editModalWeightage" class="text-lg font-black mt-0.5">0%</p>
                </div>
                <div class="bg-white/12 rounded-2xl px-3 py-2.5">
                    <p class="text-[9px] uppercase text-white/55 font-black">Base Target</p>
                    <p id="editModalBase" class="text-lg font-black mt-0.5">0</p>
                </div>
                <div class="bg-white/12 rounded-2xl px-3 py-2.5">
                    <p class="text-[9px] uppercase text-white/55 font-black">Stretch Target</p>
                    <p id="editModalStretch" class="text-lg font-black mt-0.5">0</p>
                </div>
                <div class="bg-white/12 rounded-2xl px-3 py-2.5">
                    <p class="text-[9px] uppercase text-white/55 font-black">KPI Score</p>
                    <p id="editModalScore" class="text-lg font-black mt-0.5">0%</p>
                </div>
            </div>

            {{-- QUARTER DOTS --}}
            <div class="flex gap-2 mt-3">
                <div id="editQ1" class="w-9 h-9 rounded-xl bg-white/15 text-white flex items-center justify-center text-[10px] font-black">Q1</div>
                <div id="editQ2" class="w-9 h-9 rounded-xl bg-white/15 text-white flex items-center justify-center text-[10px] font-black">Q2</div>
                <div id="editQ3" class="w-9 h-9 rounded-xl bg-white/15 text-white flex items-center justify-center text-[10px] font-black">Q3</div>
                <div id="editQ4" class="w-9 h-9 rounded-xl bg-white/15 text-white flex items-center justify-center text-[10px] font-black">Q4</div>
                <div class="ml-auto self-center">
                    <span id="editModalScoreBadge" class="px-3 py-1 rounded-xl bg-white/20 text-white text-[10px] font-black">—</span>
                </div>
            </div>
        </div>

        {{-- SCROLLABLE BODY --}}
        <div class="overflow-y-auto flex-1 px-6 py-5">
            <form id="editKpiForm" method="POST" action="" class="space-y-5">
            @csrf

            {{-- SECTION: EDIT DETAILS --}}
            <div class="flex items-center gap-2">
                <span id="editAccentBar" class="w-1 h-5 rounded-full bg-indigo-500 shrink-0"></span>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Edit KPI Details</p>
            </div>

            <div>
                <label class="text-xs font-black text-slate-600 uppercase tracking-wide">KPI Title <span class="text-red-500">*</span></label>
                <input id="edit_kpi_title" name="kpi_title"
                       class="w-full border border-slate-200 rounded-2xl px-4 py-3 mt-2 text-sm font-medium text-slate-800 focus:border-indigo-400 transition">
            </div>

            <div>
                <label class="text-xs font-black text-slate-600 uppercase tracking-wide">Description</label>
                <textarea id="edit_kpi_description" name="kpi_description" rows="3"
                          class="w-full border border-slate-200 rounded-2xl px-4 py-3 mt-2 text-sm text-slate-700 resize-none"></textarea>
            </div>

            <div>
                <label class="text-xs font-black text-slate-600 uppercase tracking-wide">KPI Status</label>
                <select id="edit_status" name="status"
                        class="w-full border border-slate-200 rounded-2xl px-4 py-3 mt-2 text-sm text-slate-800">
                    <option value="not_started">Not Started</option>
                    <option value="on_track">On Track</option>
                    <option value="at_risk">At Risk</option>
                    <option value="in_trouble">In Trouble</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            {{-- SECTION: TARGETS --}}
            <div class="border-t border-slate-100 pt-5">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-1 h-5 rounded-full bg-amber-500 shrink-0"></span>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Annual Targets</p>
                    <span class="ml-auto text-[10px] text-amber-700 font-black bg-amber-50 px-2.5 py-1 rounded-xl border border-amber-200">⚠ Requires Approval</span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wide">Base Target</label>
                        <input id="edit_base_target" type="number" oninput="checkTargetChanged()" step="0.01"
                               class="w-full border border-slate-200 rounded-2xl px-4 py-3 mt-2 text-sm font-medium text-slate-800">
                    </div>
                    <div>
                        <label class="text-xs font-black text-slate-600 uppercase tracking-wide">Stretch Target</label>
                        <input id="edit_stretch_target" type="number" oninput="checkTargetChanged()" step="0.01"
                               class="w-full border border-slate-200 rounded-2xl px-4 py-3 mt-2 text-sm font-medium text-slate-800">
                    </div>
                </div>

                <div class="mt-3 rounded-2xl bg-amber-50 border border-amber-200 p-3 flex gap-2 text-xs text-amber-800">
                    <span class="shrink-0">⚠️</span>
                    <span>Changes to <strong>Base / Stretch Target</strong> require manager approval before taking effect. Title, Description and Status update immediately.</span>
                </div>
            </div>

            {{-- REASON --}}
            <div id="targetReasonBox" class="hidden">
                <label class="text-xs font-black text-red-600 uppercase tracking-wide">Reason for Target Change <span class="text-red-500">*</span></label>
                <textarea id="target_change_reason" rows="3"
                          class="w-full border border-red-200 rounded-2xl px-4 py-3 mt-2 text-sm resize-none"
                          placeholder="Explain why the target needs to change. Minimum 30 characters. This will be reviewed by your approver."></textarea>
            </div>

            </form>
        </div>

        {{-- STICKY FOOTER --}}
        <div class="shrink-0 px-6 py-4 border-t border-slate-100 bg-white rounded-b-[28px] flex gap-3">
            <button type="button" onclick="closeEditKpiModal()"
                    class="flex-1 h-12 rounded-2xl border border-slate-200 text-slate-600 font-black text-sm hover:bg-slate-50 transition">
                Cancel
            </button>
            <button type="button" onclick="submitKpiEdit()" id="editSaveBtn"
                    class="flex-1 h-12 rounded-2xl text-white font-black text-sm transition"
                    style="background: linear-gradient(to right,#4f46e5,#6366f1)">
                Save Changes
            </button>
        </div>

    </div>
</div>

<!-- DELETE KPI MODAL -->

<div
    id="deleteKpiModal"
    class="fixed inset-0 z-[99999] hidden items-center justify-center modal-bg p-6"
>

    <div
        class="
            bg-white
            rounded-[28px]
            w-full
            max-w-lg
            p-6
        "
    >

        <input
            type="hidden"
            id="deleteKpiId"
        >

        <div class="flex justify-between items-center">

            <h2 class="text-xl font-black text-red-700">
                Request KPI Deletion
            </h2>

            <button
                onclick="closeDeleteKpiModal()"
                class="text-slate-500"
            >
                ✕
            </button>

        </div>

        <div
            class="
                mt-5
                rounded-xl
                bg-red-50
                border
                border-red-200
                p-4
            "
        >

            <div class="text-xs text-red-500 uppercase font-black">
                KPI
            </div>

            <div
                id="deleteKpiTitle"
                class="
                    mt-2
                    text-sm
                    font-black
                    text-slate-900
                "
            ></div>

        </div>

        <div class="mt-5">

            <label
                class="
                    text-xs
                    font-black
                    uppercase
                    text-red-600
                "
            >
                Reason For Deletion
            </label>

            <textarea
                id="deleteReason"
                rows="4"
                class="
                    w-full
                    border
                    border-red-300
                    rounded-xl
                    px-4
                    py-3
                    mt-2
                "
                placeholder="
Explain why KPI should be deleted.
Minimum 30 characters.
                "
            ></textarea>

        </div>

        <div
            class="
                flex
                justify-end
                gap-3
                mt-6
            "
        >

            <button
                onclick="closeDeleteKpiModal()"
                class="
                    px-5
                    py-3
                    rounded-xl
                    border
                    border-slate-300
                "
            >
                Cancel
            </button>

            <button
                onclick="submitDeleteRequest()"
                class="
                    px-5
                    py-3
                    rounded-xl
                    bg-red-600
                    hover:bg-red-700
                    text-white
                    font-black
                "
            >
                Submit Delete Request
            </button>

        </div>

    </div>

</div>

<!-- ============================================================
     KPI ASSIGNED TO ME — VIEW-ONLY DETAIL MODAL
     ============================================================ -->
<div id="assignedKpiDetailModal"
    class="modal-bg fixed inset-0 hidden z-[99999] items-center justify-center p-4">

    <div class="bg-white w-full max-w-2xl rounded-[2rem] overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">

        <!-- HEADER -->
        <div id="assignedDetailHeader"
            class="px-6 py-5 flex items-center justify-between shrink-0 bg-gradient-to-r from-amber-600 to-yellow-500">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-xl">📋</div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/70">Assigned KPI · View Only</p>
                    <h2 id="assignedDetailTitle" class="font-black text-white text-base mt-0.5">KPI Detail</h2>
                </div>
            </div>
            <button type="button" onclick="closeAssignedKpiDetail()"
                class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white font-black text-sm flex items-center justify-center transition-all">
                ✕
            </button>
        </div>

        <!-- VIEW-ONLY NOTICE -->
        <div class="shrink-0 bg-amber-50 border-b border-amber-100 px-6 py-2.5 flex items-center gap-2">
            <span class="text-amber-500">👁</span>
            <p class="text-xs text-amber-700 font-medium">You are viewing a KPI assigned to you. No changes can be made here.</p>
        </div>

        <!-- SCROLLABLE BODY -->
        <div id="assignedDetailContent" class="overflow-y-auto flex-1 p-6"></div>

    </div>
</div>

<script>
let activeAssignedKpi = null;

function openAssignedKpiDetail(card) {
    const modal   = document.getElementById('assignedKpiDetailModal');
    const content = document.getElementById('assignedDetailContent');
    const title   = document.getElementById('assignedDetailTitle');
    if (!modal || !content || !card) return;

    activeAssignedKpi = JSON.parse(card.dataset.kpi || '{}');
    const kpi = activeAssignedKpi;

    if (title) title.textContent = kpi.kpi_title || 'KPI Detail';

    // update header gradient to match category
    const header = document.getElementById('assignedDetailHeader');
    if (header) {
        const gradMap = {
            'Financial':         'from-amber-600 to-yellow-500',
            'Growth & Customer': 'from-amber-500 to-yellow-400',
            'Initiatives':       'from-amber-500 to-orange-400',
            'People':            'from-amber-600 to-orange-500',
        };
        const grad = gradMap[kpi.category] ?? 'from-amber-600 to-yellow-500';
        header.className = header.className.replace(/from-\S+ to-\S+/, grad);
    }

    renderAssignedKpiDetail('Q1');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

function closeAssignedKpiDetail() {
    const modal = document.getElementById('assignedKpiDetailModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

function renderAssignedKpiDetail(activeQ) {
    const content = document.getElementById('assignedDetailContent');
    const kpi     = activeAssignedKpi;
    if (!kpi || !content) return;

    const quarters = kpi.quarters || [];
    const quarter  = quarters.find(q => q.quarter === activeQ) || {};

    const fmtDate = d => {
        if (!d) return '-';
        const dt = new Date(d);
        return isNaN(dt) ? d : dt.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
    };

    const statusLabel = {
        on_track:    ['On Track',    'bg-emerald-100 text-emerald-700'],
        at_risk:     ['At Risk',     'bg-yellow-100 text-yellow-700'],
        in_trouble:  ['In Trouble',  'bg-red-100 text-red-700'],
        completed:   ['Completed',   'bg-[#F5EAE0] text-[#6B3F2A]'],
        not_started: ['Not Started', 'bg-slate-100 text-slate-600'],
    };

    const [sLabel, sCls] = statusLabel[quarter.status] ?? ['Not Started', 'bg-slate-100 text-slate-600'];

    const target  = parseFloat(quarter.quarter_target || 0);
    const actual  = parseFloat(quarter.quarter_actual || 0);
    const score   = target > 0 ? Number(((actual / target) * 100).toFixed(1)) : 0;

    const scoreColor = score >= 90 ? 'text-emerald-600' : score >= 75 ? 'text-yellow-600' : score >= 50 ? 'text-orange-600' : 'text-red-600';
    const progressBar = score >= 90 ? 'from-emerald-400 to-green-500' : score >= 75 ? 'from-yellow-400 to-yellow-500' : score >= 50 ? 'from-orange-400 to-orange-500' : 'from-red-400 to-red-500';

    // Quarter tabs
    let tabsHtml = '';
    ['Q1','Q2','Q3','Q4'].forEach(q => {
        const active = q === activeQ;
        tabsHtml += `<button onclick="renderAssignedKpiDetail('${q}')"
            class="px-4 py-2 rounded-2xl font-black text-sm transition-all ${active ? 'bg-amber-600 text-white shadow-md shadow-amber-200' : 'bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100'}">
            ${q}
        </button>`;
    });

    content.innerHTML = `
    <div class="space-y-5">

        <!-- KPI INFO -->
        <div class="space-y-2">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-gradient-to-b from-amber-500 to-yellow-400"></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">KPI Information</p>
            </div>
            <div class="rounded-[20px] bg-[#3d2000] p-4">
                <p class="text-[9px] uppercase tracking-widest text-amber-300 font-black">KPI Name</p>
                <p class="font-black text-white text-base mt-1 leading-snug">${kpi.kpi_title || '-'}</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="rounded-2xl bg-amber-50 border border-amber-100 p-3">
                    <p class="text-[9px] uppercase tracking-widest text-amber-600 font-black">Category</p>
                    <p class="font-bold text-slate-900 mt-1 text-sm">${kpi.category || '-'}</p>
                </div>
                <div class="rounded-2xl bg-amber-50 border border-amber-100 p-3">
                    <p class="text-[9px] uppercase tracking-widest text-amber-600 font-black">Sub Category</p>
                    <p class="font-bold text-slate-900 mt-1 text-sm">${kpi.sub_category || '-'}</p>
                </div>
            </div>
            ${kpi.kpi_description ? `
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-3">
                <p class="text-[9px] uppercase tracking-widest text-slate-500 font-black">Description</p>
                <p class="text-sm text-slate-600 mt-1 leading-relaxed">${kpi.kpi_description}</p>
            </div>` : ''}
        </div>

        <!-- TARGETS -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-gradient-to-b from-amber-400 to-orange-400"></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Annual Target</p>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">
                    <p class="text-[9px] uppercase tracking-widest text-amber-600 font-black">Base Target</p>
                    <p class="text-xl font-black text-slate-900 mt-1">${Number(kpi.base_target || 0).toLocaleString()}</p>
                    <p class="text-[9px] text-amber-300 mt-1 font-bold uppercase">Annual</p>
                </div>
                <div class="rounded-2xl bg-yellow-50 border border-yellow-100 p-4">
                    <p class="text-[9px] uppercase tracking-widest text-yellow-600 font-black">Stretch Target</p>
                    <p class="text-xl font-black text-slate-900 mt-1">${Number(kpi.stretch_target || 0).toLocaleString()}</p>
                    <p class="text-[9px] text-yellow-400 mt-1 font-bold uppercase">Annual</p>
                </div>
                <div class="rounded-2xl bg-orange-50 border border-orange-100 p-4">
                    <p class="text-[9px] uppercase tracking-widest text-orange-600 font-black">Weightage</p>
                    <p class="text-xl font-black text-slate-900 mt-1">${kpi.weightage || 0}%</p>
                    <p class="text-[9px] text-orange-300 mt-1 font-bold uppercase">Weight</p>
                </div>
            </div>
        </div>

        <!-- QUARTER BREAKDOWN -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 rounded-full bg-gradient-to-b from-orange-400 to-amber-500"></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Quarter Breakdown</p>
            </div>

            <!-- QUARTER TABS -->
            <div class="flex gap-2 mb-4">${tabsHtml}</div>

            <!-- SELECTED QUARTER CARD -->
            <div class="rounded-[20px] border border-amber-100 bg-gradient-to-br from-amber-50 to-white p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 flex items-center justify-center font-black text-amber-700 text-lg">${activeQ}</div>
                        <div>
                            <p class="font-black text-slate-900">${quarter.quarter_title || activeQ + ' Quarter'}</p>
                            <p class="text-xs text-slate-500 mt-0.5">${fmtDate(quarter.start_date)} — ${fmtDate(quarter.end_date)}</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-xl text-xs font-black ${sCls}">${sLabel}</span>
                </div>

                <div class="mb-4">
                    <div class="flex justify-between text-xs font-black text-slate-500 mb-1">
                        <span>Achievement</span>
                        <span class="${scoreColor}">${score}%</span>
                    </div>
                    <div class="h-3 bg-amber-50 rounded-full overflow-hidden border border-amber-100">
                        <div class="h-3 rounded-full bg-gradient-to-r ${progressBar}" style="width:${Math.max(3,Math.min(score,100))}%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-white border border-amber-100 p-3">
                        <p class="text-[9px] uppercase tracking-widest text-amber-500 font-black">Annual Target</p>
                        <p class="text-lg font-black text-slate-900 mt-1">${Number(kpi.base_target || 0).toLocaleString()}</p>
                    </div>
                    <div class="rounded-2xl bg-white border border-amber-100 p-3">
                        <p class="text-[9px] uppercase tracking-widest text-amber-500 font-black">Actual</p>
                        <p class="text-lg font-black text-slate-900 mt-1">${Number(actual).toLocaleString()}</p>
                    </div>
                </div>

                ${quarter.quarter_description ? `
                <div class="rounded-2xl bg-white border border-amber-100 p-3 mt-3">
                    <p class="text-[9px] uppercase tracking-widest text-amber-500 font-black">Quarter Note</p>
                    <p class="text-sm text-slate-600 mt-1">${quarter.quarter_description}</p>
                </div>` : ''}
            </div>
        </div>

    </div>`;
}

// close on backdrop click
document.getElementById('assignedKpiDetailModal')?.addEventListener('click', function(e){
    if (e.target === this) closeAssignedKpiDetail();
});
</script>

</body>
</html>
