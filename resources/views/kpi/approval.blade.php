<!DOCTYPE html>
<html>
<head>

    <title>Approval Center</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    <style>

        .glass{
            background: rgba(255,255,255,.9);
            backdrop-filter: blur(14px);
        }

        .card-hover{
            transition:.2s ease;
        }

        .card-hover:hover{
            transform:translateY(-2px);
            box-shadow:0 18px 35px rgba(15,23,42,.08);
        }

    </style>

</head>

<body class="min-h-screen bg-[#f4f7fb]">

@include('partials.sidebar')

<main
    id="mainContent"
    class="ml-[230px] min-h-screen transition-all duration-300 bg-[#f4f7fb]"
>

<div class="p-6 space-y-6">

    <!-- HEADER -->
    <div class="rounded-[20px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white p-6 shadow-xl">

        <div class="flex justify-between items-center">

            <div>

                <a
                    href="{{ route('notifications') }}"
                    class="text-xs text-blue-100 hover:text-white"
                >
                    ← Notifications
                </a>

                <h1 class="text-3xl font-black mt-3">
                    Approval Center
                </h1>

                <p class="text-white/70 text-xs mt-2">

                    {{ session('short_name') }}
                    ·
                    KPI Governance Workflow

                </p>

            </div>

            <div class="bg-white/10 rounded-3xl px-6 py-5 text-center min-w-[150px]">

                <p class="text-[10px] uppercase tracking-wider text-blue-200 font-black">
                    Pending
                </p>

                <h2 class="text-4xl font-black mt-2">

                    {{ $totalPending ?? 0 }}

                </h2>

            </div>

        </div>

    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">

        <div class="glass rounded-[20px] p-5 border border-white/70">
            <p class="text-xs uppercase text-slate-500 font-black">Completion</p>
            <h2 class="text-3xl font-black mt-2 text-emerald-600">{{ $completionCount ?? 0 }}</h2>
        </div>

        <div class="glass rounded-[20px] p-5 border border-white/70">
            <p class="text-xs uppercase text-slate-500 font-black">Quarter Requests</p>
            <h2 class="text-3xl font-black mt-2 text-[#6B3F2A]">{{ $quarterCount ?? 0 }}</h2>
        </div>

        <div class="glass rounded-[20px] p-5 border border-white/70">
            <p class="text-xs uppercase text-slate-500 font-black">Target Requests</p>
            <h2 class="text-3xl font-black mt-2 text-yellow-600">{{ $targetCount ?? 0 }}</h2>
        </div>

        <div class="glass rounded-[20px] p-5 border border-white/70">
            <p class="text-xs uppercase text-slate-500 font-black">Delete Requests</p>
            <h2 class="text-3xl font-black mt-2 text-red-600">{{ $deleteCount ?? 0 }}</h2>
        </div>

        <div class="glass rounded-[20px] p-5 border border-white/70">
            <p class="text-xs uppercase text-slate-500 font-black">Weightage Requests</p>
            <h2 class="text-3xl font-black mt-2 text-orange-600">{{ $weightageCount ?? 0 }}</h2>
        </div>

        <div class="bg-slate-900 text-white rounded-[20px] p-5">
            <p class="text-xs uppercase text-slate-300 font-black">Total Pending</p>
            <h2 class="text-3xl font-black mt-2">{{ $totalPending ?? 0 }}</h2>
        </div>

    </div>

    <!-- APPROVAL TABS -->
    <div
        class="
        glass
        rounded-[20px]
        border
        border-white/70
        p-2
        flex
        gap-2
        "
    >

        <button
            id="tab-pending"
            class="
            approval-tab
            flex-1
            h-[56px]
            rounded-2xl
            bg-slate-900
            text-white
            font-black
            "
            onclick="switchApprovalTab('pending')"
        >
            Pending
        </button>

        <button
            id="tab-approved"
            class="
            approval-tab
            flex-1
            h-[56px]
            rounded-2xl
            bg-slate-100
            text-slate-700
            font-black
            "
            onclick="switchApprovalTab('approved')"
        >
            Approved
        </button>

        <button
            id="tab-rejected"
            class="
            approval-tab
            flex-1
            h-[56px]
            rounded-2xl
            bg-slate-100
            text-slate-700
            font-black
            "
            onclick="switchApprovalTab('rejected')"
        >
            Rejected
        </button>

    </div>

    <!-- FILTER -->
    <div class="glass rounded-[20px] border border-white/70 p-4 shadow-sm">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <div>

                <label class="text-xs font-black uppercase text-slate-500">
                    Search
                </label>

                <input
                    id="searchInput"
                    type="text"
                    placeholder="Search KPI or employee..."
                    class="w-full mt-2 border border-slate-200 rounded-2xl px-4 py-3 text-sm"
                >

            </div>

            <div>

                <label class="text-xs font-black uppercase text-slate-500">
                    Type
                </label>

                <select
                    id="typeFilter"
                    class="w-full mt-2 border border-slate-200 rounded-2xl px-4 py-3 text-sm"
                >

                    <option value="">
                        All Types
                    </option>

                    <option value="quarter_update">
                        Quarter Update
                    </option>

                    <option value="target_change">
                        Target Change
                    </option>

                    <option value="delete_request">
                        Delete Request
                    </option>

                    <option value="weightage_change">
                        Weightage Change
                    </option>

                </select>

            </div>

            <div class="bg-slate-900 text-white rounded-2xl p-4 flex items-center justify-between">

                <div>

                    <p class="text-xs text-blue-100">
                        Visible Request
                    </p>

                    <h2
                        id="visibleCount"
                        class="text-2xl font-black mt-1"
                    >
                        {{ $totalPending ?? 0 }}
                    </h2>

                </div>

            </div>

        </div>

    </div>

    <!-- LIST -->
    <div class="space-y-4">

        @forelse(($approvals ?? []) as $approval)

            @php

                $type =
                    $approval['type']
                    ?? 'quarter_update';

                $badgeColor =
                    match($type){
                        'completion'      => 'bg-emerald-50 text-emerald-700',
                        'quarter_update'  => 'bg-[#FBF5EF] text-[#6B3F2A]',
                        'target_change'   => 'bg-yellow-50 text-yellow-700',
                        'delete_request'  => 'bg-red-50 text-red-700',
                        'weightage_change'=> 'bg-orange-50 text-orange-700',
                        default           => 'bg-slate-100 text-slate-700'
                    };

                $typeLabel =
                    match($type){
                        'completion'      => 'Completion Approval',
                        'quarter_update'  => 'Quarter Update',
                        'target_change'   => 'Target Change',
                        'delete_request'  => 'Delete Request',
                        'weightage_change'=> 'Weightage Change',
                        default           => 'Approval'
                    };

                $priority =
                    strtolower(
                        $approval['risk_level']
                        ??
                        $approval['priority']
                        ??
                        'normal'
                    );

                $priorityColor =
                    match($priority){

                        'critical'
                            => 'bg-red-100 text-red-700 border-red-200',

                        'high'
                            => 'bg-orange-100 text-orange-700 border-orange-200',

                        default
                            => 'bg-slate-100 text-slate-700 border-slate-200'
                    };

                $priorityLabel =
                    ucfirst($priority);

            @endphp

            <div
                id="approval-{{ $approval['id'] }}"
                class="approval-card glass card-hover rounded-[24px] border p-5
                @if($priority === 'critical')
                    border-red-300
                @elseif($priority === 'high')
                    border-orange-300
                @else
                    border-white/70
                @endif
                "
                data-status="{{ strtolower($approval['status'] ?? 'pending') }}"
                data-type="{{ $type }}"
                data-search="
                    {{ strtolower($approval['kpi_title'] ?? '') }}
                    {{ strtolower($approval['requested_by_name'] ?? '') }}
                    {{ strtolower($typeLabel ?? '') }}
                    {{ strtolower($priorityLabel ?? '') }}
                "
            >
                <div class="flex flex-col xl:flex-row gap-5">

                    <!-- LEFT -->
                    <div class="flex-1">

                        <!-- BADGES -->
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black {{ $badgeColor }}">
                                {{ $typeLabel }}
                            </span>

                            <span
                                class="px-3 py-1 rounded-full border text-[10px] font-black {{ $priorityColor }}">
                                {{ $priorityLabel }}
                            </span>

                            @if($type === 'quarter_update')
                                <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black">
                                    {{ $approval['quarter'] }}
                                </span>
                            @endif

                            @if(($approval['status'] ?? '') === 'approved')

                            <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-black">
                                Approved
                            </span>

                            @elseif(($approval['status'] ?? '') === 'rejected')

                            <span class="px-3 py-1 rounded-full bg-red-50 text-red-700 text-[10px] font-black">
                                Rejected
                            </span>

                            @else

                            <span class="px-3 py-1 rounded-full bg-yellow-50 text-yellow-700 text-[10px] font-black">
                                Pending
                            </span>

                            @endif

                        </div>

                        <!-- KPI TITLE -->
                        <h2 class="text-2xl font-black text-slate-900 leading-tight">

                            {{ $approval['kpi_title'] ?? 'Untitled KPI' }}

                        </h2>

                        <!-- META -->
                        <div class="mt-4 flex flex-wrap gap-4 text-sm text-slate-500">

                            <div>

                                Requested By:

                                <span class="font-black text-slate-700">

                                    {{ $approval['requested_by_name'] ?? '-' }}

                                </span>

                            </div>

                            <div>

                                {{ $approval['created_at'] ?? '-' }}

                            </div>

                        </div>

                        <!-- QUARTER UPDATE -->
                        @if($type === 'quarter_update')

                            @php
                                // Unit-aware, thousands-separated formatting -- mirrors
                                // $sec2FmtVal in performance/report.blade.php, so the same
                                // number reads consistently everywhere it's shown. Decimals
                                // are kept only when the value actually has a fractional
                                // part (87.58 stays 87.58; 45 stays 45, not 45.00).
                                $fmtApprovalVal = function ($v, $u) {
                                    if ($v === null || $v === '') return '0';
                                    $n = (float) $v;
                                    $dp = (fmod($n, 1) !== 0.0) ? 2 : 0;
                                    if ($u === 'currency')   return 'RM ' . number_format($n, max($dp, 2));
                                    if ($u === 'percentage') return number_format($n, max($dp, 2)) . '%';
                                    return number_format($n, $dp);
                                };
                                $approvalUnit = $approval['unit'] ?? '';
                            @endphp

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">

                                <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">

                                    <p class="text-[10px] uppercase text-slate-400 font-black">
                                        Previous
                                    </p>

                                    <h3 class="text-xl font-black mt-2">

                                        {{ $fmtApprovalVal($approval['old_actual'] ?? 0, $approvalUnit) }}

                                    </h3>

                                </div>

                                <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-4">

                                    <p class="text-[10px] uppercase text-[#8B5E4A] font-black">
                                        Requested
                                    </p>

                                    <h3 class="text-xl font-black mt-2 text-[#6B3F2A]">

                                        {{ $fmtApprovalVal($approval['requested_actual'] ?? 0, $approvalUnit) }}

                                    </h3>

                                </div>

                                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4">

                                    <p class="text-[10px] uppercase text-emerald-500 font-black">
                                        Target
                                    </p>

                                    <h3 class="text-xl font-black mt-2 text-emerald-700">

                                        {{ $fmtApprovalVal($approval['quarter_target'] ?? 0, $approvalUnit) }}

                                    </h3>

                                </div>

                                <div class="rounded-2xl bg-orange-50 border border-orange-100 p-4">

                                    <p class="text-[10px] uppercase text-orange-500 font-black">
                                        Achievement
                                    </p>

                                    <h3 class="text-xl font-black mt-2 text-orange-700">

                                        @php

                                            $target =
                                                (float)($approval['quarter_target'] ?? 0);

                                            $actual =
                                                (float)($approval['requested_actual'] ?? 0);

                                            $achievement =
                                                $target > 0
                                                ? round(($actual / $target) * 100, 2)
                                                : 0;

                                        @endphp

                                        {{ number_format($achievement,2) }}%

                                    </h3>

                                </div>

                            </div>

                        @endif

                        <!-- COMPLETION DETAIL -->
                        @if($type === 'completion')
                        <div class="mt-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                                    <p class="text-[10px] uppercase text-slate-400 font-black">Quarter</p>
                                    <h3 class="text-xl font-black mt-1">{{ $approval['quarter'] ?? '-' }}</h3>
                                </div>
                                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4">
                                    <p class="text-[10px] uppercase text-emerald-500 font-black">Submitted By</p>
                                    <h3 class="text-base font-black mt-1 text-emerald-700">{{ $approval['requested_by_name'] ?? '-' }}</h3>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-5">
                                <p class="text-[10px] uppercase text-emerald-500 font-black mb-2">Completion Review</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $approval['reason'] ?? '-' }}</p>
                            </div>
                            @php
                                // Build unified file list: prefer attachment_urls (multi), fallback to single attachment_url
                                $proofFiles = [];
                                $rawUrls = $approval['attachment_urls'] ?? null;
                                if ($rawUrls) {
                                    $decoded = is_array($rawUrls) ? $rawUrls : json_decode($rawUrls, true);
                                    if (is_array($decoded)) $proofFiles = $decoded;
                                }
                                if (empty($proofFiles) && !empty($approval['attachment_url'])) {
                                    $su = $approval['attachment_url'];
                                    $isImg = preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $su);
                                    $proofFiles = [['url'=>$su,'name'=>basename($su),'type'=> $isImg ? 'image/jpeg' : 'application/pdf']];
                                }
                            @endphp
                            @if(!empty($proofFiles))
                            <div class="rounded-2xl bg-sky-50 border border-sky-100 p-5">
                                <p class="text-[10px] uppercase text-sky-500 font-black mb-3">
                                    Proof / Document
                                    <span class="normal-case font-medium text-sky-400 ml-1">({{ count($proofFiles) }} file{{ count($proofFiles) > 1 ? 's' : '' }})</span>
                                </p>
                                <div class="space-y-2">
                                    @foreach($proofFiles as $pf)
                                    @php
                                        $pfUrl  = $pf['url'] ?? '';
                                        $pfName = $pf['name'] ?? basename($pfUrl);
                                        $pfIsImg = str_starts_with($pf['type'] ?? '', 'image/') || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $pfUrl);
                                    @endphp
                                    @if($pfIsImg)
                                        <a href="{{ $pfUrl }}" target="_blank" class="block group">
                                            <div class="rounded-2xl overflow-hidden border-2 border-sky-200 group-hover:border-[#8B5E4A] transition bg-white">
                                                <img src="{{ $pfUrl }}" alt="{{ $pfName }}"
                                                     class="w-full max-h-64 object-contain">
                                                <div class="px-3 py-2 border-t border-sky-100 flex items-center gap-2 bg-sky-50">
                                                    <span class="text-base">🖼️</span>
                                                    <span class="text-xs font-bold text-sky-700 truncate flex-1">{{ $pfName }}</span>
                                                    <span class="text-[10px] text-[#8B5E4A] font-black shrink-0">Open ↗</span>
                                                </div>
                                            </div>
                                        </a>
                                    @else
                                        <a href="{{ $pfUrl }}" target="_blank"
                                           class="flex items-center gap-3 p-3 rounded-2xl border-2 border-sky-200 hover:border-[#8B5E4A] hover:bg-[#FBF5EF] transition group">
                                            <div class="w-10 h-10 rounded-xl bg-red-50 border border-red-200 flex items-center justify-center text-xl shrink-0">📄</div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-black text-slate-700 truncate">{{ $pfName }}</p>
                                                <p class="text-[10px] text-slate-400">PDF Document</p>
                                            </div>
                                            <span class="text-xs font-black text-[#8B5E4A] shrink-0 group-hover:text-[#5a3323]">Open ↗</span>
                                        </a>
                                    @endif
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        @endif

                        <!-- REMARK / DETAILS -->

                        @if($type === 'quarter_update')

                        <div class="mt-4 rounded-2xl bg-slate-50 border border-slate-100 p-5">

                            <p class="text-[10px] uppercase text-slate-400 font-black">
                                Reason
                            </p>

                            <p class="text-sm text-slate-700 mt-3 leading-relaxed">

                                {{ $approval['reason'] ?? '-' }}

                            </p>

                        </div>

                        @elseif($type === 'target_change')
                        @php

                            $baseImpact = 0;

                            if(
                                ($approval['old_base_target'] ?? 0) > 0
                            ){
                                $baseImpact =
                                    (
                                        (
                                            ($approval['new_base_target'] ?? 0)
                                            -
                                            ($approval['old_base_target'] ?? 0)
                                        )
                                        /
                                        ($approval['old_base_target'] ?? 1)
                                    ) * 100;
                            }

                            $stretchImpact = 0;

                            if(
                                ($approval['old_stretch_target'] ?? 0) > 0
                            ){
                                $stretchImpact =
                                    (
                                        (
                                            ($approval['new_stretch_target'] ?? 0)
                                            -
                                            ($approval['old_stretch_target'] ?? 0)
                                        )
                                        /
                                        ($approval['old_stretch_target'] ?? 1)
                                    ) * 100;
                            }

                        @endphp

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">

                            <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">

                                <p class="text-[10px] uppercase text-slate-400 font-black">
                                    Current Base
                                </p>

                                <h3 class="text-xl font-black mt-2">
                                    {{ number_format($approval['old_base_target'] ?? 0,0) }}
                                </h3>

                            </div>

                            <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-4">

                                <p class="text-[10px] uppercase text-[#8B5E4A] font-black">
                                    Requested Base
                                </p>

                                <h3 class="text-xl font-black mt-2 text-[#6B3F2A]">
                                    {{ number_format($approval['new_base_target'] ?? 0,0) }}
                                </h3>

                            </div>

                            <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">

                                <p class="text-[10px] uppercase text-slate-400 font-black">
                                    Current Stretch
                                </p>

                                <h3 class="text-xl font-black mt-2">
                                    {{ number_format($approval['old_stretch_target'] ?? 0,0) }}
                                </h3>

                            </div>

                            <div class="rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20 p-4">

                                <p class="text-[10px] uppercase text-[#8B5E4A] font-black">
                                    Requested Stretch
                                </p>

                                <h3 class="text-xl font-black mt-2 text-[#6B3F2A]">
                                    {{ number_format($approval['new_stretch_target'] ?? 0,0) }}
                                </h3>

                            </div>

                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

                            <div class="rounded-2xl bg-amber-50 border border-amber-100 p-5">

                                <p class="text-[10px] uppercase text-amber-500 font-black">
                                    Impact
                                </p>

                                <div class="mt-3 space-y-3">

                                    <div class="flex justify-between">

                                        <span>Base</span>

                                        <span class="font-black">

                                            {{ number_format($baseImpact,1) }}%

                                        </span>

                                    </div>

                                    <div class="flex justify-between">

                                        <span>Stretch</span>

                                        <span class="font-black">

                                            {{ number_format($stretchImpact,1) }}%

                                        </span>

                                    </div>

                                </div>

                            </div>

                            <div class="rounded-2xl bg-slate-50 border border-slate-100 p-5">

                                <p class="text-[10px] uppercase text-slate-400 font-black">
                                    Reason
                                </p>

                                <p class="text-sm text-slate-700 mt-3 leading-relaxed">

                                    {{ $approval['reason'] ?? '-' }}

                                </p>

                            </div>

                        </div>

                        @elseif($type === 'weightage_change')

                        <div class="grid grid-cols-2 gap-4 mt-6">

                            <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                                <p class="text-[10px] uppercase text-slate-400 font-black">Current Weightage</p>
                                <h3 class="text-2xl font-black mt-2">
                                    {{ number_format((float)($approval['old_weightage'] ?? 0), 2) }}%
                                </h3>
                            </div>

                            <div class="rounded-2xl bg-orange-50 border border-orange-100 p-4">
                                <p class="text-[10px] uppercase text-orange-500 font-black">Requested Weightage</p>
                                <h3 class="text-2xl font-black mt-2 text-orange-700">
                                    {{ number_format((float)($approval['new_weightage'] ?? 0), 2) }}%
                                </h3>
                                @php
                                    $wtDiff = (float)($approval['new_weightage'] ?? 0) - (float)($approval['old_weightage'] ?? 0);
                                @endphp
                                <p class="text-[11px] {{ $wtDiff >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-black mt-1">
                                    {{ $wtDiff >= 0 ? '+' : '' }}{{ number_format($wtDiff, 2) }}%
                                </p>
                            </div>

                        </div>

                        <div class="mt-4 rounded-2xl bg-amber-50 border border-amber-100 p-5">
                            <p class="text-[10px] uppercase text-amber-600 font-black">Reason from Employee</p>
                            <p class="text-sm text-slate-700 mt-2 leading-relaxed">
                                {{ $approval['reason'] ?? '-' }}
                            </p>
                        </div>

                        @else

                        <div class="mt-5 rounded-2xl bg-red-50 border border-red-100 p-5">

                            <p class="text-[10px] uppercase text-red-500 font-black">
                                Reason For Deletion
                            </p>

                            <p class="text-sm text-slate-700 mt-3 leading-relaxed">
                                {{ $approval['reason'] ?? '-' }}
                            </p>

                        </div>

                        @endif
                    </div>

                    <!-- ACTION / STATUS PANEL -->
                    <div class="w-full xl:w-[280px]"
                    >
                        @php

                            $status =
                                strtolower(
                                    $approval['status']
                                    ?? 'pending'
                                );

                            $statusClass =
                                match($status){

                                    'approved'
                                        => 'bg-emerald-50 border-emerald-200',

                                    'rejected'
                                        => 'bg-red-50 border-red-200',

                                    default
                                        => 'bg-yellow-50 border-yellow-200'
                                };

                            $statusText =
                                match($status){

                                    'approved'
                                        => 'APPROVED',

                                    'rejected'
                                        => 'REJECTED',

                                    default
                                        => 'PENDING'
                                };

                        @endphp

                        <div
                            class="
                            rounded-2xl
                            border
                            p-5
                            {{ $statusClass }}
                            "
                        >

                            <h3
                                class="
                                text-lg
                                font-black
                                "
                            >
                                {{ $statusText }}
                            </h3>

                            <div class="mt-5 space-y-4">

                                <div>

                                    <div
                                        class="
                                        text-[10px]
                                        uppercase
                                        text-slate-400
                                        font-black
                                        "
                                    >
                                        Action By
                                    </div>

                                    <div class="font-bold">

                                        {{
                                            $approval['approved_by_name']
                                            ??
                                            $approval['rejected_by_name']
                                            ??
                                            '-'
                                        }}

                                    </div>

                                </div>

                                <div>

                                    <div
                                        class="
                                        text-[10px]
                                        uppercase
                                        text-slate-400
                                        font-black
                                        "
                                    >
                                        Action Date
                                    </div>

                                    <div class="font-bold">

                                        {{
                                            $approval['approved_at']
                                            ??
                                            $approval['rejected_at']
                                            ??
                                            '-'
                                        }}

                                    </div>

                                </div>

                                <div>

                                    <div
                                        class="
                                        text-[10px]
                                        uppercase
                                        text-slate-400
                                        font-black
                                        "
                                    >
                                        Remark
                                    </div>

                                    <div
                                        class="
                                        mt-2
                                        rounded-xl
                                        bg-white
                                        border
                                        p-3
                                        text-sm
                                        "
                                    >

                                        {{
                                            $approval['rejection_reason']
                                            ??
                                            $approval['approver_remark']
                                            ??
                                            'Waiting for approval'
                                        }}

                                    </div>

                                </div>

                                @if($status === 'pending')

                                <div class="pt-2 space-y-3">

                                    <button
                                        onclick="approveRequest('{{ $approval['id'] }}')"
                                        class="w-full h-[54px] rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black"
                                    >
                                        Approve
                                    </button>

                                    <button
                                        onclick="rejectRequest('{{ $approval['id'] }}')"
                                        class="w-full h-[54px] rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black"
                                    >
                                        Reject
                                    </button>

                                </div>

                                @endif

                            </div>

                        </div>

                    </div>
                </div>
            </div>

        @empty

            <div class="glass rounded-[24px] border border-dashed border-slate-300 p-20 text-center">

                <h2 class="text-3xl font-black text-slate-900">

                    No Pending Approval

                </h2>

                <p class="text-sm text-slate-500 mt-3">

                    Everything already reviewed.

                </p>

            </div>

        @endforelse

    </div>

