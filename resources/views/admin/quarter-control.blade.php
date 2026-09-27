<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quarter Control · Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">
<div class="p-6 max-w-4xl mx-auto space-y-4">

    <div>
        <h1 class="text-xl font-black text-slate-900">Quarter Control</h1>
        <p class="text-[12px] text-slate-500 mt-1">
            BTS-only. Forces a quarter open — for both KPI actual updates and the appraisal
            (self-review + manager/VP/SLT appraiser) forms — until the date and time you set,
            regardless of that quarter's normal dates. Useful when a past quarter (e.g. the one
            before the current one) needs to be reopened for a late submission or correction.
        </p>
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[12px] font-semibold text-emerald-700">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">
        {{ $errors->first() }}
    </div>
    @endif

    <div class="bg-white rounded-2xl soft-card border border-slate-200 px-4 py-3 flex items-center justify-between">
        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Financial Year</p>
        <form method="GET" action="{{ route('admin.quarter-control') }}" class="flex items-center gap-2">
            <input
                type="text"
                name="financial_year"
                value="{{ $financialYear }}"
                class="w-28 rounded-lg border border-slate-200 px-3 py-1.5 text-[12px] font-bold text-center focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none"
            >
            <button type="submit" class="text-[11px] font-black px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition">View</button>
        </form>
    </div>

    <div class="space-y-3">
        @foreach($quarters as $q)
        <div x-data="{ open: false }">
            <div class="bg-white rounded-2xl soft-card border border-slate-200 p-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-[14px] font-black text-slate-900">{{ $q['quarter'] }}</p>
                        @if($q['is_active'])
                            <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">OPEN OVERRIDE</span>
                        @else
                            <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">NO OVERRIDE</span>
                        @endif
                    </div>
                    @if($q['is_active'])
                        <p class="text-[11px] text-slate-500 mt-1">
                            Open until <span class="font-bold text-slate-700">{{ $q['open_until_display'] }}</span>
                            @if($q['created_by_name']) · opened by {{ $q['created_by_name'] }} @endif
                        </p>
                    @else
                        <p class="text-[11px] text-slate-400 mt-1">Following its normal quarter/appraisal dates.</p>
                    @endif
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if($q['is_active'])
                    <form method="POST" action="{{ route('admin.quarter-control.destroy', $q['quarter']) }}" onsubmit="return confirm('Close the {{ $q['quarter'] }} override now?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="financial_year" value="{{ $financialYear }}">
                        <button type="submit" class="text-[11px] font-black px-3 py-2 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition">Close now</button>
                    </form>
                    @endif
                    <button @click="open = !open" class="text-[11px] font-black px-3 py-2 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                        <span x-text="open ? 'Cancel' : '{{ $q['is_active'] ? 'Extend' : 'Open' }}'"></span>
                    </button>
                </div>
            </div>

            <div x-show="open" x-cloak class="bg-white rounded-2xl soft-card border-2 border-[#6B9080]/40 p-4 mt-2">
                <form method="POST" action="{{ route('admin.quarter-control.store') }}" class="flex items-end gap-3 flex-wrap">
                    @csrf
                    <input type="hidden" name="quarter" value="{{ $q['quarter'] }}">
                    <input type="hidden" name="financial_year" value="{{ $financialYear }}">
                    <div>
                        <p class="text-[10px] font-bold text-slate-600 mb-1">Open {{ $q['quarter'] }} until</p>
                        <input
                            type="datetime-local"
                            name="open_until"
                            required
                            class="rounded-xl border-2 border-[#D9C4A0] px-3 py-2 text-[13px] bg-white outline-none focus:border-red-500"
                        >
                    </div>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black">
                        Confirm — open {{ $q['quarter'] }}
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <div class="pt-2">
        <h2 class="text-lg font-black text-slate-900">Appraiser Delegation</h2>
        <p class="text-[12px] text-slate-500 mt-1">
            BTS-only. If a Manager is on long leave, stand their own VP in as appraiser for that
            Manager's Executives — still through the normal role chain, just skipping the absent
            person. Remove the delegation once the Manager is back. A VP's own appraiser duty can't
            be delegated onward through this — if the VP is also away, this feature doesn't apply.
        </p>
    </div>

    <div class="space-y-3">
        @forelse($appraiserManagers as $m)
        <div x-data="{ open: false }">
            <div class="bg-white rounded-2xl soft-card border border-slate-200 p-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-[14px] font-black text-slate-900">{{ $m['short_name'] }}</p>
                        <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">{{ $m['department_code'] }}</span>
                        @if($m['is_delegated'])
                            <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">DELEGATED</span>
                        @endif
                    </div>
                    @if($m['is_delegated'])
                        <p class="text-[11px] text-slate-500 mt-1">
                            <span class="font-bold text-slate-700">{{ $m['delegate_name'] ?? 'Their VP' }}</span> is appraising {{ $m['short_name'] }}'s Executives
                            @if($m['reason']) · {{ $m['reason'] }} @endif
                        </p>
                    @elseif($m['candidate_vp_id'])
                        <p class="text-[11px] text-slate-400 mt-1">Normal chain — would delegate to <span class="font-semibold">{{ $m['candidate_vp_name'] ?? 'their VP' }}</span> if activated.</p>
                    @else
                        <p class="text-[11px] text-red-400 mt-1">No VP on record — this Manager can't be delegated.</p>
                    @endif
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if($m['is_delegated'])
                    <form method="POST" action="{{ route('admin.appraiser-delegation.destroy', $m['id']) }}" onsubmit="return confirm('End this delegation and revert {{ $m['short_name'] }} to the normal chain?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[11px] font-black px-3 py-2 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition">End delegation</button>
                    </form>
                    @elseif($m['candidate_vp_id'])
                    <button @click="open = !open" class="text-[11px] font-black px-3 py-2 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                        <span x-text="open ? 'Cancel' : 'Delegate'"></span>
                    </button>
                    @endif
                </div>
            </div>

            @if(!$m['is_delegated'] && $m['candidate_vp_id'])
            <div x-show="open" x-cloak class="bg-white rounded-2xl soft-card border-2 border-[#6B9080]/40 p-4 mt-2">
                <form method="POST" action="{{ route('admin.appraiser-delegation.store') }}" class="flex items-end gap-3 flex-wrap">
                    @csrf
                    <input type="hidden" name="manager_id" value="{{ $m['id'] }}">
                    <div class="flex-1 min-w-[220px]">
                        <p class="text-[10px] font-bold text-slate-600 mb-1">Reason (optional)</p>
                        <input
                            type="text"
                            name="reason"
                            maxlength="500"
                            placeholder="e.g. Long MC / maternity leave until further notice"
                            class="w-full rounded-xl border-2 border-[#D9C4A0] px-3 py-2 text-[13px] bg-white outline-none focus:border-red-500"
                        >
                    </div>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-[#16A34A] hover:bg-[#15803D] text-white text-[12px] font-black whitespace-nowrap">
                        Confirm — {{ $m['candidate_vp_name'] ?? 'their VP' }} takes over
                    </button>
                </form>
            </div>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-2xl soft-card border border-slate-200 p-4 text-[12px] text-slate-400 text-center">
            No active Managers found.
        </div>
        @endforelse
    </div>

</div>
</main>

</body>
</html>
