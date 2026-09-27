<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $kpi['kpi_title'] ?? 'KPI' }} — {{ $staff['short_name'] ?? $staff['full_name'] ?? 'Staff' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300">
<div class="px-4 pt-4 pb-10">

    {{-- Breadcrumb / back --}}
    <a href="{{ $backUrl ?? route('dashboard.staff.kpis', $staff['id']) }}" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-400 hover:text-[#6B9080] transition mb-3">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ $backLabel ?? ('Back to ' . ($staff['short_name'] ?? $staff['full_name'] ?? 'Staff') . "'s KPIs") }}
    </a>

    @include('dashboard.partials.kpi-detail-content')

</div>
</main>
</body>
</html>
