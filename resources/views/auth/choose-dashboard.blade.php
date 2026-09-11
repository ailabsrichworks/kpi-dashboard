<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Company — RCG KPI</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .brand-panel { background: radial-gradient(circle at top left, rgba(196,184,150,.4), transparent 32%), radial-gradient(circle at bottom right, rgba(166,147,116,.3), transparent 38%), linear-gradient(135deg, #F1EBE0 0%, #E9E0D1 50%, #DED2BC 130%); }
    </style>
</head>

<body class="min-h-screen bg-[#F5F5F3] flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        {{-- Header card --}}
        <div class="brand-panel relative overflow-hidden rounded-[20px] text-[#3A3128] px-6 py-6 shadow-[0_15px_45px_rgba(0,0,0,0.12)] mb-4">
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-[#C9B896] via-[#C9B896] to-[#C9B896]/10"></div>
            <div class="pointer-events-none absolute -top-10 -right-10 w-40 h-40 rounded-full bg-[#C9B896]/25 blur-3xl"></div>
            <div class="relative mb-1">
                <h1 class="text-base font-black">Select Company</h1>
                <p class="text-[11px] text-[#8B7355] font-semibold">Choose which company dashboard to access</p>
            </div>
            @if(session('user_name'))
            <p class="relative text-[10px] text-[#6B5D4F] mt-3">Logged in as <span class="text-[#3A3128] font-bold">{{ session('user_name') }}</span></p>
            @endif
        </div>

        {{-- Error --}}
        @if(session('error'))
            <div class="mb-3 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Company cards --}}
        <div class="space-y-3">
            @forelse($dashboards as $dashboard)
                @php
                    $roleBadge = match(strtoupper(trim($dashboard['role'] ?? ''))) {
                        'SLT'       => 'bg-purple-50 text-purple-700 border-purple-100',
                        'VP'        => 'bg-[#F5EAE0] text-[#6B3F2A] border-[#E8D5C4]',
                        'MANAGER'   => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                        'EXECUTIVE' => 'bg-slate-100 text-slate-600 border-slate-200',
                        default     => 'bg-slate-100 text-slate-600 border-slate-200',
                    };
                @endphp
                <form method="POST" action="{{ route('dashboard.select') }}">
                    @csrf
                    <input type="hidden" name="employee_uuid" value="{{ $dashboard['employee_uuid'] }}">
                    <input type="hidden" name="company_code" value="{{ $dashboard['company_code'] }}">

                    <button type="submit" class="w-full text-left bg-white rounded-2xl border border-[#E5E7EB] border-t-[3px] border-t-[#C9B896] shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all group">
                        <div class="p-4 flex items-center gap-4">

                            {{-- Company logo — falls back to initials when this company has no logo file --}}
                            @php $dashboardLogoUrl = \App\Services\CompanyLogoService::forBackground($dashboard['company_code'] ?? null, '#F8FAFC'); @endphp
                            <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden border border-[#E5E7EB] p-1.5">
                                @if($dashboardLogoUrl)
                                <img src="{{ $dashboardLogoUrl }}" alt="{{ $dashboard['company_display_name'] ?? $dashboard['company_name'] ?? $dashboard['company_code'] }} logo" class="w-full h-full object-contain">
                                @else
                                <span class="text-sm font-black text-[#6B5D4F]">{{ strtoupper(substr($dashboard['company_code'],0,2)) }}</span>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-black text-slate-900 truncate">{{ $dashboard['company_display_name'] ?? $dashboard['company_name'] ?? $dashboard['company_code'] }}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">{{ $dashboard['full_name'] ?? $dashboard['short_name'] ?? 'Employee' }}</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black border {{ $roleBadge }}">{{ $dashboard['role'] }}</span>
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold">{{ $dashboard['department_code'] }}</span>
                                    @if($dashboard['is_default'] ?? false)
                                        <span class="px-2 py-0.5 rounded-full bg-[#C9B896]/20 text-[#8B7355] text-[10px] font-bold border border-[#C9B896]/40">Default</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Arrow --}}
                            <div class="text-slate-300 group-hover:text-[#8B7355] transition shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </button>
                </form>
            @empty
                <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    No company access found for this account.
                </div>
            @endforelse
        </div>

        {{-- Logout --}}
        <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
            @csrf
            <button class="text-xs text-slate-400 hover:text-[#6B5D4F] transition font-semibold">← Back to login</button>
        </form>

    </div>

</body>
</html>
