<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .filter-chip.active { outline: 2px solid #1e293b; outline-offset: 1px; }
        .notif-row { transition: box-shadow .15s, transform .15s; }
        .notif-row:hover { box-shadow: 0 10px 30px rgba(15,23,42,.10); transform: translateY(-1px); }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

@php
    // category drives the accent color; type drives the icon/label within it
    $categoryMeta = [
        'approval'  => ['label' => 'Approval Needed',  'bg' => '#D4AF37', 'soft' => '#D4AF37'],
        'appraisal' => ['label' => 'Appraisal',         'bg' => '#7A0019', 'soft' => '#7A0019'],
        'update'    => ['label' => 'Team Update',       'bg' => '#475569', 'soft' => '#475569'],
    ];
    $typeMeta = [
        'job_description_submitted'  => ['icon' => '📋', 'label' => 'Job Description',     'category' => 'update'],
        'appraisal_submitted'        => ['icon' => '📝', 'label' => 'Appraisal Submitted',  'category' => 'appraisal'],
        'appraisal_appraised'        => ['icon' => '✅', 'label' => 'Ready to Sign',         'category' => 'appraisal'],
        'kpi_completion_approval'    => ['icon' => '✔️', 'label' => 'Completion Approval',   'category' => 'approval'],
        'kpi_target_change_approval' => ['icon' => '🎯', 'label' => 'Target Change',         'category' => 'approval'],
        'kpi_delete_approval'        => ['icon' => '🗑️', 'label' => 'Delete Request',        'category' => 'approval'],
        'kpi_actual_approval'        => ['icon' => '📊', 'label' => 'Actual Update',         'category' => 'approval'],
        'kpi_weightage_approval'     => ['icon' => '⚖️', 'label' => 'Weightage Change',      'category' => 'approval'],
    ];

    $rows = collect($notifications)->map(function ($n) use ($typeMeta, $categoryMeta) {
        $type = $typeMeta[$n['type']] ?? ['icon' => '🔔', 'label' => 'Update', 'category' => 'update'];
        $n['_type']  = $type;
        $n['_cat']   = $categoryMeta[$type['category']];
        $n['_when']  = \Carbon\Carbon::parse($n['created_at']);
        return $n;
    });

    $unreadCount   = $rows->where('is_read', false)->count();
    $approvalCount = $rows->where('_type.category', 'approval')->count();
    $appraisalCount = $rows->where('_type.category', 'appraisal')->count();
    $updateCount   = $rows->where('_type.category', 'update')->count();

    $today   = $rows->filter(fn($n) => $n['_when']->isToday())->values();
    $earlier = $rows->filter(fn($n) => !$n['_when']->isToday())->values();
@endphp

<main id="mainContent" class="ml-[230px] min-h-screen">

{{-- ═══════ HEADER (sticky) ═══════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="relative">
            @if($unreadCount > 0)
                <span class="text-[11px] font-black bg-[#D4AF37] text-[#1a1a1a] px-2 py-0.5 rounded-full">{{ $unreadCount }} new</span>
            @endif
        </div>
        @if($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}" class="relative">
            @csrf
            <button type="submit" class="text-xs font-black bg-white/10 hover:bg-white/20 text-white px-3.5 py-2 rounded-xl border border-white/20 transition">
                Mark all as read
            </button>
        </form>
        @endif
    </div>
</div>

<div class="px-4 pb-4 space-y-3">

    @if($rows->isEmpty())
        <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] p-12 text-center">
            <div class="text-4xl mb-3">🔔</div>
            <p class="text-slate-500 font-bold text-sm">No notifications yet</p>
            <p class="text-slate-400 text-xs mt-1 max-w-sm mx-auto">You'll see something here as soon as someone who reports to you submits a Job Description, an appraisal, or requests your approval on a KPI.</p>
        </div>
    @else
        {{-- ═══════ FILTER CHIPS ═══════ --}}
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="filterCat('all')" data-cat="all" class="filter-chip active px-3 py-1.5 rounded-xl text-[11px] font-black bg-white border border-[#E5E7EB] text-slate-700 transition">
                All <span class="opacity-50">({{ $rows->count() }})</span>
            </button>
            <button type="button" onclick="filterCat('approval')" data-cat="approval" class="filter-chip px-3 py-1.5 rounded-xl text-[11px] font-black transition" style="background:{{ $categoryMeta['approval']['bg'] }}22;color:#8a6d00;">
                ⚖️ Approvals <span class="opacity-60">({{ $approvalCount }})</span>
            </button>
            <button type="button" onclick="filterCat('appraisal')" data-cat="appraisal" class="filter-chip px-3 py-1.5 rounded-xl text-[11px] font-black transition" style="background:{{ $categoryMeta['appraisal']['bg'] }}18;color:{{ $categoryMeta['appraisal']['bg'] }};">
                📝 Appraisals <span class="opacity-60">({{ $appraisalCount }})</span>
            </button>
            <button type="button" onclick="filterCat('update')" data-cat="update" class="filter-chip px-3 py-1.5 rounded-xl text-[11px] font-black bg-slate-100 text-slate-600 transition">
                📋 Job Descriptions <span class="opacity-60">({{ $updateCount }})</span>
            </button>
        </div>

        @foreach([['label' => 'Today', 'items' => $today], ['label' => 'Earlier', 'items' => $earlier]] as $group)
            @continue($group['items']->isEmpty())
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">{{ $group['label'] }}</p>
                <div class="space-y-2">
                    @foreach($group['items'] as $n)
                        @php
                            $type   = $n['_type'];
                            $cat    = $n['_cat'];
                            $unread = !($n['is_read'] ?? false);
                        @endphp
                        <div class="notif-row bg-white rounded-2xl soft-card border border-[#E5E7EB] {{ $unread ? 'border-l-[4px]' : '' }} p-4 flex items-start gap-3 cursor-pointer"
                             style="{{ $unread ? 'border-left-color:'.$cat['bg'].';' : '' }}"
                             data-id="{{ $n['id'] }}" data-link="{{ $n['link'] ?? '' }}" data-cat="{{ $type['category'] }}">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0" style="background:{{ $unread ? $cat['bg'].'18' : '#F8FAFC' }};">
                                {{ $type['icon'] }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-[13px] {{ $unread ? 'font-black text-slate-900' : 'font-bold text-slate-600' }} leading-snug">
                                        {{ $n['title'] }}
                                    </p>
                                    <span class="text-[10px] text-slate-400 shrink-0 whitespace-nowrap">{{ $n['_when']->diffForHumans() }}</span>
                                </div>
                                @if(!empty($n['message']))
                                    <p class="text-[11px] text-slate-500 mt-0.5">{{ $n['message'] }}</p>
                                @endif
                                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                    <span class="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full" style="background:{{ $cat['bg'] }}18;color:{{ $cat['bg'] === '#D4AF37' ? '#8a6d00' : $cat['bg'] }};">{{ $type['label'] }}</span>
                                    @if(!empty($n['quarter']))
                                        <span class="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">{{ $n['quarter'] }} {{ $n['financial_year'] }}</span>
                                    @endif
                                    @if($unread)
                                        <span class="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full bg-red-50 text-red-600">New</span>
                                    @endif
                                </div>
                            </div>
                            @if(!empty($n['link']))
                                <span class="shrink-0 self-center text-[10px] font-black px-2.5 py-1.5 rounded-lg" style="background:{{ $cat['bg'] }}18;color:{{ $cat['bg'] === '#D4AF37' ? '#8a6d00' : $cat['bg'] }};">Open →</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <p id="emptyFilterMsg" class="hidden text-center text-[11px] text-slate-400 py-8">Nothing in this category yet.</p>
    @endif

</div>
</main>

<script>
function filterCat(cat) {
    document.querySelectorAll('.filter-chip').forEach(function (chip) {
        chip.classList.toggle('active', chip.dataset.cat === cat);
    });
    var anyVisible = false;
    document.querySelectorAll('.notif-row').forEach(function (row) {
        var show = (cat === 'all' || row.dataset.cat === cat);
        row.style.display = show ? '' : 'none';
        if (show) anyVisible = true;
    });
    // hide now-empty "Today"/"Earlier" group headers
    document.querySelectorAll('main > div.px-4 > div > p.uppercase').forEach(function (header) {
        var group = header.nextElementSibling;
        if (!group) return;
        var visibleInGroup = Array.prototype.some.call(group.children, function (row) {
            return row.style.display !== 'none';
        });
        header.parentElement.style.display = visibleInGroup ? '' : 'none';
    });
    var emptyMsg = document.getElementById('emptyFilterMsg');
    if (emptyMsg) emptyMsg.classList.toggle('hidden', anyVisible);
}

document.querySelectorAll('.notif-row').forEach(function (row) {
    row.addEventListener('click', function () {
        var id = row.dataset.id;
        var link = row.dataset.link;

        fetch('/notifications/' + id + '/read', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        }).finally(function () {
            if (link) window.location.href = link;
        });
    });
});
</script>

</body>
</html>
