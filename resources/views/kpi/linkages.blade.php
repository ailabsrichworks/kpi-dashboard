<!DOCTYPE html>
<html>
<head>
    <title>Target Linkages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>

<body class="min-h-screen bg-[#F5F5F3]">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300">
<div class="px-4 pb-4 space-y-3">

    {{-- HEADER --}}
    <div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
        <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('dashboard') }}" class="text-[11px] text-[#D4AF37] hover:text-white transition">← Dashboard</a>
                <h1 class="text-2xl font-black tracking-tight mt-1">Target Linkages</h1>
                <p class="text-white/70 text-xs mt-1">Cascading targets · {{ $fy }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 text-emerald-700 px-3 py-2 rounded-xl text-xs border border-emerald-200">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 text-red-700 px-3 py-2 rounded-xl text-xs border border-red-200">{{ session('error') }}</div>@endif

    {{-- ═══════ KPI TARGET LINKAGES ══════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-[#E5E7EB] border-l-[4px] border-l-[#D4AF37] soft-card overflow-hidden">
        <div class="theme-header-banner flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#1A0A0A] to-[#7A0019]">
            <div>
                <h2 class="text-sm font-black text-white">KPI Target Linkages</h2>
                <p class="theme-header-text-muted text-[10px] text-white/70 mt-0.5">Cascading targets · {{ $fy }}</p>
            </div>
            @if($canAssignTarget)
            <button onclick="document.getElementById('assignLinkageForm').classList.toggle('hidden')"
                    class="px-3 py-1.5 bg-white/15 hover:bg-white/25 text-white rounded-xl text-xs font-black transition border border-white/20">
                + Assign Target
            </button>
            @endif
        </div>

        {{-- Assign form (hidden by default) --}}
        @if($canAssignTarget)
        <div id="assignLinkageForm" class="hidden border-b border-[#E5E7EB] bg-slate-50 px-4 py-3">
            <form action="{{ route('linkage.store') }}" method="POST">
                @csrf
                <p class="text-[9px] font-black text-[#B8860B] uppercase mb-2">New Cascading Target</p>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 items-end">
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase block mb-1">Person</label>
                        <select name="assignee_id" required class="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none">
                            <option value="">Select...</option>
                            @foreach($directReports as $dr)
                            <option value="{{ $dr['id'] }}">{{ $dr['short_name'] }} ({{ $dr['role'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase block mb-1">Category</label>
                        <select id="lnkCategory" name="category" required onchange="updateLnkSubCat()"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none">
                            <option value="Financial">Financial</option>
                            <option value="Growth &amp; Customer">Growth &amp; Customer</option>
                            <option value="Initiatives">Initiatives</option>
                            <option value="People">People</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase block mb-1">Sub Category</label>
                        <select id="lnkSubCat" name="sub_category" required
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none">
                            <option value="Revenue">Revenue</option>
                            <option value="Operating Cost Optimisation">Operating Cost Optimisation</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase block mb-1">Unit</label>
                        <select name="unit" required class="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none">
                            <option value="number">Number</option>
                            <option value="currency">Currency (RM)</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase block mb-1">Annual Target</label>
                        <input name="assigned_target" type="number" step="0.01" min="0" required placeholder="0"
                               class="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none">
                    </div>
                    <div class="flex gap-1.5">
                        <button type="submit" class="flex-1 px-3 py-2 theme-soft-btn rounded-xl text-xs font-black transition">Save</button>
                        <button type="button" onclick="document.getElementById('assignLinkageForm').classList.add('hidden')" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-600 rounded-xl text-xs font-black transition">✕</button>
                    </div>
                </div>
            </form>
        </div>
        @endif

        <div class="p-4 bg-white">
            @if(!$hasAnyLinkage)
            <p class="text-xs text-slate-400 text-center py-2">No linkage targets yet. Use "+ Assign Target" to assign a cascading target to your team.</p>
            @else
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

                {{-- Targets Assigned to Me --}}
                @if($myLinkageMap->isNotEmpty())
                <div>
                    <p class="text-[9px] font-black text-[#B8860B] uppercase tracking-wider mb-2">Targets Assigned to Me</p>
                    <div class="space-y-2">
                        @foreach($myLinkageMap as $lnk)
                        @php $lnkMet = $lnk['met']; @endphp
                        <div class="p-2.5 rounded-xl border {{ $lnkMet ? 'border-emerald-200 bg-emerald-50' : 'border-[#E5E7EB] bg-[#D4AF37]/5' }}">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="min-w-0">
                                    <span class="text-xs font-black text-slate-800">{{ $lnk['sub_category'] }}</span>
                                    <span class="ml-1.5 text-[9px] text-slate-400">{{ $lnk['category'] }} · from {{ $lnk['assigner_name'] ?? '-' }}</span>
                                </div>
                                @if(!$lnkMet)
                                <span class="shrink-0 ml-2 text-[9px] font-black px-1.5 py-0.5 rounded-full border bg-[#D4AF37]/10 text-[#B8860B] border-[#E5E7EB]">Gap</span>
                                @else
                                <span class="shrink-0 ml-2 text-[9px] font-black px-1.5 py-0.5 rounded-full border bg-emerald-100 text-emerald-700 border-emerald-200">Met ✓</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 mb-1.5">
                                <div class="flex-1 h-1.5 rounded-full overflow-hidden bg-slate-100">
                                    <div class="h-1.5 rounded-full {{ $lnkMet ? 'bg-emerald-400' : 'bg-[#D4AF37]' }}" style="width:{{ $lnk['pct'] }}%"></div>
                                </div>
                                <span class="text-[9px] font-black text-slate-600 w-7 text-right shrink-0">{{ $lnk['pct'] }}%</span>
                            </div>
                            <div class="flex justify-between text-[9px] text-slate-400">
                                <span>Target: <span class="font-black text-slate-700">{{ $fmtLinkageVal($lnk['assigned_target'], $lnk['unit']) }}</span></span>
                                <span>Covered: <span class="font-black text-slate-700">{{ $fmtLinkageVal($lnk['covered'], $lnk['unit']) }}</span></span>
                                @if(!$lnkMet)
                                <span class="text-[#B8860B] font-black">Gap: {{ $fmtLinkageVal($lnk['gap'], $lnk['unit']) }}</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Targets I Assigned --}}
                @if($outgoingWithCoverage->isNotEmpty())
                <div>
                    <p class="text-[9px] font-black text-[#B8860B] uppercase tracking-wider mb-2">Targets I Assigned</p>
                    <div class="space-y-2">
                        @foreach($outgoingWithCoverage as $lnk)
                        @php $lnkMet = $lnk['met']; @endphp
                        <div class="p-2.5 rounded-xl border border-[#E5E7EB] bg-[#D4AF37]/5 group">
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="min-w-0">
                                    <span class="text-xs font-black text-slate-800">{{ $lnk['assignee_name'] ?? '-' }}</span>
                                    <span class="ml-1.5 text-[9px] text-slate-400">{{ $lnk['sub_category'] }} · {{ $lnk['category'] }}</span>
                                </div>
                                <div class="shrink-0 ml-2 flex items-center gap-1.5">
                                    @if(!$lnkMet)
                                    <span class="text-[9px] font-black bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded border border-amber-200">Gap</span>
                                    @else
                                    <span class="text-[9px] font-black bg-emerald-50 text-emerald-600 px-1.5 py-0.5 rounded border border-emerald-200">Met ✓</span>
                                    @endif
                                    <form action="{{ route('linkage.destroy', $lnk['id']) }}" method="POST" onsubmit="return confirm('Remove this linkage?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-[9px] text-red-400 hover:text-red-600 font-black opacity-0 group-hover:opacity-100 transition">✕</button>
                                    </form>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mb-1.5">
                                <div class="flex-1 h-1.5 rounded-full overflow-hidden bg-slate-100">
                                    <div class="h-1.5 rounded-full {{ $lnkMet ? 'bg-emerald-400' : 'bg-[#D4AF37]' }}" style="width:{{ $lnk['pct'] }}%"></div>
                                </div>
                                <span class="text-[9px] font-black text-slate-600 w-7 text-right shrink-0">{{ $lnk['pct'] }}%</span>
                            </div>
                            <div class="flex justify-between text-[9px] text-slate-400">
                                <span>Target: <span class="font-black text-slate-700">{{ $fmtLinkageVal($lnk['assigned_target'], $lnk['unit']) }}</span></span>
                                <span>Covered: <span class="font-black text-slate-700">{{ $fmtLinkageVal($lnk['covered'], $lnk['unit']) }}</span></span>
                                @if(!$lnkMet)
                                <span class="text-[#B8860B] font-black">Gap: {{ $fmtLinkageVal($lnk['gap'], $lnk['unit']) }}</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
            @endif
        </div>
    </div>

</div>
</main>

<script>
// ── LINKAGE FORM: SUB CATEGORY DROPDOWN ─────────────────────────────────────
const lnkSubCatMap = {
    'Financial':         ['Revenue', 'Operating Cost Optimisation'],
    'Growth & Customer': ['New Customer Acquisition', 'Growth'],
    'Initiatives':       ['Continuous Improvement & New Business'],
    'People':            ['Certification of Competence (COC)', 'Staff Development'],
};

function updateLnkSubCat() {
    const cat    = document.getElementById('lnkCategory')?.value || 'Financial';
    const sel    = document.getElementById('lnkSubCat');
    if (!sel) return;
    const opts   = lnkSubCatMap[cat] || [];
    sel.innerHTML = opts.map(o => `<option value="${o}">${o}</option>`).join('');
}
</script>

</body>
</html>
