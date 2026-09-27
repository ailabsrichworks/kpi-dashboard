<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View As · Admin</title>
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

    <a href="/profile" class="text-[10px] text-slate-500 hover:text-slate-800">← Profile</a>

    <div>
        <h1 class="text-xl font-black text-slate-900">View As — Employee KPI Access</h1>
        <p class="text-[12px] text-slate-500 mt-1">
            BTS-only. Opens an employee's dashboard directly — no password needed. Every use is logged with your name, the employee, and the time.
        </p>
    </div>

    @if(session('error'))
    <div class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">
        {{ session('error') }}
    </div>
    @endif

    <form method="GET" action="{{ route('admin.view-as') }}" class="bg-white rounded-2xl soft-card border border-slate-200 p-4">
        <input
            type="text"
            name="q"
            value="{{ $search }}"
            placeholder="Search by name or employee ID…"
            class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none"
        >
    </form>

    <div class="bg-white rounded-2xl soft-card border border-slate-200 overflow-hidden">
        <table class="w-full text-[12px]">
            <thead>
                <tr class="bg-[#1a3d34] text-white text-[10px] uppercase tracking-widest">
                    <th class="text-left px-4 py-3 font-black">Employee</th>
                    <th class="text-left px-4 py-3 font-black">Position</th>
                    <th class="text-left px-4 py-3 font-black">Department</th>
                    <th class="text-left px-4 py-3 font-black">Company</th>
                    <th class="text-center px-4 py-3 font-black">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($employees as $emp)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-4 py-3">
                        <p class="font-bold text-slate-800">{{ $emp['full_name'] ?? $emp['short_name'] ?? '-' }}</p>
                        <p class="text-slate-400 text-[10px]">{{ $emp['employee_id'] ?? '-' }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $emp['position'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $deptNames[$emp['department_code']] ?? $emp['department_code'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $emp['company_code'] ?? '-' }}</td>
                    <td class="px-4 py-3 text-center">
                        <form method="POST" action="{{ route('admin.view-as.start', $emp['id']) }}" onsubmit="return confirm('Open {{ addslashes($emp['full_name'] ?? $emp['short_name'] ?? 'this employee') }}\'s dashboard? This will be logged.');">
                            @csrf
                            <button type="submit" class="text-[11px] font-black px-3 py-1.5 rounded-lg bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                                View As →
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-slate-400">No employees found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
</main>
</body>
</html>
