<!DOCTYPE html>
<html>
<head>
    <title>Manage Weightage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        .glass { background: rgba(255,255,255,.9); backdrop-filter: blur(14px); }
        .weightage-input::-webkit-outer-spin-button,
        .weightage-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .weightage-input { -moz-appearance: textfield; }
    </style>
</head>

<body class="min-h-screen bg-[#f4f7fb] pb-28">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300">
<div class="p-6 space-y-6">

    <!-- APPROVAL FLOW GUIDE -->
    @php
        $approverName = $approver['short_name'] ?? null;
        $approverRole = $approver['role'] ?? null;
    @endphp

    <div class="glass rounded-2xl border border-indigo-100 shadow-sm">
        <button type="button"
                onclick="document.getElementById('weightageGuideBody').classList.toggle('hidden'); document.getElementById('weightageGuideChevron').classList.toggle('rotate-180')"
                class="w-full flex items-center justify-between gap-2 px-4 py-2.5 text-left">
            <p class="text-[11px] font-black uppercase text-indigo-500">How Weightage Works</p>
            <svg id="weightageGuideChevron" class="w-3.5 h-3.5 text-slate-400 transition-transform shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>

        <div id="weightageGuideBody" class="hidden px-4 pb-3.5">
            <div class="flex flex-col xl:flex-row xl:items-start gap-3">

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5">

                        <div class="flex items-center gap-1.5 bg-slate-100 rounded-lg px-2 py-1.5">
                            <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[9px] font-black flex items-center justify-center shrink-0">1</span>
                            <div>
                                <p class="text-[11px] font-black text-slate-800 leading-tight">Set Weightage</p>
                                <p class="text-[9px] text-slate-500 leading-tight">Assign % to each KPI</p>
                            </div>
                        </div>

                        <span class="text-slate-400 font-black text-xs">→</span>

                        <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5">
                            <span class="w-5 h-5 rounded-full bg-amber-500 text-white text-[9px] font-black flex items-center justify-center shrink-0">2</span>
                            <div>
                                <p class="text-[11px] font-black text-slate-800 leading-tight">Submit for Approval</p>
                                <p class="text-[9px] text-slate-500 leading-tight">If changing existing %</p>
                            </div>
                        </div>

                        <span class="text-slate-400 font-black text-xs">→</span>

                        <div class="flex items-center gap-1.5 bg-[#FBF5EF] border border-[#6B3F2A]/30 rounded-lg px-2 py-1.5">
                            <span class="w-5 h-5 rounded-full bg-[#6B3F2A] text-white text-[9px] font-black flex items-center justify-center shrink-0">3</span>
                            <div>
                                <p class="text-[11px] font-black text-slate-800 leading-tight">Boss Reviews</p>
                                <p class="text-[9px] text-slate-500 leading-tight">Approves or Rejects</p>
                            </div>
                        </div>

                        <span class="text-slate-400 font-black text-xs">→</span>

                        <div class="flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 rounded-lg px-2 py-1.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[9px] font-black flex items-center justify-center shrink-0">4</span>
                            <div>
                                <p class="text-[11px] font-black text-slate-800 leading-tight">Applied</p>
                                <p class="text-[9px] text-slate-500 leading-tight">Weightage updated</p>
                            </div>
                        </div>

                    </div>

                    <div class="mt-2 text-[10px] text-slate-600 space-y-0.5">
                        <p><span class="font-black text-emerald-700">New allocation</span> (0% → any%) — saves directly, no approval needed.</p>
                        <p><span class="font-black text-amber-700">Changing existing</span> (e.g. 30% → 25%) — requires boss approval with a reason.</p>
                    </div>
                </div>

                <div class="xl:w-[200px] shrink-0">
                    <div class="rounded-xl border {{ $approverName ? 'border-[#6B3F2A]/30 bg-[#FBF5EF]' : 'border-slate-200 bg-slate-50' }} px-3 py-2.5">
                        <p class="text-[9px] uppercase font-black {{ $approverName ? 'text-[#8B5E4A]' : 'text-slate-400' }}">Your Approver</p>
                        @if($approverName)
                            <p class="text-sm font-black text-slate-900 mt-0.5">{{ $approverName }}</p>
                            <p class="text-[10px] text-slate-500">{{ $approverRole }}</p>
                            <p class="text-[9px] text-[#6B3F2A] mt-1.5">Approval requests go to this person</p>
                        @else
                            <p class="text-xs font-black text-slate-500 mt-0.5">No approver set</p>
                            <p class="text-[9px] text-slate-400 mt-0.5">Contact your administrator</p>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    @php
        $currentEmployeeId = (string)($user['id'] ?? '');

        $myKpis = collect($kpis ?? [])->filter(function($item) use ($currentEmployeeId) {
            return (string)($item['employee_id'] ?? '') === $currentEmployeeId;
        });

        $calculateScore = function($item) {
            $quarters    = collect($item['quarters'] ?? []);
            $baseTotal   = 0;
            $actualTotal = 0;
            foreach (['Q1','Q2','Q3','Q4'] as $q) {
                $row          = $quarters->firstWhere('quarter', $q) ?? [];
                $baseTotal   += (float)($row['quarter_target'] ?? 0);
                $actualTotal += (float)($row['quarter_actual'] ?? 0);
            }
            if ($baseTotal <= 0) {
                $baseTotal   = (float)($item['base_target'] ?? 0);
                $actualTotal = (float)($item['actual_value'] ?? 0);
            }
            return $baseTotal > 0 ? round(($actualTotal / $baseTotal) * 100, 2) : 0;
        };

        $weightageTotal = round($myKpis->sum(fn($i) => (float)($i['weightage'] ?? 0)), 2);
        $remaining      = round(100 - $weightageTotal, 2);

        $individualPerformance = round($myKpis->sum(function($item) use ($calculateScore) {
            return ($calculateScore($item) * (float)($item['weightage'] ?? 0)) / 100;
        }), 2);
    @endphp

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-sm">
            <p class="text-xs uppercase font-black text-slate-400">My KPI</p>
            <h2 class="text-2xl font-black text-slate-900 mt-2">{{ $myKpis->count() }}</h2>
        </div>

        <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-sm">
            <p class="text-xs uppercase font-black text-slate-400">Total Weightage</p>
            <h2 id="weightageTotalText"
                class="text-2xl font-black mt-2
                {{ $weightageTotal > 100 ? 'text-red-700' : ($weightageTotal == 100 ? 'text-emerald-700' : 'text-amber-700') }}">
                {{ number_format($weightageTotal, 2) }}%
            </h2>
            <div class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div id="weightageBar"
                     class="h-full rounded-full transition-all {{ $weightageTotal > 100 ? 'bg-red-500' : ($weightageTotal == 100 ? 'bg-emerald-500' : 'bg-amber-400') }}"
                     style="width: {{ min(100, $weightageTotal) }}%"></div>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-sm">
            <p class="text-xs uppercase font-black text-slate-400">Remaining</p>
            <h2 id="weightageRemainingText"
                class="text-2xl font-black mt-2
                {{ $remaining < 0 ? 'text-red-700' : ($remaining == 0 ? 'text-emerald-700' : 'text-amber-700') }}">
                {{ number_format($remaining, 2) }}%
            </h2>
        </div>

        <div class="rounded-2xl bg-white p-4 border border-slate-200 shadow-sm">
            <p class="text-xs uppercase font-black text-slate-400">Individual Performance</p>
            <h2 class="text-2xl font-black text-[#6B3F2A] mt-2">{{ number_format($individualPerformance, 2) }}%</h2>
        </div>

    </div>

    <!-- QUICK ACTIONS -->
    <div class="glass rounded-2xl p-4 border border-white/70 flex flex-wrap gap-3 items-center">
        <button type="button" onclick="balanceEmptyWeightage()"
            class="bg-amber-100 hover:bg-amber-200 text-amber-800 px-5 py-3 rounded-xl text-sm font-black">
            Balance Empty
        </button>
        <button type="button" onclick="confirmEqualizeWeightage()"
            class="bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 rounded-xl text-sm font-black">
            Equalize All
        </button>
        <div class="ml-auto text-[11px] text-slate-500 text-right">
            <span id="totalStatus" class="font-black">
                @if($weightageTotal == 100) Total = 100% ✓
                @elseif($weightageTotal > 100) Over by {{ number_format($weightageTotal - 100, 2) }}%
                @else {{ number_format(100 - $weightageTotal, 2) }}% left to allocate
                @endif
            </span>
        </div>
    </div>

    <!-- KPI LIST -->
    <div class="space-y-5">

        @php
            $categoryOrder  = ['Financial','Growth & Customer','Initiatives','People'];
            $categoryStyles = [
                'Financial'         => ['bg' => 'bg-emerald-700 text-white', 'sub' => 'bg-emerald-50 text-emerald-800 border border-emerald-200'],
                'Growth & Customer' => ['bg' => 'bg-indigo-700 text-white',  'sub' => 'bg-indigo-50 text-indigo-800 border border-indigo-200'],
                'Initiatives'       => ['bg' => 'bg-amber-600 text-white',   'sub' => 'bg-amber-50 text-amber-800 border border-amber-200'],
                'People'            => ['bg' => 'bg-pink-700 text-white',    'sub' => 'bg-pink-50 text-pink-800 border border-pink-200'],
                'Default'           => ['bg' => 'bg-slate-700 text-white',   'sub' => 'bg-slate-50 text-slate-800 border border-slate-200'],
            ];
            $groupedKpis = collect();
            foreach ($categoryOrder as $cat) {
                $items = $myKpis->where('category', $cat);
                if ($items->count()) $groupedKpis[$cat] = $items;
            }
            $pendingMap = $pendingWeightageRequests ?? [];
        @endphp

        @foreach($groupedKpis as $category => $categoryKpis)

            @php
                $categoryWeightage = round($categoryKpis->sum(fn($i) => (float)($i['weightage'] ?? 0)), 2);
                $style = $categoryStyles[$category] ?? $categoryStyles['Default'];
            @endphp

            <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm">

                <!-- CATEGORY HEADER -->
                <div class="px-4 py-3 {{ $style['bg'] }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-black tracking-wide uppercase">{{ $category ?: 'General' }}</h2>
                            <p class="text-xs text-white/70 mt-0.5">{{ count($categoryKpis) }} KPI</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] uppercase text-white/70 font-black">Category Weightage</p>
                            <h3 class="text-lg font-black text-white mt-0.5 category-total"
                                data-category="{{ Str::slug($category) }}">
                                {{ number_format($categoryWeightage, 2) }}%
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- KPI ROWS -->
                <div class="divide-y divide-slate-100">

                    @foreach($categoryKpis as $kpi)

                        @php
                            $kpiId      = $kpi['id'];
                            $isPending  = isset($pendingMap[$kpiId]);
                            $pendingReq = $pendingMap[$kpiId] ?? null;
                            $currentWt  = (float)($kpi['weightage'] ?? 0);
                            $isNew      = $currentWt <= 0;
                        @endphp

                        <div class="p-4 {{ $isPending ? 'bg-amber-50' : '' }}">
                            <div class="flex flex-col xl:flex-row xl:items-start gap-4">

                                <!-- LEFT: KPI INFO -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase {{ $style['sub'] }}">
                                            {{ $kpi['sub_category'] ?? '-' }}
                                        </span>
                                        @if($isNew)
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black bg-emerald-100 text-emerald-700">
                                                New Allocation
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black bg-slate-100 text-slate-600">
                                                Current: {{ number_format($currentWt, 2) }}%
                                            </span>
                                        @endif
                                        @if($isPending)
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black bg-amber-200 text-amber-800">
                                                ⏳ Pending Approval
                                            </span>
                                        @endif
                                    </div>

                                    <h3 class="text-sm font-bold text-slate-900 leading-snug">{{ $kpi['kpi_title'] }}</h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2">{{ $kpi['kpi_description'] ?? '' }}</p>

                                    @if($isPending && $pendingReq)
                                        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px]">
                                            <p class="font-black text-amber-700 mb-1">Pending Approval Request</p>
                                            <div class="flex gap-4 text-slate-600">
                                                <span>From: <b>{{ number_format((float)($pendingReq['old_weightage'] ?? 0), 2) }}%</b></span>
                                                <span>→</span>
                                                <span>To: <b class="text-amber-700">{{ number_format((float)($pendingReq['new_weightage'] ?? 0), 2) }}%</b></span>
                                            </div>
                                            <p class="text-slate-500 mt-1">
                                                Submitted {{ \Carbon\Carbon::parse($pendingReq['created_at'])->diffForHumans() }}
                                                @if($approverName) · Waiting for <b>{{ $approverName }}</b> @endif
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                <!-- RIGHT: INPUT + STATUS PILL -->
                                <div class="w-full xl:w-[200px] shrink-0" data-kpi-wrapper>

                                    <label class="text-[10px] uppercase font-black text-slate-400">Weightage %</label>

                                    <input
                                        type="number"
                                        name="weightages[{{ $kpiId }}]"
                                        min="0" max="100" step="0.01"
                                        value="{{ number_format($currentWt, 2, '.', '') }}"
                                        data-original="{{ number_format($currentWt, 2, '.', '') }}"
                                        data-kpi-id="{{ $kpiId }}"
                                        data-kpi-title="{{ addslashes($kpi['kpi_title']) }}"
                                        data-category="{{ Str::slug($category) }}"
                                        class="weightage-input w-full mt-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-black text-slate-900 transition focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400
                                            {{ $isPending ? 'opacity-60 cursor-not-allowed' : '' }}"
                                        oninput="recalculateWeightage(this)"
                                        onkeydown="return event.key !== '-'"
                                        {{ $isPending ? 'disabled' : '' }}>

                                    <!-- STATUS PILL — updated live by JS -->
                                    <div data-status-wrapper
                                         class="mt-2 px-3 py-2.5 rounded-xl border transition-colors
                                            {{ $isPending ? 'bg-amber-50 border-amber-200' : 'bg-slate-50 border-slate-100' }}">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full shrink-0 status-dot
                                                {{ $isPending ? 'bg-amber-400' : 'bg-slate-300' }}"></div>
                                            <div class="min-w-0">
                                                <p class="save-status text-[11px] font-bold truncate
                                                    {{ $isPending ? 'text-amber-700' : 'text-slate-500' }}">
                                                    {{ $isPending ? 'Pending Approval' : ($isNew ? 'New Allocation' : 'Synced') }}
                                                </p>
                                                <p class="save-hint text-[10px] text-slate-400 truncate">
                                                    {{ $isPending ? 'Locked — awaiting decision' : ($isNew ? 'Will save immediately' : 'Edit to change') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>

                    @endforeach

                </div>

            </div>

        @endforeach

    </div>

</div>
</main>

<!-- ================================================================ -->
<!-- STICKY BOTTOM BAR                                                  -->
<!-- ================================================================ -->
<div id="saveAllBar"
     class="fixed bottom-0 left-[230px] right-0 z-50 bg-white/95 backdrop-blur-sm border-t border-slate-200 px-6 py-4 shadow-2xl">
    <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">

        <div id="changesSummary">
            <p class="text-sm font-black text-slate-500">No changes yet</p>
            <p class="text-[11px] text-slate-400">Edit weightages above, then save here</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" onclick="resetAllChanges()" id="resetBtn"
                class="hidden px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-black text-slate-600 hover:bg-slate-50 transition">
                Reset
            </button>
            <button type="button" onclick="saveAllWeightages()" id="saveAllBtn"
                disabled
                class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-sm font-black transition">
                Save All Weightages
            </button>
        </div>

    </div>
</div>

<!-- ================================================================ -->
<!-- SAVE REVIEW MODAL                                                  -->
<!-- ================================================================ -->
<div id="saveReviewModal" style="display:none"
     class="fixed inset-0 z-[99999] bg-black/60 flex items-start justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl w-full max-w-2xl my-6 shadow-2xl">

        <div class="bg-gradient-to-r from-indigo-700 to-[#5a3323] rounded-t-3xl px-6 py-5 text-white">
            <h2 class="text-xl font-black">Review All Changes</h2>
            <p class="text-white/70 text-sm mt-1">Check everything before confirming — you cannot undo this</p>
        </div>

        <div id="saveReviewContent" class="p-6 space-y-5">
            <!-- filled by JS -->
        </div>

    </div>
</div>

<script>

/* ------------------------------------------------------------------ */
/*  STATE                                                               */
/* ------------------------------------------------------------------ */
var _directItems   = [];   // [{kpiId, value}]
var _approvalItems = [];   // [{kpiId, title, oldVal, newVal}]

/* ------------------------------------------------------------------ */
/*  HELPERS                                                             */
/* ------------------------------------------------------------------ */
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ------------------------------------------------------------------ */
/*  RECALCULATE                                                         */
/* ------------------------------------------------------------------ */
function recalculateWeightage(changedInput) {
    var inputs = Array.from(document.querySelectorAll('.weightage-input:not([disabled])'));
    var total  = 0;
    inputs.forEach(function(i) { total += parseFloat(i.value || 0); });
    total = Math.round(total * 100) / 100;

    var remaining = Math.max(0, 100 - total);
    var totalEl   = document.getElementById('weightageTotalText');
    var remainEl  = document.getElementById('weightageRemainingText');
    var barEl     = document.getElementById('weightageBar');
    var statusEl  = document.getElementById('totalStatus');

    totalEl.innerText  = total.toFixed(2) + '%';
    remainEl.innerText = remaining.toFixed(2) + '%';

    if (total > 100) {
        totalEl.className  = 'text-2xl font-black mt-2 text-red-700';
        remainEl.className = 'text-2xl font-black mt-2 text-red-700';
        barEl.className    = 'h-full rounded-full transition-all bg-red-500';
        barEl.style.width  = '100%';
        statusEl.innerText = 'Over by ' + (total - 100).toFixed(2) + '%';
    } else if (total === 100) {
        totalEl.className  = 'text-2xl font-black mt-2 text-emerald-700';
        remainEl.className = 'text-2xl font-black mt-2 text-emerald-700';
        barEl.className    = 'h-full rounded-full transition-all bg-emerald-500';
        barEl.style.width  = '100%';
        statusEl.innerText = 'Total = 100% ✓';
    } else {
        totalEl.className  = 'text-2xl font-black mt-2 text-amber-700';
        remainEl.className = 'text-2xl font-black mt-2 text-amber-700';
        barEl.className    = 'h-full rounded-full transition-all bg-amber-400';
        barEl.style.width  = total + '%';
        statusEl.innerText = remaining.toFixed(2) + '% left to allocate';
    }

    // Category totals
    var catTotals = {};
    inputs.forEach(function(i) {
        var c = i.dataset.category;
        catTotals[c] = (catTotals[c] || 0) + parseFloat(i.value || 0);
    });
    document.querySelectorAll('.category-total').forEach(function(el) {
        el.innerText = (catTotals[el.dataset.category] || 0).toFixed(2) + '%';
    });

    // Per-KPI status pills
    var directCount   = 0;
    var approvalCount = 0;

    inputs.forEach(function(input) {
        var original  = parseFloat(input.dataset.original || 0);
        var value     = parseFloat(input.value || 0);
        var changed   = Math.abs(value - original) > 0.001;
        var isNew     = original <= 0;
        var wrapper   = input.closest('[data-kpi-wrapper]');
        var statusEl2 = wrapper.querySelector('.save-status');
        var hintEl    = wrapper.querySelector('.save-hint');
        var dotEl     = wrapper.querySelector('.status-dot');
        var pillDiv   = wrapper.querySelector('[data-status-wrapper]');

        if (total > 100) {
            statusEl2.innerText = 'Over 100% — Fix First';
            statusEl2.className = 'save-status text-[11px] font-bold text-red-600 truncate';
            hintEl.innerText    = 'Reduce total below 100%';
            dotEl.className     = 'w-2 h-2 rounded-full bg-red-400 status-dot shrink-0';
            pillDiv.className   = 'mt-2 px-3 py-2.5 rounded-xl border transition-colors bg-red-50 border-red-200';
        } else if (changed && !isNew) {
            statusEl2.innerText = 'Needs Approval';
            statusEl2.className = 'save-status text-[11px] font-bold text-amber-700 truncate';
            hintEl.innerText    = original.toFixed(2) + '% → ' + value.toFixed(2) + '%';
            dotEl.className     = 'w-2 h-2 rounded-full bg-amber-500 status-dot shrink-0';
            pillDiv.className   = 'mt-2 px-3 py-2.5 rounded-xl border transition-colors bg-amber-50 border-amber-200';
            approvalCount++;
        } else if (changed && isNew) {
            statusEl2.innerText = 'Ready to Save';
            statusEl2.className = 'save-status text-[11px] font-bold text-indigo-700 truncate';
            hintEl.innerText    = '0% → ' + value.toFixed(2) + '%';
            dotEl.className     = 'w-2 h-2 rounded-full bg-indigo-500 status-dot shrink-0';
            pillDiv.className   = 'mt-2 px-3 py-2.5 rounded-xl border transition-colors bg-indigo-50 border-indigo-200';
            directCount++;
        } else {
            statusEl2.innerText = isNew ? 'New Allocation' : 'Synced';
            statusEl2.className = 'save-status text-[11px] font-bold text-slate-500 truncate';
            hintEl.innerText    = isNew ? 'Will save immediately' : 'No changes';
            dotEl.className     = 'w-2 h-2 rounded-full bg-slate-300 status-dot shrink-0';
            pillDiv.className   = 'mt-2 px-3 py-2.5 rounded-xl border transition-colors bg-slate-50 border-slate-100';
        }
    });

    updateBottomBar(total, directCount, approvalCount);
}

/* ------------------------------------------------------------------ */
/*  BOTTOM BAR                                                          */
/* ------------------------------------------------------------------ */
function updateBottomBar(total, directCount, approvalCount) {
    var saveBtn     = document.getElementById('saveAllBtn');
    var resetBtn    = document.getElementById('resetBtn');
    var summaryEl   = document.getElementById('changesSummary');
    var totalChange = directCount + approvalCount;

    if (total > 100) {
        saveBtn.disabled  = true;
        saveBtn.innerText = 'Total Over 100%';
        saveBtn.className = 'px-6 py-3 bg-red-400 text-white rounded-xl text-sm font-black cursor-not-allowed';
        summaryEl.innerHTML =
            '<p class="text-sm font-black text-red-700">Total exceeds 100% — fix before saving</p>' +
            '<p class="text-[11px] text-red-400">Reduce some weightages first</p>';
        resetBtn.classList.add('hidden');
        return;
    }

    saveBtn.className = 'px-6 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-sm font-black transition';
    saveBtn.innerText = 'Save All Weightages';

    if (totalChange === 0) {
        saveBtn.disabled = true;
        resetBtn.classList.add('hidden');
        summaryEl.innerHTML =
            '<p class="text-sm font-black text-slate-500">No changes yet</p>' +
            '<p class="text-[11px] text-slate-400">Edit weightages above, then save here</p>';
    } else {
        saveBtn.disabled = false;
        resetBtn.classList.remove('hidden');

        var parts = [];
        if (directCount > 0)   parts.push('<span class="text-indigo-700 font-black">' + directCount + ' save directly</span>');
        if (approvalCount > 0) parts.push('<span class="text-amber-700 font-black">' + approvalCount + ' need boss approval</span>');

        summaryEl.innerHTML =
            '<p class="text-sm font-black text-slate-800">' + totalChange + ' change' + (totalChange !== 1 ? 's' : '') + ' ready</p>' +
            '<p class="text-[11px] text-slate-500">' + parts.join(' &nbsp;·&nbsp; ') + '</p>';
    }
}

/* ------------------------------------------------------------------ */
/*  SAVE ALL — GATHER & OPEN REVIEW MODAL                               */
/* ------------------------------------------------------------------ */
function saveAllWeightages() {
    var inputs = Array.from(document.querySelectorAll('.weightage-input:not([disabled])'));
    _directItems   = [];
    _approvalItems = [];

    inputs.forEach(function(input) {
        var original = parseFloat(input.dataset.original || 0);
        var value    = parseFloat(input.value || 0);
        if (Math.abs(value - original) <= 0.001) return;

        if (original <= 0) {
            _directItems.push({ kpiId: input.dataset.kpiId, value: value });
        } else {
            _approvalItems.push({
                kpiId  : input.dataset.kpiId,
                title  : input.dataset.kpiTitle || input.dataset.kpiId,
                oldVal : original,
                newVal : value,
            });
        }
    });

    if (_directItems.length === 0 && _approvalItems.length === 0) return;

    buildSaveReviewModal();
    document.getElementById('saveReviewModal').style.display = 'flex';
}

/* ------------------------------------------------------------------ */
/*  BUILD MODAL CONTENT                                                 */
/* ------------------------------------------------------------------ */
function buildSaveReviewModal() {
    var approverName = '{{ addslashes($approverName ?? "") }}';
    var approverRole = '{{ addslashes($approverRole ?? "") }}';
    var html = '';

    /* ---- Direct saves (green) ---- */
    if (_directItems.length > 0) {
        html += '<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">';
        html +=   '<div class="flex items-center gap-3 mb-3">';
        html +=     '<div class="w-8 h-8 rounded-full bg-emerald-600 text-white text-sm font-black flex items-center justify-center shrink-0">✓</div>';
        html +=     '<div>';
        html +=       '<p class="text-sm font-black text-emerald-800">' + _directItems.length + ' KPI will save immediately</p>';
        html +=       '<p class="text-[10px] text-emerald-600 mt-0.5">New allocations — no approval needed</p>';
        html +=     '</div>';
        html +=   '</div>';
        html +=   '<div class="space-y-1.5">';
        _directItems.forEach(function(item) {
            var input = document.querySelector('[data-kpi-id="' + item.kpiId + '"]');
            var title = input ? input.dataset.kpiTitle : item.kpiId;
            html += '<div class="flex items-center justify-between bg-white rounded-xl px-3 py-2.5 border border-emerald-100">';
            html +=   '<span class="text-xs font-bold text-slate-700 truncate mr-3">' + escapeHtml(title) + '</span>';
            html +=   '<span class="text-sm font-black text-emerald-700 shrink-0">0% → ' + item.value.toFixed(2) + '%</span>';
            html += '</div>';
        });
        html +=   '</div>';
        html += '</div>';
    }

    /* ---- Approval needed (amber) ---- */
    if (_approvalItems.length > 0) {
        html += '<div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">';
        html +=   '<div class="flex items-center gap-3 mb-3">';
        html +=     '<div class="w-8 h-8 rounded-full bg-amber-500 text-white text-sm font-black flex items-center justify-center shrink-0">!</div>';
        html +=     '<div>';
        html +=       '<p class="text-sm font-black text-amber-800">' + _approvalItems.length + ' KPI need boss approval</p>';
        html +=       '<p class="text-[10px] text-amber-600 mt-0.5">Request will be sent — boss must approve before changes take effect</p>';
        html +=     '</div>';
        html +=   '</div>';

        html +=   '<div class="space-y-1.5 mb-4">';
        _approvalItems.forEach(function(item) {
            var diff = item.newVal - item.oldVal;
            html += '<div class="flex items-center justify-between bg-white rounded-xl px-3 py-2.5 border border-amber-100">';
            html +=   '<span class="text-xs font-bold text-slate-700 truncate mr-3">' + escapeHtml(item.title) + '</span>';
            html +=   '<span class="text-sm font-black text-amber-700 shrink-0">' +
                          item.oldVal.toFixed(2) + '% → ' + item.newVal.toFixed(2) + '%' +
                          ' <span class="text-[10px] font-normal text-slate-400">(' + (diff >= 0 ? '+' : '') + diff.toFixed(2) + '%)</span>' +
                      '</span>';
            html += '</div>';
        });
        html +=   '</div>';

        /* Approver banner */
        if (approverName) {
            html += '<div class="rounded-xl bg-[#FBF5EF] border border-[#6B3F2A]/30 px-4 py-3 flex items-center gap-3 mb-4">';
            html +=   '<div class="w-8 h-8 rounded-full bg-[#6B3F2A] text-white text-xs font-black flex items-center justify-center shrink-0">' +
                          escapeHtml(approverName.charAt(0).toUpperCase()) +
                      '</div>';
            html +=   '<div>';
            html +=     '<p class="text-[10px] font-black text-[#6B3F2A]">Approval requests go to</p>';
            html +=     '<p class="text-sm font-black text-slate-900">' + escapeHtml(approverName) +
                            ' <span class="font-normal text-slate-500 text-xs">(' + escapeHtml(approverRole) + ')</span></p>';
            html +=   '</div>';
            html += '</div>';
        }

        /* Shared reason textarea */
        html += '<div>';
        html +=   '<label class="text-xs font-black uppercase text-slate-700">Reason for All Changes</label>';
        html +=   '<p class="text-[10px] text-slate-400 mt-0.5 mb-2">Minimum 20 characters. One reason covers all ' +
                      _approvalItems.length + ' approval request' + (_approvalItems.length !== 1 ? 's' : '') + '.</p>';
        html +=   '<textarea id="bulkApprovalReason" rows="4" ' +
                      'class="w-full border border-slate-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-amber-200 focus:border-amber-400" ' +
                      'placeholder="e.g. Shifting focus to Financial KPIs this quarter due to board priorities..."></textarea>';
        html +=   '<p id="bulkReasonCount" class="text-[10px] text-slate-400 mt-1 text-right">0 / min 20 chars</p>';
        html += '</div>';

        html += '</div>';
    }

    /* ---- Buttons ---- */
    html += '<div class="flex justify-end gap-3 pt-2 border-t border-slate-100">';
    html +=   '<button onclick="closeSaveReviewModal()" ' +
                  'class="px-5 py-2.5 border border-slate-200 rounded-xl text-sm font-black text-slate-700 hover:bg-slate-50">Cancel</button>';
    html +=   '<button id="confirmSaveBtn" onclick="confirmSaveAll()" ' +
                  'class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-black transition">Confirm & Save</button>';
    html += '</div>';

    document.getElementById('saveReviewContent').innerHTML = html;

    /* Attach char counter */
    var ta      = document.getElementById('bulkApprovalReason');
    var counter = document.getElementById('bulkReasonCount');
    if (ta && counter) {
        ta.addEventListener('input', function() {
            counter.innerText = ta.value.length + ' / min 20 chars';
        });
    }
}

function closeSaveReviewModal() {
    document.getElementById('saveReviewModal').style.display = 'none';
}

/* ------------------------------------------------------------------ */
/*  CONFIRM & EXECUTE SAVES                                             */
/* ------------------------------------------------------------------ */
function confirmSaveAll() {
    /* Validate reason if approval items exist */
    if (_approvalItems.length > 0) {
        var reasonEl = document.getElementById('bulkApprovalReason');
        var reason   = reasonEl ? reasonEl.value : '';
        if (reason.trim().length < 20) {
            alert('Please enter a reason with at least 20 characters for the approval requests.');
            return;
        }
    }

    var btn   = document.getElementById('confirmSaveBtn');
    btn.disabled  = true;
    btn.innerText = 'Saving...';

    var csrf    = '{{ csrf_token() }}';
    var errors  = [];
    var skipped = [];   // already-pending KPIs — soft skip, not a failure
    var reason  = _approvalItems.length > 0
        ? (document.getElementById('bulkApprovalReason') || {}).value || ''
        : '';

    /* Sequential promise chain */
    var chain = Promise.resolve();

    /* 1. Direct saves via bulk-update */
    if (_directItems.length > 0) {
        chain = chain.then(function() {
            var payload = {};
            _directItems.forEach(function(item) { payload[item.kpiId] = item.value; });
            return fetch('{{ route("weightage.bulk-update") }}', {
                method : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body   : JSON.stringify({ weightages: payload }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) errors.push('Direct save failed: ' + (data.message || 'Unknown error'));
            })
            .catch(function() { errors.push('Network error during direct save.'); });
        });
    }

    /* 2. Approval request per KPI */
    _approvalItems.forEach(function(item) {
        chain = chain.then(function() {
            return fetch('/kpi/' + item.kpiId + '/request-weightage-change', {
                method : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body   : JSON.stringify({ old_weightage: item.oldVal, new_weightage: item.newVal, reason: reason }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    var msg = (data.message || '').toLowerCase();
                    /* Already-pending is a soft skip — the previous request is still active */
                    if (msg.indexOf('pending') !== -1 || msg.indexOf('already exists') !== -1) {
                        skipped.push(item.title);
                    } else {
                        errors.push(item.title + ': ' + (data.message || 'Request failed'));
                    }
                }
            })
            .catch(function() { errors.push(item.title + ': Network error'); });
        });
    });

    /* Handle result */
    chain.then(function() {
        if (errors.length > 0) {
            alert('Some actions failed:\n\n' + errors.join('\n'));
            btn.disabled  = false;
            btn.innerText = 'Confirm & Save';
        } else {
            closeSaveReviewModal();
            if (skipped.length > 0) {
                showToast('Saved! Note: ' + skipped.length + ' KPI already had a pending request (skipped).');
            } else {
                showToast('All changes saved successfully!');
            }
            setTimeout(function() { location.reload(); }, 1800);
        }
    });
}

function showToast(message) {
    var toast = document.createElement('div');
    toast.className = 'fixed top-6 right-6 z-[999999] px-5 py-3 rounded-2xl text-sm font-black text-white bg-emerald-600 shadow-xl';
    toast.innerText = message;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}

/* ------------------------------------------------------------------ */
/*  RESET ALL                                                           */
/* ------------------------------------------------------------------ */
function resetAllChanges() {
    if (!confirm('Reset all changes back to saved values?')) return;
    document.querySelectorAll('.weightage-input:not([disabled])').forEach(function(input) {
        input.value = input.dataset.original;
    });
    recalculateWeightage();
}

/* ------------------------------------------------------------------ */
/*  EQUALIZE / BALANCE                                                  */
/* ------------------------------------------------------------------ */
function equalizeAllWeightage() {
    var inputs   = Array.from(document.querySelectorAll('.weightage-input:not([disabled])'));
    if (!inputs.length) return;
    var equal    = Math.floor((100 / inputs.length) * 100) / 100;
    var assigned = 0;
    inputs.forEach(function(inp, i) {
        if (i === inputs.length - 1) {
            inp.value = (100 - assigned).toFixed(2);
        } else {
            inp.value  = equal.toFixed(2);
            assigned  += equal;
        }
    });
    recalculateWeightage();
}

function confirmEqualizeWeightage() {
    if (confirm('Equalize all KPI weightage? This will overwrite current allocation.')) equalizeAllWeightage();
}

function balanceEmptyWeightage() {
    var inputs  = Array.from(document.querySelectorAll('.weightage-input:not([disabled])'));
    var empties = inputs.filter(function(i) { return parseFloat(i.value || 0) <= 0; });
    if (!empties.length) return;
    var used    = inputs.reduce(function(s, i) { return empties.includes(i) ? s : s + parseFloat(i.value || 0); }, 0);
    var balance = Math.max(0, 100 - used);
    var share   = Math.floor((balance / empties.length) * 100) / 100;
    empties.forEach(function(inp, i) {
        inp.value = i === empties.length - 1
            ? (balance - share * (empties.length - 1)).toFixed(2)
            : share.toFixed(2);
    });
    recalculateWeightage();
}

/* ------------------------------------------------------------------ */
/*  INIT                                                                */
/* ------------------------------------------------------------------ */
recalculateWeightage();

</script>

</body>
</html>
