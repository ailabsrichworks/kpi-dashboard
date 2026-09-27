<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $staff['short_name'] ?? $staff['full_name'] ?? 'Staff' }} — KPI Overview</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://ui-avatars.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .line-clamp-2 { display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen text-slate-900">

@include('partials.sidebar')

@php
    $scoreStyle = function($s) {
        $s = (float)$s;
        if ($s <= 25)  return ['bar'=>'bg-red-600',                                     'text'=>'text-red-700',     'badge'=>'bg-red-50 text-red-700 border-red-100',        'label'=>'Critical'];
        if ($s <= 50)  return ['bar'=>'bg-gradient-to-r from-red-600 to-orange-500',    'text'=>'text-orange-700',  'badge'=>'bg-orange-50 text-orange-700 border-orange-100','label'=>'Risk'];
        if ($s <= 75)  return ['bar'=>'bg-gradient-to-r from-orange-500 to-yellow-400', 'text'=>'text-amber-700',   'badge'=>'bg-amber-50 text-amber-700 border-amber-100',  'label'=>'Watch'];
        if ($s <= 100) return ['bar'=>'bg-gradient-to-r from-yellow-400 to-emerald-600','text'=>'text-emerald-700', 'badge'=>'bg-emerald-50 text-emerald-700 border-emerald-100','label'=>'Good'];
        return                 ['bar'=>'bg-emerald-700',                                'text'=>'text-emerald-800', 'badge'=>'bg-emerald-50 text-emerald-800 border-emerald-100','label'=>'Exceeded'];
    };
    $overallStyle = $scoreStyle($weightedScore);

    // Same category order + colour language as "My KPI" so the boss sees a consistent system
    $categoryOrder = ['Financial','Growth & Customer','Initiatives','People'];

    $categoryThemes = [
        'Financial'         => ['headerBg'=>'from-emerald-800 to-emerald-600','icon'=>'💰','catPill'=>'bg-emerald-700 text-white','subPill'=>'bg-emerald-100 text-emerald-700','border'=>'border-l-emerald-500'],
        'Growth & Customer' => ['headerBg'=>'from-indigo-800 to-indigo-600', 'icon'=>'📈','catPill'=>'bg-indigo-700 text-white', 'subPill'=>'bg-indigo-100 text-indigo-700', 'border'=>'border-l-indigo-500'],
        'Initiatives'       => ['headerBg'=>'from-amber-700 to-amber-500',  'icon'=>'🚀','catPill'=>'bg-amber-600 text-white',  'subPill'=>'bg-amber-100 text-amber-700',  'border'=>'border-l-amber-500'],
        'People'            => ['headerBg'=>'from-pink-800 to-pink-600',   'icon'=>'👥','catPill'=>'bg-pink-700 text-white',   'subPill'=>'bg-pink-100 text-pink-700',   'border'=>'border-l-pink-500'],
    ];
    $categoryThemeDefault = ['headerBg'=>'from-slate-700 to-slate-600','icon'=>'📌','catPill'=>'bg-slate-600 text-white','subPill'=>'bg-slate-100 text-slate-600','border'=>'border-l-slate-400'];

    // Group by category (defined order first, unknown categories after), then by sub_category
    $kpiCollection = collect($kpis);
    $grouped = $kpiCollection->groupBy(fn($k) => $k['category'] ?: 'Uncategorized');

    $orderedCategories = collect($categoryOrder)->filter(fn($c) => $grouped->has($c));
    $grouped->keys()->reject(fn($c) => in_array($c, $categoryOrder))->sort()->each(fn($c) => $orderedCategories->push($c));
