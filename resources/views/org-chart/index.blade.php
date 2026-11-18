<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Org Chart</title>

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

        /* ═══ Pure-CSS org chart connectors ═══ */
        .org-tree, .org-tree ul {
            padding-top: 28px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        /* flex-start, not the inherited center: with align-items:center a
           leaf sibling (no children, shorter li) gets vertically centered
           against a sibling with a deep subtree (taller li) in the same
           row, pushing the leaf's top edge down and breaking the row's
           horizontal connector line into disconnected segments. flex-start
           keeps every sibling's top edge flush so the line stays continuous
           no matter how uneven the subtrees below it are. */
        .org-tree ul { flex-direction: row; align-items: flex-start; }
        .org-tree li {
            list-style-type: none;
            position: relative;
            padding: 28px 14px 0 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .org-tree li::before, .org-tree li::after {
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            width: 50%;
            height: 28px;
            border-top: 2px solid #D4AF37;
        }
        .org-tree li::after { right: auto; left: 50%; border-left: 2px solid #D4AF37; }
        .org-tree li:only-child::after, .org-tree li:only-child::before { display: none; }
        .org-tree li:only-child { padding-top: 0; }
        .org-tree li:first-child::before, .org-tree li:last-child::after { border: 0 none; }
        .org-tree li:last-child::before { border-right: 2px solid #D4AF37; border-radius: 0 6px 0 0; }
        .org-tree li:first-child::after { border-radius: 6px 0 0 0; }
        .org-tree li > ul::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            border-left: 2px solid #D4AF37;
            width: 0;
            height: 28px;
        }
        .org-tree > li { padding-top: 0 !important; }
        .org-tree > li::before, .org-tree > li::after { display: none !important; }

        .org-node { transition: opacity .15s, transform .15s; }
        .org-node-dimmed { opacity: .25; }
        .org-node.org-search-match { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(212,175,55,.45); }
        .org-node.org-search-hidden { opacity: .12; }

        @media print {
            #sidebar, .no-print { display: none !important; }
            #mainContent { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

@php
    $roleBadgeStyle = [
        'SLT'       => ['bg' => '#F3E8FF', 'text' => '#7C3AED', 'border' => '#7C3AED'],
        'VP'        => ['bg' => '#F5EAE0', 'text' => '#6B3F2A', 'border' => '#6B3F2A'],
        'MANAGER'   => ['bg' => '#E0E7FF', 'text' => '#4338CA', 'border' => '#4338CA'],
        'EXECUTIVE' => ['bg' => '#F1F5F9', 'text' => '#475569', 'border' => '#94A3B8'],
    ];
    $roleLabels = ['SLT' => 'SLT', 'VP' => 'VP', 'MANAGER' => 'Manager', 'EXECUTIVE' => 'Executive'];
@endphp

<main id="mainContent" class="ml-[230px] min-h-screen">

{{-- ═══════ HEADER (sticky) ═══════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="relative">
            <h1 class="text-xl font-black tracking-tight leading-tight">
                Organization Chart — {{ $companyCode }}
            </h1>
            <p class="text-[11px] text-white/60 mt-1">{{ $today }} · Full reporting structure for every active staff member</p>
        </div>

        <form method="GET" action="{{ route('org-chart') }}" class="relative flex flex-wrap items-center gap-2">
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Company</label>
                {{-- Resets the department filter on change -- a department
                     code picked for the old company (e.g. TITAN) has no
                     match in a different company's roster, so starting
                     back at "All Departments" avoids landing on a chart
                     that looks empty/filtered for no visible reason. --}}
                <select name="company" onchange="document.getElementById('orgChartDeptSelect').value='ALL'; this.form.submit();" class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20">
                    @foreach($companies as $c)
                        <option value="{{ $c['code'] }}" @selected($companyCode === strtoupper($c['code']))>{{ $c['name'] ?? $c['code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Department</label>
                <select id="orgChartDeptSelect" name="department" onchange="this.form.submit()" class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20">
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

    {{-- ═══════ STAT CARDS ═══════ --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3">
        <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Staff</p>
            <p class="text-2xl font-black text-slate-900 leading-none">{{ $totalStaff }}</p>
        </div>
        @foreach($roleCounts as $role => $count)
            @php $style = $roleBadgeStyle[$role]; @endphp
            <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] p-4" style="border-top:3px solid {{ $style['border'] }};">
                <p class="text-[10px] font-black uppercase tracking-widest mb-2" style="color:{{ $style['text'] }};">{{ $roleLabels[$role] }}</p>
                <p class="text-2xl font-black text-slate-900 leading-none">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    {{-- ═══════ LEGEND + SEARCH ═══════ --}}
    <div class="flex flex-wrap items-center justify-between gap-2 px-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mr-1">Legend</span>
            @foreach($roleBadgeStyle as $role => $style)
                <span class="text-[9px] font-black px-2.5 py-1 rounded-full" style="background:{{ $style['bg'] }};color:{{ $style['text'] }};">{{ $roleLabels[$role] }}</span>
            @endforeach
        </div>
        <input
            type="text"
            id="orgSearch"
            onkeyup="filterOrgChart()"
            placeholder="Search name or ID…"
            class="text-xs rounded-lg px-3 py-1.5 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 no-print"
        >
    </div>

    {{-- ═══════ ORG CHART ═══════ --}}
    <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden">
        <div class="p-4 pb-2">
            <p class="text-[11px] font-black text-slate-800">Reporting Structure</p>
            <p class="text-[9px] text-slate-400 mt-0.5">Executive → Manager → VP → SLT, resolved from each staff member's direct reporting line. Scroll to see the full chart.</p>
        </div>

        {{-- Company name sits as a plain, always-visible banner ABOVE the
             scrollable tree -- not as a node inside it -- so it's never
             pushed out of view by scrolling and always reads big & centered,
             regardless of how many top-level roots the tree itself has. --}}
        @if(!empty($roots))
            <div class="text-center py-3 border-b border-[#E5E7EB] bg-[#FAFAF9]">
                <p class="text-2xl sm:text-3xl font-black text-[#1A0A0A] tracking-tight">{{ $companyCode }}</p>
                <p class="text-[9px] uppercase tracking-[0.2em] text-[#B8860B] font-black mt-1">Organization Chart</p>
            </div>
        @endif

        <div class="overflow-auto thin-scroll px-6 py-6" style="max-height:75vh;" id="orgChartScroll">
            @if(empty($roots))
                <p class="text-center text-[11px] text-slate-400 py-10">No active staff found.</p>
            @else
                <ul class="org-tree" id="orgTreeRoot">
                    @foreach($roots as $root)
                        @include('org-chart._node', ['node' => $root, 'roleBadgeStyle' => $roleBadgeStyle])
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

</div>
</main>

<script>
function filterOrgChart() {
    var q = document.getElementById('orgSearch').value.trim().toLowerCase();
    document.querySelectorAll('#orgTreeRoot .org-node').forEach(function (card) {
        card.classList.remove('org-search-match', 'org-search-hidden');
        if (q === '') return;
        var matches = (card.dataset.search || '').indexOf(q) !== -1;
        card.classList.add(matches ? 'org-search-match' : 'org-search-hidden');
    });
}
</script>

</body>
</html>
