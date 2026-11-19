<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff Data</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="/vendor/alpinejs/alpinejs-3.14.9.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .thin-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">

<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="relative">
            <h1 class="text-xl font-black tracking-tight leading-tight">Edit Staff Data — {{ $companyCode }}</h1>
            <p class="text-[11px] text-white/60 mt-1">{{ $today }} · Only HR/BTS can directly edit staff data here</p>
        </div>

        <form method="GET" action="{{ route('staff-management') }}" class="relative flex flex-wrap items-center gap-2">
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Company</label>
                <select name="company" onchange="this.form.submit()" class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20">
                    @foreach($companies as $c)
                        <option value="{{ $c['code'] }}" @selected($companyCode === strtoupper($c['code']))>{{ $c['name'] ?? $c['code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Search</label>
                <input type="text" name="q" value="{{ $search }}" placeholder="Name / Staff ID..." class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20 w-44">
            </div>
            <button type="submit" class="text-xs font-black px-3 py-1.5 rounded-lg bg-[#D4AF37] text-[#1a1a1a] mt-4">Go</button>
        </form>
    </div>
</div>

<div class="px-4 pb-10 space-y-3">

    @if(session('success'))
        <div class="bg-[#DCFCE7] border border-[#15803D]/30 text-[#15803D] text-[11px] font-bold rounded-xl px-4 py-3">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden">
        <div class="p-4 pb-2">
            <p class="text-[11px] font-black text-slate-800">Staff Active ({{ count($employees) }})</p>
            <p class="text-[9px] text-slate-400 mt-0.5">
                Only Active staff are shown here. Click Edit to change any staff information.
                Staff who have resigned/been terminated (Inactive) are now recorded in
                <a href="{{ route('staff-history', ['company' => $companyCode]) }}" class="text-[#1D4ED8] font-bold hover:underline">Staff History</a>.
            </p>
        </div>

        <div class="overflow-x-auto thin-scroll">
            <table class="w-full text-[11px]">
                <thead>
                    <tr class="text-left text-[9px] font-black text-slate-400 uppercase tracking-widest border-y border-[#E5E7EB]">
                        <th class="px-4 py-2.5">Staff</th>
                        <th class="px-4 py-2.5">Department</th>
                        <th class="px-4 py-2.5">Role</th>
                        <th class="px-4 py-2.5">Position</th>
                        <th class="px-4 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $e)
                        <tr class="border-b border-[#F1F5F9] hover:bg-[#FAFAF9]">
                            <td class="px-4 py-3">
                                <p class="font-black text-slate-800">{{ $e['short_name'] ?? $e['full_name'] }}</p>
                                <p class="text-[9px] text-slate-400">{{ $e['employee_id'] }} · {{ $e['email'] ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $e['department_name'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $e['role'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $e['position'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('staff-management.edit', $e['id']) }}" class="text-[10px] font-black px-3 py-1.5 rounded-lg bg-[#1A0A0A] text-white hover:bg-[#7A0019] transition">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-[11px]">No active staff found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>

</body>
</html>
