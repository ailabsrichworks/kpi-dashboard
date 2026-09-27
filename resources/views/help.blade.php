<!DOCTYPE html>
<html>
<head>
    <title>Help & Guide</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>

<body class="min-h-screen bg-[#F5F5F3]">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300 bg-[#F5F5F3]">

{{-- ═══════ HEADER (sticky) ════════════════════════════════════════════════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-row items-center justify-between gap-4">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="pointer-events-none absolute -top-10 -right-10 w-48 h-48 rounded-full bg-[#D4AF37]/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-16 left-1/3 w-56 h-56 rounded-full bg-white/10 blur-3xl"></div>

        <div class="relative flex items-center gap-4">
            <div class="w-11 h-11 rounded-2xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-[#D4AF37]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <a href="{{ route('kpi.index') }}" class="text-[11px] text-[#D4AF37] hover:text-white transition">← KPI List</a>
                <h1 class="text-2xl font-black tracking-tight mt-1">Help &amp; Guide</h1>
                <p class="text-white/70 text-xs mt-1">Understand what every score, colour, and status means</p>
            </div>
        </div>
    </div>
</div>

<div class="px-4 pb-6 space-y-4">

    {{-- SCORE BANDS --}}
    <div class="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Score Bands · Performance Achievement Guide</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-emerald-50 border border-emerald-100">
                <div class="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-emerald-400 to-green-500 shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-xs font-black text-emerald-700">Outstanding &nbsp;·&nbsp; 90% – 100%</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">On or above target. Outstanding performance.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-yellow-50 border border-yellow-100">
                <div class="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-yellow-400 to-amber-500 shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-xs font-black text-yellow-700">Meets Expectations &nbsp;·&nbsp; 70% – 89%</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">Solid progress. Small gaps to close.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-orange-50 border border-orange-100">
                <div class="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-orange-400 to-orange-600 shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-xs font-black text-orange-700">Below Average &nbsp;·&nbsp; 50% – 69%</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">Below expectation. Needs focused attention.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-red-50 border border-red-100">
                <div class="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-red-500 to-red-600 shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-xs font-black text-red-700">Unsatisfactory &nbsp;·&nbsp; 1% – 49%</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">Significantly off target. Urgent action required.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- QUARTER STATUS --}}
    <div class="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Quarter Status</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-200">
                <span class="mt-1 w-3 h-3 rounded-full bg-slate-400 shrink-0 ring-2 ring-slate-200"></span>
                <div>
                    <p class="text-xs font-black text-slate-600">Not Started</p>
                    <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Quarter has not begun or no progress entered yet.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-emerald-50 border border-emerald-100">
                <span class="mt-1 w-3 h-3 rounded-full bg-emerald-500 shrink-0 ring-2 ring-emerald-200"></span>
                <div>
                    <p class="text-xs font-black text-emerald-700">On Track</p>
                    <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Progressing as planned. No issues foreseen.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-yellow-50 border border-yellow-100">
                <span class="mt-1 w-3 h-3 rounded-full bg-yellow-500 shrink-0 ring-2 ring-yellow-200"></span>
                <div>
                    <p class="text-xs font-black text-yellow-700">At Risk</p>
                    <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Some concern. Could miss target if not acted on soon.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-red-50 border border-red-100">
                <span class="mt-1 w-3 h-3 rounded-full bg-red-500 shrink-0 ring-2 ring-red-200"></span>
                <div>
                    <p class="text-xs font-black text-red-700">In Trouble</p>
                    <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Significantly behind plan. Immediate action needed.</p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-3 rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20">
                <span class="mt-1 w-3 h-3 rounded-full bg-[#6B3F2A] shrink-0 ring-2 ring-[#6B3F2A]/30"></span>
                <div>
                    <p class="text-xs font-black text-[#6B3F2A]">Completed</p>
                    <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Quarter target achieved and officially closed.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- HOW SCORE IS CALCULATED --}}
        <div class="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">How Score Works</h2>
            </div>
            <div class="space-y-2.5">
                <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">1</span>
                        <p class="text-[11px] font-black text-slate-600 uppercase">Quarter Score</p>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed pl-7">For each quarter:<br><span class="font-semibold text-slate-700">Actual ÷ Base Target × 100</span><br>Scores are added across all 4 quarters.</p>
                </div>
                <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">2</span>
                        <p class="text-[11px] font-black text-slate-600 uppercase">Weighted Score</p>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed pl-7"><span class="font-semibold text-slate-700">KPI Score × Weightage ÷ 100</span><br>Each KPI contributes proportionally based on its importance.</p>
                </div>
                <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">3</span>
                        <p class="text-[11px] font-black text-slate-600 uppercase">Total KPI Score</p>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed pl-7">Sum of all <span class="font-semibold text-slate-700">Weighted Scores</span>.<br>All KPI weightages <strong>must total 100%</strong>.</p>
                </div>
            </div>
        </div>

        {{-- QUICK REFERENCE --}}
        <div class="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Quick Reference</h2>
            </div>
            <div class="space-y-2.5">
                <div class="p-3 rounded-2xl bg-amber-50 border border-amber-100">
                    <p class="text-[11px] font-black text-amber-700 mb-1">What is Weightage?</p>
                    <p class="text-[11px] text-slate-500 leading-relaxed">A % value showing <span class="font-semibold">how much this KPI counts</span> towards your total score. Example: a KPI with 30% weightage contributes 3× more than one at 10%.</p>
                </div>
                <div class="p-3 rounded-2xl bg-violet-50 border border-violet-100">
                    <p class="text-[11px] font-black text-violet-700 mb-1">What is Base vs Stretch Target?</p>
                    <p class="text-[11px] text-slate-500 leading-relaxed"><span class="font-semibold">Base Target</span> = the minimum expected result.<br><span class="font-semibold">Stretch Target</span> = an ambitious goal beyond base.</p>
                </div>
                <div class="p-3 rounded-2xl bg-sky-50 border border-sky-100">
                    <p class="text-[11px] font-black text-sky-700 mb-1">What are Q1, Q2, Q3, Q4?</p>
                    <p class="text-[11px] text-slate-500 leading-relaxed">The year is split into 4 quarters:<br>Q1 Jan–Mar · Q2 Apr–Jun<br>Q3 Jul–Sep · Q4 Oct–Dec</p>
                </div>
                <div class="p-3 rounded-2xl bg-rose-50 border border-rose-100">
                    <p class="text-[11px] font-black text-rose-700 mb-1">Why does my score show 0%?</p>
                    <p class="text-[11px] text-slate-500 leading-relaxed">Either no actuals have been entered yet, or your total weightage is not set to 100%. Check the Weightage page.</p>
                </div>
            </div>
        </div>

    </div>

    <p class="text-[10px] text-slate-400 text-center pb-2">Score is calculated in real-time from your actual vs target values across all quarters. Contact your manager if you see unexpected results.</p>

</div>

</main>

</body>
</html>
