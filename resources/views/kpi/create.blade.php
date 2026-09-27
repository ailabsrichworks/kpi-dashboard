<!DOCTYPE html>
<html>
<head>
    <title>Create KPI</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    <style>
        .category-card{
            position:relative;

            min-height:72px;

            display:flex;
            align-items:center;
            justify-content:center;

            border:1px solid var(--user-theme-accent, #D4AF37);
            border-radius:14px;

            background:#ffffff;

            font-size:14px;
            font-weight:700;

            color:#334155;

            transition:.15s ease;
        }

        .category-radio:checked + .category-card::after{
            content:'✓';
            position:absolute;
            top:8px;
            right:8px;
            width:14px;
            height:14px;
            border-radius:999px;
            background:#D4AF37;
            color:#1a1a1a;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:8px;
            font-weight:700;
        }

        .category-card:hover{
            border-color:var(--user-theme-accent, #D4AF37);
        }

        .category-radio:checked + .category-card{
            background:#FBF5EF;
            border-color:var(--user-theme-accent, #D4AF37);
            color:#7A0019;
        }

        .category-title{
            font-size:12px;
            font-weight:800;
        }

        .category-desc{
            font-size:10px;
            margin-top:2px;
            opacity:.8;
        }

        .sub-card{
            min-height:42px;
            padding:0 16px;
            display:flex;
            align-items:center;
            justify-content:center;
            border:1px solid var(--user-theme-accent, #D4AF37);
            border-radius:999px;
            background:white;
            font-size:13px;
            font-weight:600;
            color:#475569;
            transition:.15s ease;
            position:relative;
        }

        .sub-card:hover{
            transform:translateY(-1px);
            box-shadow: 0 8px 20px rgba(15,23,42,.06);
        }

        .sub-radio:checked + .sub-card{
            background:#FBF5EF;
            border-color:var(--user-theme-accent, #D4AF37);
            color:#7A0019;
            box-shadow:
                inset 0 0 0 1px var(--user-theme-accent, #D4AF37);
        }

        .sub-radio:checked + .sub-card::after{
            content:'✓';
            position:absolute;
            top:8px;
            right:8px;
            width:14px;
            height:14px;
            border-radius:999px;
            background:#D4AF37;
            color:#1a1a1a;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:8px;
        }

        :root{
            --navy:#7A0019;
            --navy-soft:#8B0020;
            --slate:#0f172a;
            --surface:#ffffff;
            --surface-soft:#f8fafc;
            --border:#e2e8f0;
            --text:#0f172a;
            --muted:#64748b;

            --brown:#6B3F2A;
            --brown-soft:#F5EAE0;

            --cyan:#06b6d4;
            --cyan-soft:#cffafe;

            --emerald:#10b981;
            --emerald-soft:#d1fae5;

            --amber:#f59e0b;
            --amber-soft:#fef3c7;

            --red:#ef4444;
            --red-soft:#fee2e2;

            --purple:#7c3aed;
            --purple-soft:#ede9fe;
        }

        body{
            background: #F5F5F3;
        }

        .glass-card{
            background:rgba(255,255,255,.86);
            backdrop-filter:blur(18px);

            border:1px solid rgba(255,255,255,.9);

            box-shadow:
                0 14px 30px rgba(15,23,42,.05);
        }

        .section-card{
            position:relative;
            background:#ffffff;
            border:1px solid var(--user-theme-accent, #D4AF37);
            border-radius:16px;
            box-shadow:0 1px 3px rgba(15,23,42,.05);
            overflow:hidden;
        }

        .section-card::before{
            content:'';
            position:absolute;
            top:0;
            left:0;

            width:100%;
            height:1px;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255,255,255,.95),
                    transparent
                );
        }

        .section-line{
            width:5px;
            border-radius:999px;

            background:
                linear-gradient(
                    180deg,
                    #7A0019 0%,
                    #D4AF37 100%
                );

            opacity:.9;
        }

        .step-bubble{
            width:32px;
            height:32px;
            border-radius:10px;

            display:flex;
            align-items:center;
            justify-content:center;

            background:#7A0019;
            color:white;

            font-size:13px;
            font-weight:700;

            box-shadow:none;
        }

        .field{
            width:100%;
            min-height:48px;
            border:1px solid var(--user-theme-accent, #D4AF37);
            background:white;
            color:#111827;
            transition:.15s ease;
            box-shadow:none;
        }

        textarea.field{
            min-height:110px;
            height:auto;
        }

        .field:hover{
            border-color:var(--user-theme-accent, #D4AF37);
            background:white;
        }

        .field:focus{
            outline:none;
            border-color:var(--user-theme-accent, #D4AF37);
            box-shadow:
                0 0 0 3px color-mix(in srgb, var(--user-theme-accent, #D4AF37) 18%, transparent);
            background:white;
        }

        .quarter-card{
            background:#ffffff;
            border:1px solid var(--user-theme-accent, #D4AF37);
            border-radius:24px;
            transition:.15s ease;
        }

        .quarter-card:hover{
            border-color:var(--user-theme-accent, #D4AF37);
            box-shadow:0 10px 25px rgba(124,58,237,.08);
        }

        .quarter-dot{
            width:44px;
            height:44px;

            border-radius:16px;

            display:flex;
            align-items:center;
            justify-content:center;

            font-weight:900;
            font-size:13px;

            color:#7A0019;

            background:
                linear-gradient(
                    135deg,
                    #FBF5EF 0%,
                    #ffffff 100%
                );

            border:1px solid var(--user-theme-accent, #D4AF37);

            box-shadow:
                0 8px 20px rgba(212,175,55,.15);
        }

        .summary-card{
            background:
                linear-gradient(
                    180deg,
                    rgba(255,255,255,.98),
                    rgba(248,250,252,.98)
                );

            border:1px solid var(--user-theme-accent, #D4AF37);

            border-radius:28px;

            box-shadow:
                0 12px 28px rgba(15,23,42,.05);
        }

        .metric-card{
            border-radius:22px;

            background:white;

            border:1px solid var(--user-theme-accent, #D4AF37);

            box-shadow:
                0 6px 18px rgba(15,23,42,.04);
        }

        .metric-brown{
            background:linear-gradient(180deg,#FBF5EF,#F5EAE0);
            border-color:#D9C4B0;
        }

        .metric-cyan{
            background:linear-gradient(180deg,#f4feff,#ecfeff);
            border-color:#cffafe;
        }

        .metric-purple{
            background:linear-gradient(180deg,#faf7ff,#f5f3ff);
            border-color:#ede9fe;
        }

        .metric-emerald{
            background:linear-gradient(180deg,#f5fffb,#ecfdf5);
            border-color:#d1fae5;
        }

        .metric-amber{
            background:linear-gradient(180deg,#fffdf5,#fffbeb);
            border-color:#fde68a;
        }

        .metric-red{
            background:linear-gradient(180deg,#fff8f8,#fef2f2);
            border-color:#fecaca;
        }

        .status-pill{
            display:inline-flex;
            align-items:center;
            justify-content:center;

            padding:.5rem .9rem;

            border-radius:999px;

            font-size:12px;
            font-weight:800;

            border:1px solid transparent;
        }

        .status-not-started{
            background:#f1f5f9;
            color:#475569;
            border-color:#e2e8f0;
        }

        .status-on-track{
            background:#d1fae5;
            border-color:#10b981;
            color:#047857;
        }

        .status-risk{
            background:#fffbeb;
            color:#b45309;
            border-color:#fde68a;
        }

        .status-trouble{
            background:#fef2f2;
            color:#b91c1c;
            border-color:#fecaca;
        }

        .status-completed{
            background:#F5EAE0;
            color:#6B3F2A;
            border-color:#D9C4B0;
        }

        .primary-btn{
            background:
                linear-gradient(
                    135deg,
                    #1A0A0A 0%,
                    #7A0019 100%
                );

            border:1px solid rgba(255,255,255,.08);

            box-shadow:
                0 14px 30px rgba(122,0,25,.25);
        }

        .primary-btn:hover{
            transform:translateY(-1px);

            box-shadow:
                0 20px 35px rgba(122,0,25,.30);
        }

        .outline-btn{
            background:white;

            border:1px solid var(--user-theme-accent, #D4AF37);

            color:#B8860B;

            transition:.18s ease;
        }

        .outline-btn:hover{
            background:#FBF5EF;
            border-color:var(--user-theme-accent, #D4AF37);
            color:#7A0019;
        }

        .info-card{
            border-radius:24px;

            border:1px solid var(--user-theme-accent, #D4AF37);

            background:
                linear-gradient(
                    180deg,
                    rgba(255,255,255,.95),
                    rgba(248,250,252,.95)
                );
        }

        .info-card-blue{
            border-color:#dbeafe;
            background:
                linear-gradient(
                    180deg,
                    #f8fbff,
                    #eff6ff
                );
        }

        .info-card-cyan{
            border-color:#cffafe;
            background:
                linear-gradient(
                    180deg,
                    #f4feff,
                    #ecfeff
                );
        }

        .info-card-emerald{
            border-color:#d1fae5;
            background:
                linear-gradient(
                    180deg,
                    #f5fffb,
                    #ecfdf5
                );
        }

        .info-card-amber{
            border-color:#fde68a;
            background:
                linear-gradient(
                    180deg,
                    #fffdf5,
                    #fffbeb
                );
        }

        .info-card-red{
            border-color:#fecaca;
            background:
                linear-gradient(
                    180deg,
                    #fff8f8,
                    #fef2f2
                );
        }

        .label{
            font-size:13px;
            font-weight:800;
            color:#334155;
            letter-spacing:.01em;
        }

        .helper{
            font-size:12px;
            color:#64748b;
            line-height:1.5;
        }

        .floating-glow{
            position:absolute;
            border-radius:999px;
            filter:blur(60px);
            opacity:.18;
            pointer-events:none;
        }

        .floating-blue{
            width:180px;
            height:180px;
            top:-40px;
            right:-40px;
            background:#D4AF37;
        }

        .floating-cyan{
            width:140px;
            height:140px;
            bottom:-40px;
            left:-40px;
            background:#7A0019;
        }

        .divider-soft{
            height:1px;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(148,163,184,.35),
                    transparent
                );
        }

        .mini-badge{
            padding:.28rem .7rem;

            border-radius:999px;

            font-size:11px;
            font-weight:800;

            background:#FBF5EF;
            color:#7A0019;

            border:1px solid var(--user-theme-accent, #D4AF37);
        }

        .sidebar-shadow{
            box-shadow:
                4px 0 25px rgba(15,23,42,.06);
        }

        .table-soft{
            border-collapse:separate;
            border-spacing:0 10px;
        }

        .table-soft tr{
            background:white;
            box-shadow:
                0 6px 18px rgba(15,23,42,.04);
        }

        .table-soft td{
            padding:16px;
        }

        .table-soft tr td:first-child{
            border-top-left-radius:18px;
            border-bottom-left-radius:18px;
        }

        .table-soft tr td:last-child{
            border-top-right-radius:18px;
            border-bottom-right-radius:18px;
        }

        .card-hover{
            transition:.18s ease;
        }

        .card-hover:hover{
            transform:translateY(-2px);
        }

        @keyframes submitGlow{
            0%, 100% {
                box-shadow: 0 4px 14px rgba(16,185,129,.35), 0 0 0 0 rgba(16,185,129,.45);
            }
            50% {
                box-shadow: 0 4px 20px rgba(16,185,129,.55), 0 0 0 8px rgba(16,185,129,0);
            }
        }

        /* Not ready yet (required fields still missing) — plain grey, still
           clickable so the browser's own "please fill this field" validation
           still fires, just doesn't look like an inviting call-to-action. */
        .submit-btn{
            background:#cbd5e1;
            color:#64748b;
        }
        .submit-btn:hover{
            background:#cbd5e1;
        }

        /* Ready to submit — every required field filled, form.checkValidity()
           passed. Green instead of the plain Accent gold so "ready" reads as
           a distinct, unmistakable state rather than just the default look. */
        .submit-btn.submit-ready{
            background:#10b981;
            color:#ffffff;
            animation: submitGlow 1.8s ease-in-out infinite;
        }
        .submit-btn.submit-ready:hover{
            background:#059669;
        }
    </style>
</head>

<body class="min-h-screen">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300">

<!-- HEADER (sticky) -->
<div class="sticky top-0 z-30 px-6 pt-4 pb-3 bg-[#F5F5F3]">
<div class="max-w-7xl mx-auto">
    <div class="relative theme-header-banner theme-page-banner rounded-[1.5rem] text-white p-4 overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <a href="{{ route('kpi.index') }}" class="relative text-xs text-[#D4AF37] hover:text-white">
            ← Back to KPI List
        </a>
    </div>
</div>
</div>

<div class="px-6 pb-6">
<div class="max-w-7xl mx-auto space-y-5">

    @if($errors->any())
        <div class="bg-red-50 text-red-700 px-4 py-3 rounded-2xl text-sm border border-red-200 shadow-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('kpi.store') }}" id="createKpiForm">
        @csrf

        <input type="hidden" name="financial_year" value="{{ $fy ?? 'FY' . now()->year }}">
        <input type="hidden" name="kpi_scope" value="individual">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            <div class="lg:col-span-8">

                    <!-- KPI OWNERSHIP & ASSIGNMENT -->

                    <section
                        id="section1"
                        class="section-card p-5 scroll-mt-24">

                    <div class="absolute top-0 right-0 w-40 h-40 bg-cyan-200/20 blur-3xl rounded-full"></div>

                    <div class="relative flex gap-4">

                        <div class="w-2 rounded-full step-accent-grad"></div>

                        <div class="flex-1">

                            <!-- HEADER -->
                            <div class="flex items-center gap-4">

                                <div class="w-10 h-10 rounded-2xl step-accent-grad text-white flex items-center justify-center font-black shadow-lg">
                                    1
                                </div>

                                <div>
                                    <h3 class="font-black text-slate-900 text-lg">
                                        KPI Ownership & Assignment
                                    </h3>

                                    <p class="text-sm text-slate-500">
                                        Tentukan siapa pemilik KPI dan siapa yang bertanggungjawab melaksanakan KPI ini.
                                    </p>
                                </div>

                            </div>

                            <!-- CONTENT -->
                            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mt-5 items-start">

                                <!-- ASSIGNMENT -->
                                <div class="xl:col-span-4">

                                    <div class="rounded-[24px] step-panel-box p-5 h-full">

                                        <div class="flex items-center gap-4">

                                            <div>

                                                <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                    EXECUTION OWNER
                                                </p>

                                                <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                    KPI Assignment
                                                </h3>

                                                <p class="text-sm text-slate-500">
                                                    Orang yang akan melaksanakan KPI ini setiap hari.
                                                </p>

                                            </div>

                                        </div>

                                        <div class="mt-6">

                                            <label class="text-sm font-bold text-slate-700">
                                                Assign To
                                            </label>

                                            <select
                                                name="assigned_employee_id"
                                                id="assignedEmployee"
                                                class="w-full mt-2 rounded-2xl assign-select-accent bg-white px-4 py-4 font-semibold text-slate-800 focus:ring-4">

                                                <option value="">
                                                    No Assignment
                                                </option>

                                                <option value="{{ $user['id'] }}">
                                                    Myself - {{ $user['short_name'] }}
                                                </option>

                                                @foreach($reportingStaff as $staff)

                                                    <option value="{{ $staff['id'] }}">
                                                        {{ $staff['short_name'] }}
                                                        ({{ $staff['role'] }})
                                                    </option>

                                                @endforeach

                                            </select>

                                        </div>

                                    </div>

                                </div>

                                <div class="xl:col-span-8">
                                    <div class="rounded-[24px] step-panel-box p-5 h-full flex flex-col">

                                        <!-- header -->
                                        <div class="flex items-start justify-between gap-3 mb-4">
                                            <div>
                                                <p class="text-[11px] uppercase tracking-wider assign-accent-text font-black">MY ASSIGNED KPI</p>
                                                <h3 class="font-black text-slate-900 text-xl mt-0.5">KPI Assigned To Me</h3>
                                            </div>
                                            @php $totalAssign = $pendingAssignments + $acceptedAssignments + $rejectedAssignments; @endphp
                                            <span class="px-3 py-1.5 rounded-xl assign-accent-badge font-black text-xs shrink-0">
                                                {{ $totalAssign }} KPI
                                            </span>
                                        </div>

                                        <!-- inline card list -->
                                        <div id="assignmentInlineList" class="space-y-2 overflow-y-auto flex-1 min-h-0 max-h-56 pr-1">

                                            @if($totalAssign === 0)
                                            <div class="flex flex-col items-center justify-center py-8 text-center">
                                                <div class="text-3xl mb-2">🎉</div>
                                                <p class="font-bold text-slate-500 text-sm">No KPI assigned to you yet.</p>
                                                <p class="text-xs text-slate-400 mt-1">Assignments will appear here.</p>
                                            </div>
                                            @else

                                            @foreach($assignmentGroups as $group)
                                                @foreach($group['kpis'] as $row)
                                                <div class="assignment-card assign-accent-card border-l-4 bg-white rounded-2xl p-3 cursor-pointer border transition-all hover:shadow-sm select-none"
                                                    data-status="{{ $row['status'] ?? 'pending' }}"
                                                    data-kpi='@json($row)'>
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="flex-1 min-w-0">
                                                            <p class="font-black text-slate-900 text-sm leading-snug">{{ $row['kpi_title'] }}</p>
                                                            <p class="text-[11px] text-slate-500 mt-0.5">{{ $row['category'] }} · {{ $row['sub_category'] }}</p>
                                                            <p class="text-[11px] text-slate-400 mt-1">From: <span class="font-bold text-slate-600">{{ $row['owner_name'] }}</span></p>
                                                        </div>
                                                        <span class="text-[9px] assign-accent-text font-bold uppercase tracking-wider shrink-0 mt-0.5">View detail →</span>
                                                    </div>
                                                </div>
                                                @endforeach
                                            @endforeach

                                            <div id="assignFilterEmpty" class="hidden py-8 text-center">
                                                <p class="text-2xl mb-1">🔍</p>
                                                <p class="text-sm font-bold text-slate-400">No KPIs in this category.</p>
                                            </div>

                                            @endif

                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </section>

                <!-- 2. KPI CATEGORY -->
                <section
                    id="section2"
                    class="section-card p-5 scroll-mt-24">
                    <div class="absolute top-0 right-0 w-40 h-40 bg-emerald-200/20 blur-3xl rounded-full"></div>

                    <div class="relative flex gap-4">

                        <div class="w-2 rounded-full step-accent-grad"></div>

                        <div class="flex-1">

                            <!-- HEADER -->
                            <div class="flex items-center gap-4">

                                <div class="w-10 h-10 rounded-2xl step-accent-grad text-white flex items-center justify-center font-black shadow-lg">
                                    2
                                </div>

                                <div>

                                    <h3 class="font-black text-slate-900 text-lg">
                                        KPI Category & Classification
                                    </h3>

                                    <p class="text-sm text-slate-500">
                                        Pilih kategori KPI dan klasifikasi yang paling tepat.
                                    </p>

                                </div>

                            </div>

                            <!-- CONTENT -->
                            <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 mt-5 items-start">

                                <!-- CATEGORY -->
                                <div class="xl:col-span-2">

                                    <div class="rounded-[24px] step-panel-box p-5 h-full">

                                        <div class="flex items-center gap-4">

                                            <div>

                                                <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                    KPI CATEGORY
                                                </p>

                                                <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                    Main Category <span class="text-red-500 text-lg">*</span>
                                                </h3>

                                                <p class="text-sm text-slate-500">
                                                    Pilih kategori KPI utama.
                                                </p>

                                            </div>

                                        </div>

                                        <div class="grid grid-cols-2 gap-3 mt-6">

                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    hidden
                                                    class="category-radio"
                                                    name="category"
                                                    value="Financial"
                                                    {{ old('category') == 'Financial' ? 'checked' : '' }}
                                                    required>

                                                <div class="category-card">
                                                    Financial
                                                </div>
                                            </label>

                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    hidden
                                                    class="category-radio"
                                                    name="category"
                                                    value="Growth & Customer"
                                                    {{ old('category') == 'Growth & Customer' ? 'checked' : '' }}
                                                    required>

                                                <div class="category-card">
                                                    Growth
                                                </div>
                                            </label>

                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    hidden
                                                    class="category-radio"
                                                    name="category"
                                                    value="Initiatives"
                                                    {{ old('category') == 'Initiatives' ? 'checked' : '' }}
                                                    required>

                                                <div class="category-card">
                                                    Initiatives
                                                </div>
                                            </label>

                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    hidden
                                                    class="category-radio"
                                                    name="category"
                                                    value="People"
                                                    {{ old('category') == 'People' ? 'checked' : '' }}
                                                    required>

                                                <div class="category-card">
                                                    People
                                                </div>
                                            </label>

                                        </div>

                                    </div>

                                </div>

                                <!-- SUB CATEGORY -->
                                <div class="xl:col-span-3">

                                    <div class="rounded-[24px] step-panel-box p-5 h-full">

                                        <div class="flex items-center gap-4">

                                            <div>

                                                <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                    KPI SUB CATEGORY
                                                </p>

                                                <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                    Classification <span class="text-red-500 text-lg">*</span>
                                                </h3>

                                                <p class="text-sm text-slate-500">
                                                    Pilih sub category selepas category dipilih.
                                                </p>

                                            </div>

                                        </div>

                                        <div
                                            id="subCategoryContainer"
                                            class="grid grid-cols-2 gap-3 mt-6">

                                            <div class="col-span-2 text-sm text-slate-400">
                                                Choose a category first
                                            </div>

                                        </div>

                                        {{-- Linkage warning banner --}}
                                        <div id="linkageWarning" class="hidden mt-3"></div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>

                <!-- 3. KPI DETAILS -->
                <section
                    id="section3"
                    class="section-card p-5 scroll-mt-24">

                <div class="absolute top-0 right-0 w-40 h-40 bg-indigo-200/20 blur-3xl rounded-full"></div>

                <div class="relative flex gap-4">

                    <div class="w-2 rounded-full step-accent-grad"></div>

                    <div class="flex-1">

                        <!-- HEADER -->
                        <div class="flex items-center gap-4">

                            <div class="w-10 h-10 rounded-2xl step-accent-grad text-white flex items-center justify-center font-black shadow-lg">
                                3
                            </div>

                            <div>

                                <h3 class="font-black text-slate-900 text-lg">
                                    KPI Details
                                </h3>

                                <p class="text-sm text-slate-500">
                                    Tetapkan KPI yang jelas, spesifik dan mudah difahami.
                                </p>

                            </div>

                        </div>

                        <!-- CONTENT -->
                        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 mt-5 items-start">

                            <!-- KPI TITLE -->
                            <div class="xl:col-span-2">

                                <div class="rounded-[24px] step-panel-box p-5 h-full">

                                    <div class="flex items-center gap-4">

                                        <div>

                                            <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                KPI TITLE
                                            </p>

                                            <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                KPI Name
                                            </h3>

                                            <p class="text-sm text-slate-500">
                                                Nama KPI yang akan diukur.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="mt-6">

                                        <label class="text-sm font-bold text-slate-700">
                                            KPI Title <span class="text-red-500">*</span>
                                        </label>

                                        <input
                                            name="kpi_title"
                                            id="kpiTitle"
                                            value="{{ old('kpi_title') }}"
                                            class="field w-full mt-2 rounded-2xl p-4"
                                            placeholder="Example: Increase Monthly Revenue by 20%"
                                            required>

                                    </div>

                                </div>

                            </div>

                            <!-- KPI DESCRIPTION -->
                            <div class="xl:col-span-3">

                                <div class="rounded-[24px] step-panel-box p-5 h-full">

                                    <div class="flex items-center gap-4">

                                        <div>

                                            <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                KPI DESCRIPTION
                                            </p>

                                            <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                KPI Explanation
                                            </h3>

                                            <p class="text-sm text-slate-500">
                                                Terangkan objektif KPI ini dan bagaimana ia diukur.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="mt-6">

                                        <label class="text-sm font-bold text-slate-700">
                                            Description <span class="text-red-500">*</span>
                                        </label>

                                        <textarea
                                            name="kpi_description"
                                            id="kpiDescription"
                                            rows="6"
                                            class="field w-full mt-2 rounded-2xl p-4"
                                            placeholder="Explain what success looks like, how this KPI will be measured and why it is important."
                                            required
                                        >{{ old('kpi_description') }}</textarea>


                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                </section>


                <!-- 4. FULL YEAR TARGET -->

                <section
                    id="section4"
                    class="section-card p-5 scroll-mt-24">
                <div class="absolute top-0 right-0 w-40 h-40 bg-sky-200/20 blur-3xl rounded-full"></div>

                <div class="relative flex gap-4">

                    <div class="w-2 rounded-full step-accent-grad"></div>

                    <div class="flex-1">

                        <!-- HEADER -->
                        <div class="flex items-center gap-4">

                            <div class="w-10 h-10 rounded-2xl step-accent-grad text-white flex items-center justify-center font-black shadow-lg">
                                4
                            </div>

                            <div>

                                <h3 class="font-black text-slate-900 text-lg">
                                    Full-Year Target
                                </h3>

                                <p class="text-sm text-slate-500">
                                    Tetapkan sasaran tahunan yang ingin dicapai.
                                </p>

                            </div>

                        </div>

                        <!-- CONTENT -->
                        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 mt-5 items-start">

                            <!-- UNIT -->
                            <div class="xl:col-span-2">

                                <div class="rounded-[24px] step-panel-box p-5 h-full">

                                    <div class="flex items-center gap-4">

                                        <div>

                                            <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                KPI UNIT
                                            </p>

                                            <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                Measurement
                                            </h3>

                                            <p class="text-sm text-slate-500">
                                                Pilih unit pengukuran KPI.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="mt-6">

                                        <label class="text-sm font-bold text-slate-700">
                                            Unit <span class="text-red-500">*</span>
                                        </label>

                                        <select
                                            name="unit"
                                            id="unit"
                                            class="field w-full mt-2 rounded-2xl p-4"
                                            required>

                                            <option value="number"
                                                {{ old('unit') === 'number' ? 'selected' : '' }}>
                                                Number
                                            </option>

                                            <option value="currency"
                                                {{ old('unit') === 'currency' ? 'selected' : '' }}>
                                                Currency / RM
                                            </option>

                                            <option value="percentage"
                                                {{ old('unit') === 'percentage' ? 'selected' : '' }}>
                                                Percentage / %
                                            </option>

                                        </select>

                                    </div>

                                </div>

                            </div>

                            <!-- TARGET -->
                            <div class="xl:col-span-3">

                                <div class="rounded-[24px] step-panel-box p-5 h-full">

                                    <div class="flex items-center gap-4">

                                        <div>

                                            <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                TARGET SETTING
                                            </p>

                                            <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                Annual Target
                                            </h3>

                                            <p class="text-sm text-slate-500">
                                                Tetapkan Base dan Stretch Target KPI.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="grid md:grid-cols-2 gap-4 mt-6">

                                        <div>

                                            <label class="text-sm font-bold text-slate-700">
                                                Base Target <span class="text-red-500">*</span>
                                            </label>

                                            <input
                                                name="base_target"
                                                id="baseTarget"
                                                type="number"
                                                step="0.01"
                                                value="{{ old('base_target') }}"
                                                class="field w-full mt-2 rounded-2xl p-4"
                                                required>

                                        </div>

                                        <div>

                                            <label class="text-sm font-bold text-slate-700">
                                                Stretch Target <span class="text-red-500">*</span>
                                            </label>

                                            <input
                                                name="stretch_target"
                                                id="stretchTarget"
                                                type="number"
                                                step="0.01"
                                                value="{{ old('stretch_target') }}"
                                                class="field w-full mt-2 rounded-2xl p-4"
                                                required>

                                        </div>

                                    </div>

                                    <div class="mt-4 rounded-xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-3">

                                        <p class="text-xs text-[#6B3F2A]">
                                            Base Target ialah sasaran minimum yang wajib dicapai. Stretch Target ialah sasaran lebih tinggi yang menunjukkan prestasi cemerlang.
                                        </p>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                </section>


                <!-- 5. CURRENT STATUS -->
                <section
                    id="section5"
                    class="section-card p-5 scroll-mt-24">

                <div class="absolute top-0 right-0 w-40 h-40 bg-amber-200/20 blur-3xl rounded-full"></div>

                <div class="relative flex gap-4">

                    <div class="w-2 rounded-full step-accent-grad"></div>

                    <div class="flex-1">

                        <!-- HEADER -->
                        <div class="flex items-center gap-4">

                            <div class="w-10 h-10 rounded-2xl step-accent-grad text-white flex items-center justify-center font-black shadow-lg">
                                5
                            </div>

                            <div>

                                <h3 class="font-black text-slate-900 text-lg">
                                    Current Status & Remark
                                </h3>

                                <p class="text-sm text-slate-500">
                                    Nyatakan status KPI semasa dan sebarang maklumat tambahan yang berkaitan.
                                </p>

                            </div>

                        </div>

                        <!-- CONTENT -->
                        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 mt-5 items-start">

                            <!-- STATUS -->
                            <div class="xl:col-span-2">

                                <div class="rounded-[24px] step-panel-box p-5 h-full">

                                    <div class="flex items-center gap-4">

                                        <div>

                                            <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                KPI STATUS
                                            </p>

                                            <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                Current Status
                                            </h3>

                                            <p class="text-sm text-slate-500">
                                                Pilih keadaan KPI pada masa ini.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="mt-6">

                                        <label class="text-sm font-bold text-slate-700">
                                            Status <span class="text-red-500">*</span>
                                        </label>

                                        <select
                                            name="status"
                                            id="status"
                                            class="w-full mt-2 rounded-2xl border border-slate-200 bg-white px-4 py-4 font-bold transition-all duration-200"
                                            required>

                                            <option value="not_started"
                                                {{ old('status', 'not_started') === 'not_started' ? 'selected' : '' }}>
                                                Not Started
                                            </option>

                                            <option value="on_track"
                                                {{ old('status') === 'on_track' ? 'selected' : '' }}>
                                                On Track
                                            </option>

                                            <option value="at_risk"
                                                {{ old('status') === 'at_risk' ? 'selected' : '' }}>
                                                At Risk
                                            </option>

                                            <option value="in_trouble"
                                                {{ old('status') === 'in_trouble' ? 'selected' : '' }}>
                                                In Trouble
                                            </option>

                                            <option value="completed"
                                                {{ old('status') === 'completed' ? 'selected' : '' }}>
                                                Completed
                                            </option>

                                        </select>

                                    </div>

                                </div>

                            </div>

                            <!-- REMARK -->
                            <div class="xl:col-span-3">

                                <div class="rounded-[24px] step-panel-box p-5 h-full">

                                    <div class="flex items-center gap-4">

                                        <div>

                                            <p class="text-[11px] uppercase tracking-wider eyebrow-accent-text font-black">
                                                KPI REMARK
                                            </p>

                                            <h3 class="font-black text-slate-900 text-2xl mt-1">
                                                Additional Context
                                            </h3>

                                            <p class="text-sm text-slate-500">
                                                Catatan tambahan berkaitan KPI ini.
                                            </p>

                                        </div>

                                    </div>

                                    <div class="mt-6">

                                        <label class="text-sm font-bold text-slate-700">
                                            Remark
                                        </label>

                                        <textarea
                                            name="remark"
                                            id="remark"
                                            rows="6"
                                            class="field w-full mt-2 rounded-2xl p-4"
                                            placeholder="Optional note, context, challenge, assumption or additional information."
                                        >{{ old('remark') }}</textarea>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                </section>


                <!-- 6. QUARTERS -->
                <section
                    id="section6"
                    class="section-card p-5 scroll-mt-24">

                    <div class="absolute top-0 right-0 w-40 h-40 bg-purple-200/20 blur-3xl rounded-full"></div>

                    <div class="relative flex gap-4">

                        <div class="w-2 rounded-full step-accent-grad"></div>

                        <div class="flex-1">

                            <!-- HEADER -->
                            <div class="flex items-center justify-between gap-4">

                                <div class="flex items-center gap-4">

                                    <div class="w-10 h-10 rounded-2xl step-accent-grad text-white flex items-center justify-center font-black shadow-lg">
                                        6
                                    </div>

                                    <div>

                                        <h3 class="font-black text-slate-900 text-lg">
                                            Quarter Breakdown
                                        </h3>

                                    </div>

                                </div>

                                <button
                                    type="button"
                                    onclick="autoDivideQuarter()"
                                    class="outline-btn px-5 py-3 rounded-2xl text-sm font-black">
                                    Auto Fill Annual
                                </button>

                            </div>

                            <div class="mt-5 space-y-4">

                            <div class="space-y-4">

                                @foreach(['Q1','Q2','Q3','Q4'] as $quarter)

                                    <div class="rounded-[24px] step-panel-box p-5">

                                        <div class="flex items-center justify-between gap-4 mb-5">

                                            <div class="flex items-center gap-3">

                                                <div class="quarter-dot">
                                                    {{ $quarter }}
                                                </div>

                                                <div>
                                                    <h3 class="font-black text-slate-900">
                                                        {{ $quarter }} Planning
                                                    </h3>

                                                    <p class="helper">
                                                        Specific target, timeline dan action plan.
                                                    </p>
                                                </div>

                                            </div>

                                            <div class="mini-badge">
                                                Quarterly Execution
                                            </div>

                                        </div>

                                        <input
                                            type="hidden"
                                            name="quarters[{{ $quarter }}][quarter]"
                                            value="{{ $quarter }}"
                                        >

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                                            <!-- TITLE -->
                                            <div>

                                                <label class="label">
                                                    Quarter Title <span class="text-red-500">*</span>
                                                </label>

                                                <input
                                                    name="quarters[{{ $quarter }}][quarter_title]"
                                                    value="{{ old("quarters.$quarter.quarter_title") }}"
                                                    class="field mt-2 rounded-2xl px-4 py-3 text-sm"
                                                    placeholder="Example: Launch AI KPI dashboard V1"
                                                    required
                                                >

                                            </div>

                                            <!-- STATUS -->
                                            <div>

                                                <label class="label">
                                                    Quarter Status <span class="text-red-500">*</span>
                                                </label>

                                                <select
                                                    name="quarters[{{ $quarter }}][status]"
                                                    class="field mt-2 rounded-2xl px-4 py-3 text-sm"
                                                    required
                                                >

                                                    <option value="not_started"
                                                        {{ old("quarters.$quarter.status",'not_started') === 'not_started' ? 'selected' : '' }}>
                                                        Not Started
                                                    </option>

                                                    <option value="on_track"
                                                        {{ old("quarters.$quarter.status") === 'on_track' ? 'selected' : '' }}>
                                                        On Track
                                                    </option>

                                                    <option value="at_risk"
                                                        {{ old("quarters.$quarter.status") === 'at_risk' ? 'selected' : '' }}>
                                                        At Risk
                                                    </option>

                                                    <option value="in_trouble"
                                                        {{ old("quarters.$quarter.status") === 'in_trouble' ? 'selected' : '' }}>
                                                        In Trouble
                                                    </option>

                                                    <option value="completed"
                                                        {{ old("quarters.$quarter.status") === 'completed' ? 'selected' : '' }}>
                                                        Completed
                                                    </option>

                                                </select>

                                            </div>

                                            <!-- DESCRIPTION -->
                                            <div class="md:col-span-2">

                                                <label class="label">
                                                    Quarter Description <span class="text-red-500">*</span>
                                                </label>

                                                <textarea
                                                    name="quarters[{{ $quarter }}][quarter_description]"
                                                    rows="3"
                                                    class="field mt-2 rounded-2xl px-4 py-3 text-sm"
                                                    placeholder="Explain the focus and expected outcome for this quarter."
                                                    required
                                                >{{ old("quarters.$quarter.quarter_description") }}</textarea>

                                            </div>

                                            <!-- TARGET -->
                                            <div>

                                                <label class="label">
                                                    Quarter Target <span class="text-red-500">*</span>
                                                </label>

                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="quarters[{{ $quarter }}][quarter_target]"
                                                    value="{{ old("quarters.$quarter.quarter_target") }}"
                                                    class="quarter-target field mt-2 rounded-2xl px-4 py-3 text-sm"
                                                    required
                                                >

                                            </div>

                                            <!-- TIMELINE (AUTO LOCKED) -->
                                            @php

                                                $year =
                                                    str_replace(
                                                        'FY',
                                                        '',
                                                        $fy ?? ('FY'.now()->year)
                                                    );

                                                $quarterDates = [

                                                    'Q1'=>[
                                                        'start'=>$year.'-01-01',
                                                        'end'=>$year.'-03-31'
                                                    ],

                                                    'Q2'=>[
                                                        'start'=>$year.'-04-01',
                                                        'end'=>$year.'-06-30'
                                                    ],

                                                    'Q3'=>[
                                                        'start'=>$year.'-07-01',
                                                        'end'=>$year.'-09-30'
                                                    ],

                                                    'Q4'=>[
                                                        'start'=>$year.'-10-01',
                                                        'end'=>$year.'-12-31'
                                                    ]

                                                ];

                                                $timeline =
                                                    $quarterDates[$quarter];

                                                @endphp

                                                <div class="md:col-span-2">

                                                    <label class="label">
                                                        Timeline <span class="text-red-500">*</span>
                                                    </label>

                                                    <div class="grid grid-cols-2 gap-3 mt-2">

                                                        <div>

                                                            <input
                                                                type="date"
                                                                name="quarters[{{ $quarter }}][start_date]"
                                                                value="{{ old("quarters.$quarter.start_date",$timeline['start']) }}"
                                                                min="{{ $timeline['start'] }}"
                                                                max="{{ $timeline['end'] }}"
                                                                class="field rounded-2xl px-4 py-3 text-sm"
                                                                required
                                                            >

                                                        </div>

                                                        <div>

                                                            <input
                                                                type="date"
                                                                name="quarters[{{ $quarter }}][end_date]"
                                                                value="{{ old("quarters.$quarter.end_date",$timeline['end']) }}"
                                                                min="{{ $timeline['start'] }}"
                                                                max="{{ $timeline['end'] }}"
                                                                class="field rounded-2xl px-4 py-3 text-sm"
                                                                required
                                                            >

                                                        </div>

                                                    </div>

                                                    <p class="text-xs text-purple-600 mt-2">

                                                        Allowed Range:
                                                        {{ \Carbon\Carbon::parse($timeline['start'])->format('d/m/Y') }}
                                                        →
                                                        {{ \Carbon\Carbon::parse($timeline['end'])->format('d/m/Y') }}

                                                    </p>

                                                </div>

                                            <!-- REMARK -->
                                            <div class="md:col-span-2">

                                                <label class="label">
                                                    Quarter Remark
                                                </label>

                                                <textarea
                                                    name="quarters[{{ $quarter }}][remark]"
                                                    rows="2"
                                                    class="field mt-2 rounded-2xl px-4 py-3 text-sm"
                                                    placeholder="Optional internal notes"
                                                >{{ old("quarters.$quarter.remark") }}</textarea>

                                            </div>

                                        </div>

                                    </div>

                                @endforeach

                            </div>

                            <!-- SUMMARY -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">

                                <div class="metric-card metric-blue p-5">

                                    <p class="quarter-total-label text-xs font-bold text-[#8B5E4A] uppercase tracking-wide">
                                        Quarter Target Total
                                    </p>

                                    <p
                                        id="summaryQuarterTargetTotal"
                                        class="text-2xl font-black text-[#7A0019] mt-2"
                                    >
                                        0.00
                                    </p>

                                    <p id="quarterTotalHint" class="text-xs font-bold mt-1"></p>

                                </div>


                            </div>

                        </div>

                    </div>

                </section>

            </div>

            <!-- KPI SUMMARY -->
            <aside class="lg:col-span-4">

                <div
                    class="sticky top-[160px]
                        max-h-[calc(100vh-176px)]
                        overflow-y-auto
                        rounded-[28px]
                        border accent-border
                        bg-white
                        p-4
                        shadow-[0_20px_50px_rgba(15,23,42,0.08)]">

                    <!-- HEADER -->
                    <div class="flex items-center gap-3">

                        <div
                            class="w-9 h-9
                                rounded-xl
                                accent2-fill
                                text-white
                                flex
                                items-center
                                justify-center
                                font-black
                                shadow-lg
                                flex-shrink-0">
                            ✓
                        </div>

                        <div>
                            <h2 class="font-black text-sm text-slate-900">
                                KPI Summary
                            </h2>

                            <p class="text-[11px] text-slate-500">
                                Semak sebelum submit.
                            </p>
                        </div>

                    </div>

                    <!-- DIVIDER -->
                    <div class="h-px bg-slate-200 my-3"></div>

                    <!-- OWNER -->
                    <div
                        class="rounded-xl
                            bg-slate-50
                            border
                            accent-border
                            px-3 py-2">

                        <p class="text-[10px] text-slate-400">
                            Owner
                        </p>

                        <p class="font-black text-sm accent-value-text mt-0.5">
                            {{ $user['short_name'] ?? '-' }}
                        </p>

                    </div>

                    <!-- TITLE -->
                    <div class="mt-2.5">

                        <p class="text-[10px] text-slate-400">
                            KPI Title
                        </p>

                        <p
                            id="summaryTitle"
                            class="font-black text-sm accent-value-text mt-0.5 line-clamp-2">
                            Not entered yet
                        </p>

                    </div>

                    <!-- CATEGORY -->
                    <div class="grid grid-cols-2 gap-2 mt-2.5">

                        <div
                            class="rounded-xl
                                border
                                accent-border
                                px-2.5 py-2">

                            <p class="text-[10px] text-[#8B5E4A]">
                                Category
                            </p>

                            <p
                                id="summaryCategory"
                                class="font-bold text-sm accent-value-text mt-0.5">
                                -
                            </p>

                        </div>

                        <div
                            class="rounded-xl
                                border
                                accent-border
                                px-2.5 py-2">

                            <p class="text-[10px] text-[#8B5E4A]">
                                Sub Category
                            </p>

                            <p
                                id="summarySubCategory"
                                class="font-bold text-sm accent-value-text mt-0.5">
                                -
                            </p>

                        </div>

                    </div>

                    <!-- TARGET -->
                    <div class="grid grid-cols-2 gap-2 mt-2.5">

                        <div
                            class="rounded-xl
                                border
                                accent-border
                                bg-white
                                px-3 py-2">

                            <p class="text-[10px] text-slate-400">
                                Base Target
                            </p>

                            <p
                                id="summaryBase"
                                class="font-black text-base accent-value-text mt-0.5">
                                0.00
                            </p>

                        </div>

                        <div
                            class="rounded-xl
                                border
                                accent-border
                                bg-white
                                px-3 py-2">

                            <p class="text-[10px] text-slate-400">
                                Stretch Target
                            </p>

                            <p
                                id="summaryStretch"
                                class="font-black text-base accent-value-text mt-0.5">
                                0.00
                            </p>

                        </div>

                    </div>

                    <!-- STATUS -->
                    <div class="rounded-xl border accent-border px-3 py-2 mt-2.5">

                        <p class="text-[10px] uppercase tracking-wider text-amber-600 font-black">
                            CURRENT STATUS
                        </p>

                        <div class="mt-0.5">

                            <p
                                id="summaryStatus"
                                class="font-black text-sm">

                                <span class="text-slate-500">
                                    Not Started
                                </span>

                            </p>

                        </div>

                    </div>
                    <!-- QUARTER TOTAL -->
                    <div
                        class="mt-2.5
                            rounded-xl
                            border
                            accent-border
                            px-3 py-2">

                        <p class="quarter-total-label text-[10px] text-[#8B5E4A]">
                            Quarter Target Total
                        </p>

                        <p
                            id="sidebarQuarterTargetTotal"
                            class="text-base font-black accent-value-text mt-0.5">
                            0.00
                        </p>

                    </div>


                    <!-- LINKED TARGET (shown when sub_category has a linkage) -->
                    <div id="summaryLinkage"
                         style="display:none;margin-top:10px;padding:8px 12px;border-radius:14px;border:1.5px solid #fcd34d;background:#fffbeb;">
                    </div>

                    <!-- AI SCORE -->
                    <div class="mt-2.5 rounded-xl border border-violet-100 bg-gradient-to-br from-violet-50 to-white px-3 py-2">

                        <div class="flex items-center justify-between">

                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-violet-500 font-black">ANIRA Score</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">AI quality check</p>
                            </div>

                            <button
                                type="button"
                                id="aiScoreBtn"
                                onclick="aiScoreDescription()"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-[11px] font-bold transition flex-shrink-0"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                <span id="aiScoreLabel">Score</span>
                            </button>

                        </div>

                        <!-- Result -->
                        <div id="aiScoreBadge" class="hidden mt-2">

                            <div class="flex items-center gap-3">

                                <div id="aiScoreCircle" class="w-10 h-10 rounded-xl flex flex-col items-center justify-center text-white font-black flex-shrink-0">
                                    <span id="aiScoreNumber" class="text-sm leading-none"></span>
                                    <span class="text-[9px] font-medium opacity-80">/10</span>
                                </div>

                                <p id="aiScoreFeedback" class="text-[11px] text-slate-500 leading-snug"></p>

                            </div>

                        </div>

                        <p id="aiSuggestError" class="hidden mt-2 text-xs text-red-500 font-medium"></p>

                    </div>

                    <!-- SUBMIT -->
                    <div class="mt-3">

                        <button
                            type="submit"
                            id="createKpiSubmitBtn"
                            class="w-full
                                submit-btn
                                font-black
                                py-3
                                rounded-xl
                                transition
                                shadow-lg
                                hover:-translate-y-0.5">

                            Create My KPI

                        </button>

                    </div>

                </div>

            </aside>

        </div>
    </form>

</div>
</div>

</main>

<script>
    /*
    |--------------------------------------------------------------------------
    | SUB CATEGORY MAP
    |--------------------------------------------------------------------------
    */

    const subCategories = {
        "Financial": [
            "Revenue",
            "Operating Cost Optimisation"
        ],

        "Growth & Customer": [
            "New Customer Acquisition",
            "Growth"
        ],

        "Initiatives": [
            "Continuous Improvement & New Business"
        ],

        "People": [
            "Certification of Competence (COC)",
            "Staff Development"
        ]
    };

    /*
    |--------------------------------------------------------------------------
    | ELEMENTS
    |--------------------------------------------------------------------------
    */

    const form = document.getElementById('createKpiForm');

    const categoryInputs = document.querySelectorAll('input[name="category"]');
    const subCategoryContainer = document.getElementById('subCategoryContainer');

    const unitInput = document.getElementById('unit');

    const kpiTitle = document.getElementById('kpiTitle');

    const baseTarget = document.getElementById('baseTarget');
    const stretchTarget = document.getElementById('stretchTarget');

    const statusInput = document.getElementById('status');

    const quarterTargetInputs = document.querySelectorAll('.quarter-target');

    /*
    |--------------------------------------------------------------------------
    | SUB CATEGORY
    |--------------------------------------------------------------------------
    */

    function updateSubCategories(){
        const selectedCategory = document.querySelector('input[name="category"]:checked')?.value || '';
        const oldSubCategory = @json(old('sub_category'));

        subCategoryContainer.innerHTML='';

        if(
            !selectedCategory || !subCategories[selectedCategory]
        ){
            subCategoryContainer.innerHTML= `<div class="text-xs text-slate-400">Choose a category</div>`;
            updateSummary();
            return;
        }

        subCategories[selectedCategory]
        .forEach(subCategory=>{
            const lnk = linkageMap[subCategory];
            const hasLink = !!lnk;
            const linkMet = hasLink && lnk.met;

            let linkBadge = '';
            if (hasLink) {
                linkBadge = linkMet
                    ? `<div style="margin-top:6px;font-size:9px;font-weight:800;color:#059669;display:flex;align-items:center;gap:3px;">
                           <span>🔗</span><span>Target Met ✓</span>
                       </div>`
                    : `<div style="margin-top:6px;font-size:9px;font-weight:800;color:#b45309;display:flex;align-items:center;gap:3px;">
                           <span>🔗</span><span>Gap: ${fmtLinkVal(lnk.gap, lnk.unit)}</span>
                       </div>`;
            }

            const extraStyle = hasLink
                ? (linkMet
                    ? 'border-color:#6ee7b7 !important; background:linear-gradient(135deg,#f0fdf4,#fff) !important;'
                    : 'border-color:#fcd34d !important; background:linear-gradient(135deg,#fffbeb,#fff) !important;')
                : '';

            subCategoryContainer.innerHTML +=
            `<label class="cursor-pointer">
                <input type="radio" hidden class="sub-radio" name="sub_category"
                    value="${subCategory}"
                    required
                    ${oldSubCategory === subCategory ? 'checked' : ''}
                >
                <div class="sub-card" style="${extraStyle}">
                    <div>
                        <div class="font-semibold text-sm text-slate-800">${subCategory}</div>
                        ${linkBadge}
                    </div>
                </div>
            </label>
            `;
        });

        bindSubCategoryEvents();
        updateSummary();
    }

    function bindSubCategoryEvents(){
        document.querySelectorAll('input[name="sub_category"]')
        .forEach(input=>{
            input.addEventListener('change', () => { updateSummary(); updateLinkageWarning(); });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | LINKAGE WARNING
    |--------------------------------------------------------------------------
    */

    const linkageMap = @json($linkageMap ?? []);

    function fmtLinkVal(v, u) {
        const n = Number(v) || 0;
        if (u === 'currency')   return 'RM ' + n.toLocaleString('en-MY', {maximumFractionDigits:0});
        if (u === 'percentage') return n.toFixed(1) + '%';
        return n.toLocaleString('en-MY', {maximumFractionDigits:0});
    }

    function updateLinkageWarning() {
        const box = document.getElementById('linkageWarning');
        if (!box) return;
        const sub = document.querySelector('input[name="sub_category"]:checked')?.value;
        if (!sub || !linkageMap[sub]) {
            box.classList.add('hidden');
            box.innerHTML = '';
            updateSummaryLinkage(null, null);
            return;
        }
        const lnk = linkageMap[sub];
        const met  = lnk.met;
        const pct  = lnk.pct;
        const barW = Math.min(pct, 100);

        // colour scheme
        const c = met
            ? { border:'#6ee7b7', bg:'#f0fdf4', title:'#065f46', sub:'#059669', bar:'#34d399', badge:'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7' }
            : { border:'#fcd34d', bg:'#fffbeb', title:'#92400e', sub:'#b45309', bar:'#fbbf24', badge:'background:#fef3c7;color:#92400e;border:1px solid #fcd34d' };

        box.className = '';
        box.style.cssText = `margin-top:16px;border-radius:16px;border:1.5px solid ${c.border};background:${c.bg};overflow:hidden;`;
        box.innerHTML = `
            <div style="padding:14px 16px 0">
                <div style="display:flex;align-items:flex-start;gap:10px;">
                    <div style="font-size:22px;line-height:1;margin-top:2px;">${met ? '✅' : '⚠️'}</div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:900;color:${c.title};margin:0 0 2px;">
                            Cascading Target dari ${lnk.assigner_name ?? 'Ketua Anda'}
                        </p>
                        <p style="font-size:11px;color:${c.sub};margin:0;">
                            ${lnk.category} › <strong>${sub}</strong> &nbsp;—&nbsp;
                            Annual target: <strong>${fmtLinkVal(lnk.target, lnk.unit)}</strong>
                        </p>
                    </div>
                    <span style="font-size:9px;font-weight:800;padding:3px 8px;border-radius:999px;white-space:nowrap;${c.badge}">
                        ${met ? 'Met ✓' : 'Belum Cukup'}
                    </span>
                </div>

                <!-- Progress bar -->
                <div style="margin:12px 0 0;">
                    <div style="display:flex;justify-content:space-between;font-size:10px;font-weight:700;color:${c.sub};margin-bottom:4px;">
                        <span>Coverage Progress</span>
                        <span>${pct}%</span>
                    </div>
                    <div style="height:8px;background:rgba(0,0,0,.07);border-radius:999px;overflow:hidden;">
                        <div style="height:100%;width:${barW}%;background:${c.bar};border-radius:999px;transition:width .4s;"></div>
                    </div>
                </div>

                <!-- Stats row -->
                <div style="display:grid;grid-template-columns:1fr 1fr ${met ? '' : '1fr'};gap:10px;margin:12px 0 0;padding:10px 0;border-top:1px solid ${c.border};">
                    <div>
                        <p style="font-size:9px;font-weight:800;color:${c.sub};text-transform:uppercase;letter-spacing:.05em;margin:0 0 2px;">Target dari Boss</p>
                        <p style="font-size:15px;font-weight:900;color:${c.title};margin:0;">${fmtLinkVal(lnk.target, lnk.unit)}</p>
                    </div>
                    <div>
                        <p style="font-size:9px;font-weight:800;color:${c.sub};text-transform:uppercase;letter-spacing:.05em;margin:0 0 2px;">Covered by Your KPIs</p>
                        <p style="font-size:15px;font-weight:900;color:${c.title};margin:0;">${fmtLinkVal(lnk.covered, lnk.unit)}</p>
                    </div>
                    ${!met ? `<div>
                        <p style="font-size:9px;font-weight:800;color:#b45309;text-transform:uppercase;letter-spacing:.05em;margin:0 0 2px;">Masih Kurang</p>
                        <p style="font-size:15px;font-weight:900;color:#92400e;margin:0;">${fmtLinkVal(lnk.gap, lnk.unit)}</p>
                    </div>` : ''}
                </div>
            </div>
            ${!met ? `<div style="background:#fef3c7;padding:8px 16px;border-top:1px solid #fcd34d;">
                <p style="font-size:10px;color:#92400e;margin:0;">
                    💡 KPI ini akan dikira sebagai coverage untuk target yang ditetapkan oleh ${lnk.assigner_name ?? 'ketua anda'}. Anda masih boleh create KPI ini.
                </p>
            </div>` : `<div style="background:#d1fae5;padding:8px 16px;border-top:1px solid #6ee7b7;">
                <p style="font-size:10px;color:#065f46;margin:0;">
                    ✓ Target telah dipenuhi. KPI ini akan menambah lebih coverage untuk sub-category ini.
                </p>
            </div>`}
        `;
        box.classList.remove('hidden');
        updateSummaryLinkage(sub, lnk);
    }

    function updateSummaryLinkage(sub, lnk) {
        let box = document.getElementById('summaryLinkage');
        if (!box) return;
        if (!sub || !lnk) { box.style.display = 'none'; return; }
        const met = lnk.met;
        box.style.display = '';
        box.innerHTML = `
            <p style="font-size:10px;font-weight:800;color:${met ? '#059669' : '#b45309'};text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">
                🔗 Linked Target
            </p>
            <p style="font-size:11px;font-weight:700;color:#0f172a;margin:0 0 2px;">${sub} — ${fmtLinkVal(lnk.target, lnk.unit)}</p>
            <p style="font-size:10px;color:#64748b;margin:0 0 6px;">dari ${lnk.assigner_name ?? '-'}</p>
            <div style="height:5px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                <div style="height:100%;width:${Math.min(lnk.pct,100)}%;background:${met ? '#34d399' : '#fbbf24'};border-radius:999px;"></div>
            </div>
            <p style="font-size:10px;font-weight:700;color:${met ? '#059669' : '#b45309'};margin:4px 0 0;text-align:right;">
                ${lnk.pct}% covered ${met ? '✓' : `· Gap ${fmtLinkVal(lnk.gap, lnk.unit)}`}
            </p>
        `;
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT VALUE
    |--------------------------------------------------------------------------
    */

    function formatValue(value) {

        const number = Number(value || 0);

        if (unitInput.value === 'currency') {

            return 'RM ' + number.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

        }

        if (unitInput.value === 'percentage') {

            return number.toFixed(2) + '%';

        }

        return number.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /*
    |--------------------------------------------------------------------------
    | QUARTER TOTAL
    |--------------------------------------------------------------------------
    */

    function updateQuarterTotals() {
        const isPercentage = unitInput.value === 'percentage';

        let targetTotal = 0;
        quarterTargetInputs.forEach(input => {
            targetTotal += Number(input.value || 0);
        });

        // For percentage: annual = average of quarters; for others: annual = sum
        const displayValue = isPercentage ? targetTotal / 4 : targetTotal;

        const base    = Number(baseTarget.value || 0);
        const matched = base > 0 && Math.abs(displayValue - base) < 0.01;
        const totalText = formatValue(displayValue);

        // Update dynamic labels
        document.querySelectorAll('.quarter-total-label').forEach(el => {
            el.textContent = isPercentage ? 'Quarter Target Average' : 'Quarter Target Total';
        });

        const summaryEl = document.getElementById('summaryQuarterTargetTotal');
        const sidebarEl = document.getElementById('sidebarQuarterTargetTotal');

        if(summaryEl){
            summaryEl.textContent = totalText;
            summaryEl.className = matched
                ? 'text-2xl font-black mt-2 text-emerald-600'
                : (displayValue > 0 ? 'text-2xl font-black mt-2 text-red-500' : 'text-2xl font-black mt-2 text-[#7A0019]');
        }

        if(sidebarEl){
            sidebarEl.textContent = totalText;
            sidebarEl.className = matched
                ? 'font-black text-emerald-600'
                : (displayValue > 0 ? 'font-black text-red-500' : 'font-black');
        }

        const hintEl = document.getElementById('quarterTotalHint');
        if(hintEl){
            if(base <= 0){
                hintEl.textContent = '';
            } else if(matched){
                hintEl.textContent = isPercentage ? '✓ Average matches Annual Target' : '✓ Matches Base Target';
                hintEl.className = 'text-xs font-bold text-emerald-600 mt-1';
            } else {
                hintEl.textContent = isPercentage
                    ? `Average must equal Annual Target (${formatValue(base)})`
                    : `Must equal Base Target (${formatValue(base)})`;
                hintEl.className = 'text-xs font-bold text-red-500 mt-1';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS BADGE
    |--------------------------------------------------------------------------
    */

    function updateStatusBadge()
    {
        const indicator =
            document.getElementById(
                'summaryStatus'
            );

        if(!indicator){
            return;
        }

        switch(statusInput.value)
        {
            case 'on_track':

                indicator.innerHTML =
                    '<span class="text-[#6B3F2A]">On Track</span>';

                break;

            case 'at_risk':

                indicator.innerHTML =
                    '<span class="text-orange-500">At Risk</span>';

                break;

            case 'in_trouble':

                indicator.innerHTML =
                    '<span class="text-red-600">In Trouble</span>';

                break;

            case 'completed':

                indicator.innerHTML =
                    '<span class="text-emerald-600">Completed</span>';

                break;

            default:

                indicator.innerHTML =
                    '<span class="text-slate-500">Not Started</span>';
        }
    }
    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    function updateSummary() {

        document.getElementById('summaryTitle')
            .textContent =
                kpiTitle.value || 'Not entered yet';

        const selectedCategory =
            document.querySelector(
                'input[name="category"]:checked'
            )?.value || '-';

            document.getElementById(
                'summaryCategory'
            ).textContent =
            selectedCategory;

        document.getElementById('summarySubCategory').textContent =
        document.querySelector('input[name="sub_category"]:checked')?.value || '-';

        document.getElementById('summaryBase')
            .textContent =
                formatValue(baseTarget.value);

        document.getElementById('summaryStretch')
            .textContent =
                formatValue(stretchTarget.value);

        updateQuarterTotals();
        updateStatusBadge();
        updateStatusTheme();
        updateCompletion();
    }

    /*
    |--------------------------------------------------------------------------
    | AUTO DIVIDE
    |--------------------------------------------------------------------------
    */

    function autoDivideQuarter() {

        const base = Number(baseTarget.value || 0);
        const isPercentage = unitInput.value === 'percentage';

        if (base <= 0) {
            alert('Please fill Base Target first.');
            return;
        }

        // Percentage: each quarter = base target so average = base target
        // Others: divide evenly across 4 quarters so sum = base target
        const perQuarter = isPercentage ? base.toFixed(2) : (base / 4).toFixed(2);

        quarterTargetInputs.forEach(input => {
            input.value = perQuarter;
        });

        updateSummary();
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE FORM
    |--------------------------------------------------------------------------
    */

    form.addEventListener('submit', function (event) {

        const base =
            Number(baseTarget.value || 0);

        const stretch =
            Number(stretchTarget.value || 0);

        if (stretch < base) {

            event.preventDefault();

            alert(
                'Stretch Target must be greater than or equal to Base Target.'
            );

            return;
        }

        const isPercentage = unitInput.value === 'percentage';
        const quarterTotal = Array.from(quarterTargetInputs)
            .reduce((sum, i) => sum + Number(i.value || 0), 0);
        const compareValue = isPercentage ? quarterTotal / 4 : quarterTotal;

        if (base > 0 && Math.abs(compareValue - base) > 0.01) {

            event.preventDefault();

            if (isPercentage) {
                alert(
                    `Quarter Target average (${(quarterTotal / 4).toFixed(2)}%) must equal Annual Target (${base.toFixed(2)}%).\n\nPlease adjust your quarter targets or use Auto Fill Annual.`
                );
            } else {
                alert(
                    `Quarter Target total (${quarterTotal.toFixed(2)}) must equal Base Target (${base.toFixed(2)}).\n\nPlease adjust your quarter targets or use Auto Fill Annual.`
                );
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE SUBMIT
        |--------------------------------------------------------------------------
        */

        const submitButton =
            form.querySelector(
                'button[type="submit"]'
            );

        if(submitButton){

            submitButton.disabled = true;

            submitButton.innerHTML =
                'Creating KPI...';

        }

    });

    /*
    |--------------------------------------------------------------------------
    | EVENTS
    |--------------------------------------------------------------------------
    */

    categoryInputs.forEach(input => {

        input.addEventListener(
            'change',
            updateSubCategories
        );

    });

    unitInput.addEventListener(
        'change',
        updateSummary
    );

    kpiTitle.addEventListener(
        'input',
        updateSummary
    );

    baseTarget.addEventListener(
        'input',
        updateSummary
    );

    stretchTarget.addEventListener(
        'input',
        updateSummary
    );

    statusInput.addEventListener(
        'change',
        updateSummary
    );

    quarterTargetInputs.forEach(input => {
        input.addEventListener('input', updateSummary);
    });

    // Quarter title/description/dates aren't wired to updateSummary() above,
    // but they're still required fields — catch them via delegation so the
    // submit button's glow state (form.checkValidity()) stays accurate.
    form.addEventListener('input', updateCompletion);
    form.addEventListener('change', updateCompletion);

    /*
    |--------------------------------------------------------------------------
    | INITIALIZE
    |--------------------------------------------------------------------------
    */

    function updateStatusTheme() {

        const status =
            statusInput.value;

        statusInput.className =
            'w-full mt-2 rounded-2xl border px-4 py-3 font-bold transition-all duration-200';

        if (status === 'not_started') {

            statusInput.classList.add(
                'bg-slate-50',
                'border-slate-200',
                'text-slate-700'
            );

        }

        else if (status === 'on_track') {

            statusInput.classList.add(
                'metric-emerald',
                'text-emerald-700'
            );

        }

        else if (status === 'at_risk') {

            statusInput.classList.add(
                'metric-amber',
                'text-amber-700'
            );

        }

        else if (status === 'in_trouble') {

            statusInput.classList.add(
                'bg-red-50',
                'border-red-300',
                'text-red-700'
            );

        }

        else if (status === 'completed') {

            statusInput.classList.add(
                'metric-brown',
                'text-[#6B3F2A]'
            );

        }

    }

    const quarterRanges = {

        Q1:{
            start:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-01-01',
            end:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-03-31'
        },

        Q2:{
            start:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-04-01',
            end:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-06-30'
        },

        Q3:{
            start:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-07-01',
            end:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-09-30'
        },

        Q4:{
            start:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-10-01',
            end:'{{ str_replace("FY","",$fy ?? "FY".now()->year) }}-12-31'
        }

    };

    function validateQuarterRanges(){

        ['Q1','Q2','Q3','Q4'].forEach(q=>{

            const startField =
                document.querySelector(
                    `[name="quarters[${q}][start_date]"]`
                );

            const endField =
                document.querySelector(
                    `[name="quarters[${q}][end_date]"]`
                );

            if(!startField || !endField){
                return;
            }

            const start = startField.value;
            const end = endField.value;

            if(
                start &&
                (
                    start < quarterRanges[q].start
                    ||
                    start > quarterRanges[q].end
                )
            ){
                startField.value =
                    quarterRanges[q].start;
            }

            if(
                end &&
                (
                    end < quarterRanges[q].start
                    ||
                    end > quarterRanges[q].end
                )
            ){
                endField.value =
                    quarterRanges[q].end;
            }

        });

    }

    updateSubCategories();
    updateLinkageWarning();
    updateSummary();
    updateStatusBadge();
    validateQuarterRanges();

function updateCompletion(){

    let total = 6;
    let completed = 0;

    if(
        document.querySelector(
            'input[name="category"]:checked'
        )
    ){
        completed++;
    }

    if(
        document.querySelector(
            'input[name="sub_category"]:checked'
        )
    ){
        completed++;
    }

    if(kpiTitle.value){
        completed++;
    }

    if(baseTarget.value){
        completed++;
    }

    if(stretchTarget.value){
        completed++;
    }

    if(statusInput.value){
        completed++;
    }

    const percent =
        Math.round(
            (completed / total) * 100
        );

    const completionPercent =
        document.getElementById(
            'completionPercent'
        );

    const completionBar =
        document.getElementById(
            'completionBar'
        );

    if(completionPercent){

        completionPercent.textContent =
            percent + '%';

    }

    if(completionBar){

        completionBar.style.width =
            percent + '%';

    }

    // Glow the submit button once every required field on the whole form
    // (including all 4 quarters, not just the 6 tracked above) is filled.
    const submitBtn = document.getElementById('createKpiSubmitBtn');
    if(submitBtn){
        submitBtn.classList.toggle('submit-ready', form.checkValidity());
    }
}

const sections =
document.querySelectorAll(
    '[id^="section"]'
);

const navItems =
document.querySelectorAll(
    '.step-nav'
);

window.addEventListener(
    'scroll',
    () => {

        let current = '';

        sections.forEach(section => {

            const top =
                section.offsetTop - 150;

            if(
                window.scrollY >= top
            ){
                current =
                    section.id;
            }

        });

        navItems.forEach(item => {

            item.classList.remove(
                'text-[#6B3F2A]',
                'font-black'
            );

            const href =
                item.getAttribute('href');

            if(
                href === '#' + current
            ){
                item.classList.add(
                    'text-[#6B3F2A]',
                    'font-black'
                );
            }

        });

    }
);

document.addEventListener(
    'click',
    function(e){

        if(
            e.target.id === 'closeDetailModal' ||
            e.target.closest?.('#closeDetailModal')
        ){
            const dModal =
                document.getElementById(
                    'assignmentDetailModal'
                );

            if(dModal){
                dModal.classList.add('hidden');
                dModal.classList.remove('flex');
            }
        }

    }
);

function bindAssignmentCards(){

    document
    .querySelectorAll('.assignment-card')
    .forEach(card=>{

        card.addEventListener(
            'click',
            function(event){

                event.stopPropagation();

                try{

                    const raw =
                        this.dataset.kpi;

                    if(!raw){
                        console.error(
                            'data-kpi missing'
                        );
                        return;
                    }

                    const data =
                        JSON.parse(raw);

                    const detailModal =
                        document.getElementById(
                            'assignmentDetailModal'
                        );

                    const detailContent =
                        document.getElementById(
                            'detailContent'
                        );

                    if(
                        !detailModal ||
                        !detailContent
                    ){
                        console.error(
                            'Detail modal missing'
                        );
                        return;
                    }

                    const statusMap = {
                        'on_track'   : ['On Track',    'status-on-track'],
                        'at_risk'    : ['At Risk',     'status-risk'],
                        'in_trouble' : ['In Trouble',  'status-trouble'],
                        'completed'  : ['Completed',   'status-completed'],
                        'not_started': ['Not Started', 'status-not-started'],
                    };

                    const fmtDate = (d) => {
                        if(!d) return '-';
                        const dt = new Date(d);
                        return isNaN(dt) ? d : dt.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
                    };

                    let quarterHtml = '';

                    if(Array.isArray(data.quarters)){
                        data.quarters.forEach(q => {
                            const [statusLabel, statusCls] = statusMap[q.status] ?? ['Not Started', 'status-not-started'];
                            quarterHtml += `
                            <div class="rounded-[20px] border border-purple-100 bg-gradient-to-br from-purple-50 to-white p-4">
                                <div class="flex items-center gap-3">
                                    <div class="quarter-dot flex-shrink-0">${q.quarter ?? '-'}</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2 mb-3">
                                            <p class="font-black text-slate-900 text-sm truncate">${q.quarter_title ?? '-'}</p>
                                            <span class="status-pill ${statusCls} flex-shrink-0">${statusLabel}</span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="rounded-xl bg-white border border-purple-100 p-2">
                                                <p class="text-[9px] text-purple-400 uppercase font-black tracking-wider">Target</p>
                                                <p class="text-sm font-black text-[#7A0019] mt-0.5">${Number(q.quarter_target ?? 0).toLocaleString()}</p>
                                            </div>
                                            <div class="rounded-xl bg-white border border-purple-100 p-2">
                                                <p class="text-[9px] text-purple-400 uppercase font-black tracking-wider">Start</p>
                                                <p class="text-xs font-bold text-slate-700 mt-0.5">${fmtDate(q.start_date)}</p>
                                            </div>
                                            <div class="rounded-xl bg-white border border-purple-100 p-2">
                                                <p class="text-[9px] text-purple-400 uppercase font-black tracking-wider">End</p>
                                                <p class="text-xs font-bold text-slate-700 mt-0.5">${fmtDate(q.end_date)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            `;
                        });
                    }

                    detailContent.innerHTML = `
                    <div class="space-y-6">

                        <!-- KPI INFORMATION -->
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-1 h-5 rounded-full bg-gradient-to-b from-[#7A0019] to-[#D4AF37]"></div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">KPI Information</p>
                            </div>
                            <div class="space-y-2">
                                <div class="rounded-[20px] bg-gradient-to-br from-[#1A0A0A] to-[#7A0019] p-4">
                                    <p class="text-[9px] uppercase tracking-widest text-white/50 font-black">KPI Name</p>
                                    <p class="font-black text-white text-base mt-1 leading-snug">${data.kpi_title ?? '-'}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-3">
                                        <p class="text-[9px] uppercase tracking-widest text-[#8B5E4A] font-black">Category</p>
                                        <p class="font-bold text-slate-900 mt-1 text-sm">${data.category ?? '-'}</p>
                                    </div>
                                    <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-3">
                                        <p class="text-[9px] uppercase tracking-widest text-[#8B5E4A] font-black">Sub Category</p>
                                        <p class="font-bold text-slate-900 mt-1 text-sm">${data.sub_category ?? '-'}</p>
                                    </div>
                                </div>
                                ${data.kpi_description && data.kpi_description !== '-' ? `
                                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                    <p class="text-[9px] uppercase tracking-widest text-slate-500 font-black">Description</p>
                                    <p class="text-sm text-slate-600 mt-1 leading-relaxed">${data.kpi_description}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- ASSIGNMENT -->
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-1 h-5 rounded-full bg-gradient-to-b from-emerald-500 to-teal-400"></div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Assignment</p>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-3">
                                    <p class="text-[9px] uppercase tracking-widest text-emerald-600 font-black">Assigned By</p>
                                    <p class="font-bold text-slate-900 mt-1 text-sm">${data.owner_name ?? '-'}</p>
                                </div>
                                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-3">
                                    <p class="text-[9px] uppercase tracking-widest text-emerald-600 font-black">Role</p>
                                    <p class="font-bold text-slate-900 mt-1 text-sm">${data.owner_role ?? '-'}</p>
                                </div>
                                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-3">
                                    <p class="text-[9px] uppercase tracking-widest text-emerald-600 font-black">Assigned Date</p>
                                    <p class="font-bold text-slate-900 mt-1 text-sm">${fmtDate(data.created_at)}</p>
                                </div>
                            </div>
                        </div>

                        <!-- TARGET -->
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-1 h-5 rounded-full bg-gradient-to-b from-[#8B5E4A] to-[#6B3F2A]"></div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Target</p>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-4">
                                    <p class="text-[9px] uppercase tracking-widest text-[#8B5E4A] font-black">Base Target</p>
                                    <p class="text-xl font-black text-[#7A0019] mt-1">${Number(data.base_target ?? 0).toLocaleString()}</p>
                                    <p class="text-[9px] text-white/50 mt-1 uppercase font-bold">Annual</p>
                                </div>
                                <div class="rounded-2xl bg-purple-50 border border-purple-100 p-4">
                                    <p class="text-[9px] uppercase tracking-widest text-purple-500 font-black">Stretch Target</p>
                                    <p class="text-xl font-black text-[#7A0019] mt-1">${Number(data.stretch_target ?? 0).toLocaleString()}</p>
                                    <p class="text-[9px] text-purple-300 mt-1 uppercase font-bold">Annual</p>
                                </div>
                                <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4">
                                    <p class="text-[9px] uppercase tracking-widest text-amber-600 font-black">Weightage</p>
                                    <p class="text-xl font-black text-[#7A0019] mt-1">${data.weightage ?? 0}%</p>
                                    <p class="text-[9px] text-amber-300 mt-1 uppercase font-bold">Weight</p>
                                </div>
                            </div>
                        </div>

                        <!-- QUARTER BREAKDOWN -->
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-1 h-5 rounded-full bg-gradient-to-b from-purple-500 to-indigo-500"></div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Quarter Breakdown</p>
                            </div>
                            <div class="space-y-2">
                                ${quarterHtml}
                            </div>
                        </div>

                    </div>
                    `;

                    detailModal.classList.remove(
                        'hidden'
                    );

                    detailModal.classList.add(
                        'flex'
                    );

                }catch(error){

                    console.error(
                        'Assignment Detail Error:',
                        error
                    );

                }

            }
        );

    });

}

function syncMainStatusFromQuarters(){
    const quarterStatuses = document.querySelectorAll('select[name*="[status]"]');
    const anyActive = Array.from(quarterStatuses).some(s => s.value !== 'not_started');
    const mainStatus = document.getElementById('status');
    if(!mainStatus) return;
    if(anyActive && mainStatus.value === 'not_started'){
        mainStatus.value = 'on_track';
        mainStatus.dispatchEvent(new Event('change'));
    } else if(!anyActive){
        mainStatus.value = 'not_started';
        mainStatus.dispatchEvent(new Event('change'));
    }
}

document.addEventListener('DOMContentLoaded', function(){

    bindAssignmentCards();

    document.querySelectorAll('select[name*="[status]"]').forEach(function(sel){
        sel.addEventListener('change', syncMainStatusFromQuarters);
    });

    /* --- filter tabs for inline assignment list --- */
});

const closeDetailBtn =
    document.getElementById(
        'closeDetailModal'
    );

if(closeDetailBtn){

    closeDetailBtn.addEventListener(
        'click',
        function(){

            const modal =
                document.getElementById(
                    'assignmentDetailModal'
                );

            if(!modal){
                return;
            }

            modal.classList.add(
                'hidden'
            );

            modal.classList.remove(
                'flex'
            );

        }
    );

}

const detailModal =
    document.getElementById(
        'assignmentDetailModal'
    );

if(detailModal){

    detailModal.addEventListener(
        'click',
        function(e){

            if(
                e.target === this
            ){

                this.classList.add(
                    'hidden'
                );

                this.classList.remove(
                    'flex'
                );

            }

        }
    );

}

document.addEventListener(
    'keydown',
    function(e){

        if(
            e.key === 'Escape'
        ){

            const modal =
                document.getElementById(
                    'assignmentDetailModal'
                );

            if(
                modal &&
                !modal.classList.contains(
                    'hidden'
                )
            ){

                modal.classList.add(
                    'hidden'
                );

                modal.classList.remove(
                    'flex'
                );

            }

        }

    }
);

function toggleOwner(ownerId){

    const section =
        document.getElementById(
            'owner-' + ownerId
        );

    if(!section){
        return;
    }

    section.classList.toggle('hidden');

}

 // DOMContentLoaded

/*
|--------------------------------------------------------------------------
| AI — SCORE KPI DESCRIPTION
|--------------------------------------------------------------------------
*/

async function aiScoreDescription() {
    const btn      = document.getElementById('aiScoreBtn');
    const label    = document.getElementById('aiScoreLabel');
    const badge    = document.getElementById('aiScoreBadge');
    const circle   = document.getElementById('aiScoreCircle');
    const numEl    = document.getElementById('aiScoreNumber');
    const feedback = document.getElementById('aiScoreFeedback');
    const errorEl  = document.getElementById('aiSuggestError');

    const title       = document.getElementById('kpiTitle')?.value?.trim();
    const description = document.getElementById('kpiDescription')?.value?.trim();

    if (!title || !description) {
        errorEl.textContent = 'Fill in at least the KPI title and description first.';
        errorEl.classList.remove('hidden');
        return;
    }

    const quarterTargets = Array.from(
        document.querySelectorAll('input[name^="quarters"][name$="[quarter_target]"]')
    ).map(i => parseFloat(i.value) || 0);

    const payload = {
        kpi_title:       title,
        kpi_description: description,
        base_target:     document.getElementById('baseTarget')?.value    || null,
        stretch_target:  document.getElementById('stretchTarget')?.value || null,
        unit:            document.getElementById('unit')?.value           || null,
        weightage:       document.querySelector('input[name="weightage"]')?.value || null,
        category:        document.querySelector('input[name="category"]:checked')?.value || null,
        sub_category:    document.querySelector('input[name="sub_category"]:checked')?.value || null,
        quarter_targets: quarterTargets.length ? quarterTargets : null,
    };

    btn.disabled = true;
    label.textContent = 'Scoring...';
    badge.classList.add('hidden');
    errorEl.classList.add('hidden');

    try {
        const res = await fetch('{{ route("ai.score-description") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (data.success) {
            const score = data.score ?? 0;
            const color = score >= 8 ? '#7c3aed'
                        : score >= 5 ? '#d97706'
                        : '#dc2626';

            numEl.textContent       = score;
            circle.style.background = color;
            feedback.textContent    = data.feedback ?? '';
            badge.classList.remove('hidden');
        } else {
            errorEl.textContent = data.message ?? 'Scoring failed.';
            errorEl.classList.remove('hidden');
        }
    } catch (e) {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        label.textContent = 'Score';
    }
}

/*
|--------------------------------------------------------------------------
| AI — SUGGEST KPI DESCRIPTION
|--------------------------------------------------------------------------
*/

async function aiSuggestDescription() {
    const titleInput = document.getElementById('kpiTitle');
    const descTextarea = document.getElementById('kpiDescription');
    const btn = document.getElementById('aiSuggestBtn');
    const label = document.getElementById('aiSuggestLabel');
    const errorEl = document.getElementById('aiSuggestError');

    const title = titleInput?.value?.trim();
    if (!title) {
        titleInput?.focus();
        return;
    }

    btn.disabled = true;
    label.textContent = 'Generating...';
    errorEl.classList.add('hidden');

    try {
        const res = await fetch('{{ route("ai.suggest-description") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ kpi_title: title }),
        });

        const data = await res.json();

        if (data.success) {
            descTextarea.value = data.description;
            descTextarea.dispatchEvent(new Event('input'));
            aiScoreDescription();
        } else {
            errorEl.textContent = data.message ?? 'AI suggestion failed.';
            errorEl.classList.remove('hidden');
        }
    } catch (e) {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        label.textContent = 'Generate with AI';
    }
}

/* -------------------------------------------------------------------------
 | ANIRA — KPI FORM FILL
 | Called by the chat widget when the user confirms a KPI suggestion.
 ------------------------------------------------------------------------- */

window.aniraKpiPage = true;

window.aniraFillKpiForm = function (data) {
    // Title
    const titleEl = document.getElementById('kpiTitle');
    if (titleEl && data.title) {
        titleEl.value = data.title;
        titleEl.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Description
    const descEl = document.querySelector('textarea[name="kpi_description"]');
    if (descEl && data.description) {
        descEl.value = data.description;
        descEl.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Category (radio)
    if (data.category) {
        const catRadio = document.querySelector(`input[name="category"][value="${CSS.escape(data.category)}"]`);
        if (catRadio) {
            catRadio.checked = true;
            catRadio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // Sub-category — must wait for updateSubCategories() to render the options
    if (data.sub_category) {
        setTimeout(() => {
            const subRadios = document.querySelectorAll('input[name="sub_category"]');
            subRadios.forEach(r => {
                if (r.value === data.sub_category) {
                    r.checked = true;
                    r.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            if (typeof updateSummary === 'function') updateSummary();
        }, 150);
    }

    // Unit
    const unitEl = document.getElementById('unit');
    if (unitEl && data.unit) {
        unitEl.value = data.unit;
        unitEl.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Base / Stretch targets
    const baseEl = document.getElementById('baseTarget');
    if (baseEl && data.base_target != null) {
        baseEl.value = data.base_target;
        baseEl.dispatchEvent(new Event('input', { bubbles: true }));
    }
    const stretchEl = document.getElementById('stretchTarget');
    if (stretchEl && data.stretch_target != null) {
        stretchEl.value = data.stretch_target;
        stretchEl.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Quarters — target, title, and description
    ['Q1','Q2','Q3','Q4'].forEach(q => {
        const qKey = q.toLowerCase();

        const targetInp = document.querySelector(`input[name="quarters[${q}][quarter_target]"]`);
        if (targetInp && data[qKey] != null) {
            targetInp.value = data[qKey];
            targetInp.dispatchEvent(new Event('input', { bubbles: true }));
        }

        const titleKey = `${qKey}_title`;
        const titleInp = document.querySelector(`input[name="quarters[${q}][quarter_title]"]`);
        if (titleInp && data[titleKey]) {
            titleInp.value = data[titleKey];
            titleInp.dispatchEvent(new Event('input', { bubbles: true }));
        }

        const descKey  = `${qKey}_description`;
        const descArea = document.querySelector(`textarea[name="quarters[${q}][quarter_description]"]`);
        if (descArea && data[descKey]) {
            descArea.value = data[descKey];
            descArea.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    if (typeof updateSummary === 'function') updateSummary();

    // Scroll to top of the form
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Auto-fill if redirected here from another page via ANIRA
// Must run AFTER aniraFillKpiForm is defined above
(function () {
    try {
        const raw = sessionStorage.getItem('anira_pending_kpi');
        if (!raw) return;
        sessionStorage.removeItem('anira_pending_kpi');
        const kpi = JSON.parse(raw);
        if (!kpi) return;
        // Small delay ensures all DOM event listeners (category change, etc.) are wired up
        setTimeout(() => window.aniraFillKpiForm(kpi), 100);
    } catch (_) {}
})();

</script>

<div
    id="assignmentDetailModal"
    class="fixed inset-0 hidden bg-black/60 z-[99999] items-center justify-center p-4">

    <div class="bg-white w-full max-w-xl rounded-[2rem] overflow-hidden shadow-2xl flex flex-col max-h-[88vh]">

        <!-- modal header -->
        <div class="theme-header-banner px-6 py-5 flex items-center justify-between flex-shrink-0">
            <div>
                <p class="text-white/60 text-[10px] font-black uppercase tracking-widest">KPI Assignment</p>
                <h2 class="font-black text-white text-lg mt-0.5">KPI Detail</h2>
            </div>
            <button
                type="button"
                id="closeDetailModal"
                class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white font-black text-sm flex items-center justify-center transition-all">
                ✕
            </button>
        </div>

        <!-- scrollable body -->
        <div id="detailContent" class="overflow-y-auto p-6 flex-1"></div>

        <!-- view-only footer -->
        <div class="shrink-0 border-t border-slate-100 px-6 py-4 flex items-center justify-center bg-slate-50">
            <p class="text-xs text-slate-400 font-bold flex items-center gap-1.5">
                <span>👁</span> View Only — This KPI has been assigned to you for reference.
            </p>
        </div>

    </div>

</div>



</body>
</html>