</div>

</main>

<script>

const searchInput =
    document.getElementById('searchInput');

const typeFilter =
    document.getElementById('typeFilter');

const visibleCount =
    document.getElementById('visibleCount');

const cards =
    document.querySelectorAll('.approval-card');

let activeApprovalTab = 'pending';

function filterCards(){

    const search =
        searchInput.value
        .toLowerCase()
        .trim();

    const type =
        typeFilter.value;

    let visible = 0;

    cards.forEach(card => {

        const matchesSearch =
            (card.dataset.search || '')
            .includes(search);

        const matchesType =
            !type ||
            card.dataset.type === type;

        const matchesStatus =
            (card.dataset.status || 'pending')
            === activeApprovalTab;

        if(
            matchesSearch &&
            matchesType &&
            matchesStatus
        ){

            card.classList.remove('hidden');

            visible++;

        }
        else{

            card.classList.add('hidden');
        }

    });

    visibleCount.innerText = visible;
}

searchInput.addEventListener(
    'input',
    filterCards
);

typeFilter.addEventListener(
    'change',
    filterCards
);

document.addEventListener(
    'DOMContentLoaded',
    function(){

        filterCards();

        // Deep link from a notification — ?highlight=<request id> scrolls
        // straight to that card and rings it gold for a moment instead of
        // dumping the user on the generic list.
        var highlightId = new URLSearchParams(window.location.search).get('highlight');
        if (highlightId) {
            var target = document.getElementById('approval-' + highlightId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.style.transition = 'box-shadow .3s ease';
                target.style.boxShadow = '0 0 0 3px #D4AF37';
                setTimeout(function () { target.style.boxShadow = ''; }, 2500);
            }
        }

    }
);