@endphp

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300">
<div class="px-4 pt-4 pb-10">

    {{-- Breadcrumb / back --}}
    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-400 hover:text-[#6B9080] transition mb-3">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back to Dashboard
    </a>

    {{-- Staff header card --}}
    <div class="bg-white rounded-2xl overflow-hidden soft-card border border-[#6B9080] mb-4">
        <div class="h-1 theme-header-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019]"></div>
        <div class="p-5 flex flex-wrap items-center gap-5">
            <div class="w-14 h-14 rounded-full overflow-hidden bg-slate-200 shrink-0">
                <img src="https://ui-avatars.com/api/?name={{ urlencode($staff['short_name'] ?? $staff['full_name'] ?? 'U') }}&background=1a3d34&color=fff&size=56" class="w-full h-full"/>
            </div>
            <div class="flex-1 min-w-[200px]">
                <h1 class="text-lg font-black text-slate-900">{{ $staff['full_name'] ?? $staff['short_name'] ?? 'Unknown' }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">{{ $staff['position'] ?? $staff['role'] ?? '-' }} · {{ $departmentName }} · {{ $currentFinancialYear }}</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 text-[10px] font-black border border-indigo-100">{{ strtoupper($staff['role'] ?? '-') }}</span>
                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-black">{{ count($kpis) }} KPIs</span>
                </div>
            </div>
            <div class="text-center px-6 py-3 rounded-2xl {{ $overallStyle['badge'] }} border">
                <p class="text-[9px] font-black uppercase tracking-widest opacity-70">Overall Score</p>
                <p class="text-2xl font-black {{ $overallStyle['text'] }}">{{ number_format($weightedScore, 1) }}%</p>
                <p class="text-[9px] font-bold opacity-60 mt-0.5">{{ $totalWeight }}% weightage assigned</p>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    @if(count($kpis) > 0)
        <div class="flex items-center flex-wrap gap-2 mb-4">
            @foreach($orderedCategories as $cat)
                @php $lstyle = $categoryThemes[$cat] ?? $categoryThemeDefault; @endphp
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black {{ $lstyle['catPill'] }}">
                    {{ $lstyle['icon'] }} {{ $cat }}
                </span>
            @endforeach
        </div>
    @endif

    {{-- KPI list — grouped by category, then sub-category --}}
    @if(count($kpis) === 0)
        <div class="bg-white rounded-2xl border border-[#6B9080] soft-card p-10 text-center">
            <p class="text-sm font-black text-slate-400">No KPIs found for this staff in {{ $currentFinancialYear }}.</p>
        </div>
    @else
        <div class="space-y-6">
        @foreach($orderedCategories as $cat)
            @php
                $ctheme    = $categoryThemes[$cat] ?? $categoryThemeDefault;
                $catKpis   = $grouped->get($cat);
                $subGroups = $catKpis->groupBy(fn($k) => $k['sub_category'] ?: 'General')->sortKeys();
            @endphp

            <div class="rounded-2xl overflow-hidden soft-card border border-[#6B9080]">
                {{-- Category header --}}
                <div class="px-4 py-2.5 bg-gradient-to-r {{ $ctheme['headerBg'] }} flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">{{ $ctheme['icon'] }}</span>
                        <h2 class="text-xs font-black text-white uppercase tracking-wide">{{ $cat }}</h2>
                    </div>
                    <span class="text-[9px] font-black text-white/80">{{ $catKpis->count() }} KPI{{ $catKpis->count() === 1 ? '' : 's' }}</span>
                </div>

                <div class="bg-white p-4 space-y-5">
                    @foreach($subGroups as $subCat => $subKpis)
                        <div>
                            {{-- Sub-category label --}}
                            <div class="flex items-center gap-2 mb-2.5">
                                <span class="px-2 py-0.5 rounded-lg text-[9px] font-black {{ $ctheme['subPill'] }}">{{ $subCat }}</span>
                                <span class="text-[9px] text-slate-400 font-bold">{{ $subKpis->count() }} KPI{{ $subKpis->count() === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="space-y-2">
                                @foreach($subKpis as $kpi)
                                    @php $kstyle = $scoreStyle($kpi['progress_pct']); @endphp
                                    <a href="{{ route('dashboard.staff.kpi.detail', [$staff['id'], $kpi['id']]) }}"
                                       class="block rounded-xl border {{ $ctheme['border'] }} border-l-4 border-slate-100 hover:bg-slate-50/70 hover:shadow-sm transition-all p-3">

                                        <div class="flex items-start justify-between gap-3 mb-2.5">
                                            <h3 class="text-xs font-black text-slate-900 leading-snug flex-1">{{ $kpi['kpi_title'] ?? 'Untitled KPI' }}</h3>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[9px] font-black">W {{ number_format($kpi['weightage'] ?? 0, 1) }}%</span>
                                                <span class="px-2 py-0.5 rounded-lg text-[9px] font-black {{ $kstyle['badge'] }} border">{{ $kstyle['label'] }}</span>
                                            </div>
                                        </div>

                                        {{-- Overall progress --}}
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="text-[9px] font-black text-slate-400 uppercase shrink-0">Overall</span>
                                            <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-1.5 rounded-full {{ $kstyle['bar'] }}" style="width:{{ min($kpi['progress_pct'],100) }}%"></div>
                                            </div>
                                            <span class="text-[10px] font-black {{ $kstyle['text'] }} shrink-0 w-10 text-right">{{ number_format($kpi['progress_pct'], 1) }}%</span>
                                            <span class="text-[9px] text-slate-400 shrink-0">({{ number_format($kpi['display_actual'],1) }} / {{ number_format($kpi['display_target'],1) }})</span>
                                        </div>

                                        {{-- Per-quarter strip, with each quarter's own title --}}
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                            @foreach($kpi['quarters'] as $q)
                                                @php $qstyle = $scoreStyle($q['progress_pct']); @endphp
                                                <div class="rounded-lg bg-slate-50 border border-slate-100 px-2 py-1.5">
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-[9px] font-black text-slate-500">{{ $q['label'] }}</span>
                                                        @if($q['has_data'])
                                                            <span class="text-[9px] font-black {{ $qstyle['text'] }}">{{ number_format($q['progress_pct'],1) }}%</span>
                                                        @else
                                                            <span class="text-[9px] font-bold text-slate-300">—</span>
                                                        @endif
                                                    </div>
                                                    @if($q['quarter_title'])
                                                        <p class="text-[9px] text-slate-500 truncate mb-1" title="{{ $q['quarter_title'] }}">{{ $q['quarter_title'] }}</p>
                                                    @endif
                                                    @if($q['has_data'])
                                                        <div class="h-1 bg-slate-200 rounded-full overflow-hidden mb-1">
                                                            <div class="h-1 rounded-full {{ $qstyle['bar'] }}" style="width:{{ min($q['progress_pct'],100) }}%"></div>
                                                        </div>
                                                        <p class="text-[8px] text-slate-400">{{ number_format($q['actual'],1) }} / {{ number_format($q['target'],1) }}</p>
                                                    @else
                                                        <p class="text-[8px] text-slate-300 italic">Not planned</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        </div>
    @endif

</div>
</main>
</body>
</html>
