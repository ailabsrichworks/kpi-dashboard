<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://ui-avatars.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300 bg-[#F5F5F3]">

{{-- ═══════ HEADER (sticky) ═══════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex items-center justify-between gap-4">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="pointer-events-none absolute -top-10 -right-10 w-48 h-48 rounded-full bg-[#D4AF37]/10 blur-3xl"></div>

        <div class="relative">
            <a href="/dashboard" class="text-[11px] text-[#D4AF37] hover:text-white transition">← Dashboard</a>
            <h1 class="text-2xl font-black tracking-tight mt-1">My Profile</h1>
            <p class="text-white/70 text-xs mt-1">Who you are on the system</p>
        </div>
    </div>
</div>

<div class="px-4 pb-6 max-w-3xl mx-auto space-y-4">

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[12px] font-semibold text-emerald-700">
        ✓ {{ session('success') }}
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

    {{-- IDENTITY CARD --}}
    <div class="bg-white rounded-2xl overflow-hidden soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
        <div class="p-6 flex items-center gap-5">
            <div class="w-20 h-20 rounded-full overflow-hidden shrink-0 ring-4 ring-[#D4AF37]/25">
                <img
                    src="https://ui-avatars.com/api/?name={{ urlencode($user['short_name'] ?? $user['full_name'] ?? 'User') }}&background=7A0019&color=fff&size=80"
                    class="w-full h-full object-cover"
                    alt="Avatar"
                />
            </div>
            <div class="min-w-0">
                <h2 class="text-xl font-black text-slate-900 leading-tight truncate">
                    {{ $user['salutation'] ? $user['salutation'] . ' ' : '' }}{{ $user['full_name'] ?? $user['short_name'] ?? '-' }}
                </h2>
                <p class="text-sm text-slate-500 mt-0.5">{{ $user['position'] ?? '-' }}</p>
                <div class="flex flex-wrap gap-1.5 mt-3">
                    <span class="text-[10px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-[#FBF5EF] text-[#6B3F2A] border border-[#6B3F2A]/20">
                        {{ $user['role'] ?? '-' }}
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                        {{ $department['name'] ?? $user['department_code'] ?? '-' }}
                    </span>
                    <span class="text-[10px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                        {{ session('company_display_name') ?? $user['company_code'] ?? '-' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- DETAILS --}}
    <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-6">
        <p class="text-[10px] uppercase tracking-widest font-black text-slate-400 mb-4">Employee Details</p>

        @php
            $details = [
                ['label' => 'Employee ID', 'value' => $user['employee_id'] ?? '-'],
                ['label' => 'Email', 'value' => $user['email'] ?? '-'],
                ['label' => 'Department', 'value' => $department['name'] ?? $user['department_code'] ?? '-'],
                ['label' => 'Company', 'value' => session('company_display_name') ?? $user['company_code'] ?? '-'],
                [
                    'label' => 'Reports To',
                    'value' => ($manager['short_name'] ?? $manager['full_name'] ?? '-'),
                    'hint'  => $manager['position'] ?? null,
                ],
                [
                    'label' => 'Join Date',
                    'value' => !empty($user['join_date']) ? \Carbon\Carbon::parse($user['join_date'])->format('d M Y') : '-',
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($details as $d)
                <div class="rounded-2xl bg-slate-50 border border-slate-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-wide text-slate-400 font-bold">{{ $d['label'] }}</p>
                    <p class="text-[13px] font-black text-slate-800 mt-0.5 truncate">
                        {{ $d['value'] }}
                        @if(!empty($d['hint']))
                            <span class="text-slate-400 font-semibold">· {{ $d['hint'] }}</span>
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>

</div>
</main>

</body>
</html>