function switchApprovalTab(status){

    activeApprovalTab = status;

    document
        .querySelectorAll('.approval-tab')
        .forEach(btn => {

            btn.classList.remove(
                'bg-slate-900',
                'text-white'
            );

            btn.classList.add(
                'bg-slate-100',
                'text-slate-700'
            );

        });

    const activeBtn =
        document.getElementById(
            'tab-' + status
        );

    activeBtn.classList.remove(
        'bg-slate-100',
        'text-slate-700'
    );

    activeBtn.classList.add(
        'bg-slate-900',
        'text-white'
    );

    filterCards();
}

async function approveRequest(id){

    if(
        !confirm(
            'Approve this request?'
        )
    ){
        return;
    }

    try{

        const response = await fetch(

            '/approval/' + id + '/approve',

            {

                method:'POST',

                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN':'{{ csrf_token() }}'
                }

            }

        );

        const result =
            await response.json();

        if(result.success){

            alert('Approval successful.');

            location.reload();

        }else{

            alert(
                result.message ||
                'Approval failed.'
            );
        }

    }catch(error){

        console.error(error);

        alert('System error.');
    }
}

async function rejectRequest(id){

    const reason = prompt(
        'Reason for rejection'
    );

    if(!reason){
        return;
    }

    try{

        const response = await fetch(

            '/approval/' + id + '/reject',

            {

                method:'POST',

                headers:{
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN':'{{ csrf_token() }}'
                },

                body: JSON.stringify({

                    reason: reason

                })

            }

        );

        const result =
            await response.json();

        if(result.success){

            alert('Request rejected.');

            location.reload();

        }else{

            alert(
                result.message ||
                'Reject failed.'
            );
        }

    }catch(error){

        console.error(error);

        alert('System error.');
    }
}

</script>

</body>
</html>
