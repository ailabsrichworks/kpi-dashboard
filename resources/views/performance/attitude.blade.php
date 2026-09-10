<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attitude Appraisal · {{ $qLabel }} · {{ $currentFinancialYear }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        *, body { font-family: 'Inter', sans-serif; }
        .doc-card  { box-shadow: 0 8px 40px rgba(15,23,42,.10); }

        /* Section bar */
        .sec-bar { display:flex; align-items:center; gap:12px; padding:10px 24px; background:linear-gradient(90deg,#1a3d34,#2d5548); }
        .sec-num { width:26px; height:26px; border-radius:50%; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.35); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:900; color:#fff; flex-shrink:0; }
        .sec-title { font-size:11px; font-weight:800; color:#fff; text-transform:uppercase; letter-spacing:.12em; }

        /* Part label */
        .part-label { display:flex; align-items:center; gap:8px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.10em; color:#6B9080; margin-bottom:14px; }
        .part-label::after { content:''; flex:1; height:1px; background:rgba(107,144,128,.25); }

        /* Field */
        .f-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.09em; color:#94a3b8; margin-bottom:3px; }
        .f-val   { font-size:13px; font-weight:600; color:#1e293b; }
        .f-input { width:100%; border:none; border-bottom:1.5px solid rgba(107,144,128,.30); padding:5px 0; font-size:13px; font-weight:500; color:#334155; background:transparent; outline:none; transition:border-color .15s; }
        .f-input:focus { border-bottom-color:#6B9080; }
        .f-box   { width:100%; border:1.5px solid rgba(107,144,128,.30); border-radius:10px; padding:9px 14px; font-size:13px; color:#334155; background:white; outline:none; transition:border-color .15s; }
        .f-box:focus { border-color:#6B9080; }
        .f-area  { width:100%; min-height:90px; border:1.5px solid rgba(107,144,128,.30); border-radius:10px; padding:10px 14px; font-size:12px; color:#334155; background:white; outline:none; resize:vertical; transition:border-color .15s; line-height:1.6; }
        .f-area:focus { border-color:#6B9080; }

        /* Rating pill selector */
        .rating-group { display:flex; gap:5px; justify-content:center; }
        .rating-group input[type=radio] { display:none; }
        .rating-group label {
            width:32px; height:32px; border-radius:8px; border:1.5px solid rgba(107,144,128,.3);
            display:flex; align-items:center; justify-content:center;
            font-size:12px; font-weight:700; color:#94a3b8; cursor:pointer;
            transition:all .15s; background:white; user-select:none;
        }
        .rating-group label:hover { border-color:#6B9080; color:#6B9080; background:rgba(107,144,128,.06); }
        .rating-group input[type=radio]:checked + label {
            background:#1a3d34; border-color:#1a3d34; color:#fff;
            box-shadow: 0 2px 8px rgba(26,61,52,.25);
        }

        /* Table */
        .doc-tbl { width:100%; font-size:11px; border-collapse:collapse; }
        .doc-tbl th { background:#1a3d34; color:#fff; padding:10px 12px; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.09em; }
        .doc-tbl th.l { text-align:left; } .doc-tbl th.c { text-align:center; }
        .doc-tbl td { padding:8px 12px; border-bottom:1px solid rgba(107,144,128,.10); vertical-align:middle; }
        .doc-tbl tbody tr:last-child td { border-bottom:none; }

        /* Score colour */
        .sc-great { color:#059669; } .sc-good { color:#6B9080; } .sc-warn { color:#d97706; } .sc-poor { color:#dc2626; } .sc-none { color:#cbd5e1; }

        /* Number input */
        .n-input { width:60px; text-align:center; border:1.5px solid rgba(107,144,128,.3); border-radius:8px; padding:4px; font-size:11px; font-weight:600; color:#334155; background:white; outline:none; transition:border-color .15s; }
        .n-input:focus { border-color:#6B9080; }

        /* Sig line */
        .sig-line { border-bottom:1.5px dashed rgba(107,144,128,.40); height:44px; margin-bottom:6px; }

        /* ── Print table header (hidden on screen) ─────────── */
        #print-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        #print-thead { display: none; }

        @media print {
            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            @page { size: A4 portrait; margin: 10mm 10mm 12mm; }

            /* Repeating header — table-header-group repeats cleanly on every page */
            #print-table { width: 100% !important; border-collapse: collapse !important; }
            #print-thead { display: table-header-group !important; }
            #print-thead td { padding: 4mm 0 3mm; background: white !important; }

            /* Hide original doc header — replaced by table header */
            #doc-hdr { display: none !important; }

            #sidebar, #sidebarCloseBtn, .no-print,
            .sticky { display:none !important; }

            #mainContent { margin-left:0 !important; }
            body { background:#f0f2f7 !important; }
            .px-4 { padding-left:4px !important; padding-right:4px !important; }
            .pt-3 { padding-top:0 !important; }
            .pb-10 { padding-bottom:0 !important; }

            .doc-card { box-shadow:none !important; border:1px solid #6B9080 !important; border-radius:12px !important; }
            .sec-bar { background:linear-gradient(90deg,#1a3d34,#2d5548) !important; }
            .doc-tbl th { background:#1a3d34 !important; color:#fff !important; }
            .part-label { color:#6B9080 !important; }

            input[type=radio]:checked + label {
                background:#1a3d34 !important;
                border-color:#1a3d34 !important;
                color:#fff !important;
            }

            /* Avoid page breaks inside content blocks */
            .border.rounded-xl,
            .sig-block { page-break-inside:avoid; }
            tr, p { page-break-inside:avoid; }
        }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">

{{-- Sticky header --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#f0f2f7] no-print">
    <div class="rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-4 shadow-xl flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center">
                <span class="text-sm font-black">{{ $qLabel }}</span>
            </div>
            <div>
                <h1 class="text-base font-black leading-tight">Attitude &amp; Competency Appraisal</h1>
                <p class="text-white/65 text-[10px] mt-0.5">{{ $currentUserName }} · {{ $userPosition }} · {{ $departmentName }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($isWindowOpen)
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-emerald-500/20 text-emerald-200 border border-emerald-400/30 px-3 py-1.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Window Open
            </span>
            @else
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-amber-500/15 text-amber-200 border border-amber-400/30 px-3 py-1.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> {{ $windowStart }} → {{ $windowEnd }}
            </span>
            @endif
            <button onclick="window.print()" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-xl font-bold text-xs transition border border-white/20 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / PDF
            </button>
        </div>
    </div>
</div>

{{-- Print table: thead repeats on every printed page via table-header-group --}}
<table id="print-table">
<thead id="print-thead">
<tr><td>
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
            <span style="font-size:12px;font-weight:900;color:#1a3d34">{{ session('company_display_name') }}</span>
            <p style="font-size:7px;color:#94a3b8;letter-spacing:.18em;text-transform:uppercase;margin-top:3px">Accelerating Your Business Success</p>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px">
            <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#1a3d34,#6B9080);display:flex;align-items:center;justify-content:center">
                <span style="font-size:14px;font-weight:900;color:white;line-height:1">{{ $qLabel }}</span>
            </div>
            <span style="font-size:7px;font-weight:700;color:#6B9080;letter-spacing:.12em;text-transform:uppercase">{{ $currentFinancialYear }}</span>
        </div>
    </div>
</td></tr>
</thead>
<tbody>
<tr><td style="padding:0">

<div class="px-4 pb-10 pt-3">
<div class="max-w-5xl mx-auto">

<div class="bg-white rounded-2xl overflow-hidden doc-card border border-[#6B9080]/25">
    <div class="h-[3px] theme-header-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019]"></div>

    <div class="px-10 py-8">

        {{-- Doc header --}}
        <div id="doc-hdr" class="flex items-start justify-between mb-7 pb-6 border-b border-slate-100">
            <div>
                <p class="text-xl font-black text-[#1a3d34]">{{ session('company_display_name') }}</p>
                <p class="text-[9px] text-slate-400 uppercase tracking-[.18em]">Accelerating Your Business Success</p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#1A0A0A] to-[#7A0019] flex items-center justify-center shadow-lg">
                    <span class="text-2xl font-black text-white">{{ $qLabel }}</span>
                </div>
                <span class="text-[9px] font-bold text-[#6B9080] uppercase tracking-widest">{{ $currentFinancialYear }}</span>
            </div>
        </div>

        {{-- Title --}}
        <div class="text-center mb-7">
            <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-[.22em] mb-3">— Private &amp; Confidential —</p>
            <h2 class="text-lg font-black text-[#1a3d34] uppercase tracking-[.06em] mb-1">
                Executive / Non-Executive Performance Appraisal
            </h2>
            <div class="flex items-center justify-center gap-2 mt-2">
                <span class="h-px w-12 bg-[#6B9080]/30"></span>
                <span class="text-[10px] font-semibold text-[#6B9080] uppercase tracking-widest">Attitude &amp; Competency · Quarter {{ $displayQuarter }} · {{ $currentFinancialYear }}</span>
                <span class="h-px w-12 bg-[#6B9080]/30"></span>
            </div>
        </div>

        {{-- Purpose of Review --}}
        <div class="border border-[#6B9080]/25 rounded-xl mb-6 overflow-hidden">
            <div class="bg-[#6B9080]/8 border-b border-[#6B9080]/20 px-5 py-2.5">
                <p class="text-[9px] font-black text-[#6B9080] uppercase tracking-[.14em]">Purpose of Review</p>
            </div>
            <div class="px-5 py-4 flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-5">
                    @foreach([
                        ['id'=>'por_confirmation',     'label'=>'Confirmation'],
                        ['id'=>'por_quarterly_review', 'label'=>'Quarterly Review'],
                        ['id'=>'por_others',           'label'=>'Others'],
                    ] as $opt)
                    <label class="flex items-center gap-2 cursor-pointer group select-none">
                        <span class="w-4 h-4 rounded border-2 border-[#6B9080]/50 flex items-center justify-center relative">
                            <input type="checkbox" id="{{ $opt['id'] }}" value="{{ $opt['label'] }}"
                                   {{ $opt['id'] === 'por_quarterly_review' ? 'checked' : '' }}
                                   class="sr-only peer">
                            <span class="w-2.5 h-2.5 rounded-sm bg-[#6B9080] hidden peer-checked:block"></span>
                        </span>
                        <span class="text-[11px] font-semibold text-slate-700 group-hover:text-[#6B9080] transition">{{ $opt['label'] }}</span>
                    </label>
                    @endforeach
                </div>
                <div class="h-px flex-1 bg-slate-100 hidden md:block"></div>
                <div class="flex-1 min-w-48">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Please specify (if Others)</p>
                    <input type="text" placeholder="Describe purpose…" class="f-input">
                </div>
                <div class="text-right">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Year / Period</p>
                    <p class="text-base font-black text-[#1a3d34]">{{ now()->year }} <span class="text-[#6B9080]">/</span> {{ $qLabel }}</p>
                </div>
            </div>
        </div>

        {{-- Employee info strip --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-7">
            @foreach([
                ['label'=>'Name',        'value'=>$currentUserName],
                ['label'=>'Position',    'value'=>$userPosition],
                ['label'=>'Department',  'value'=>$departmentName],
                ['label'=>'Reporting To','value'=>$reportsToName],
            ] as $f)
            <div class="border border-[#6B9080]/20 rounded-xl px-4 py-3 bg-slate-50/60">
                <p class="f-label mb-1">{{ $f['label'] }}</p>
                <p class="text-xs font-semibold text-slate-800 leading-snug">{{ $f['value'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             SECTION 3 — ATTITUDE & COMPETENCY ASSESSMENT
        ═══════════════════════════════════════════════════════════════ --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6">
            <div class="sec-bar">
                <div class="sec-num">3</div>
                <span class="sec-title">Executive / Non-Executive : Performance Appraisal</span>
            </div>

            {{-- Rating scale legend --}}
            <div class="px-6 pt-5 pb-4 border-b border-slate-100">
                <p class="text-[9px] font-black text-[#6B9080] uppercase tracking-widest mb-3">Rating Scale</p>
                <div class="flex gap-2 flex-wrap">
                    @php $ratingLegend = [
                        ['score'=>5,'cat'=>'Outstanding',    'def'=>'Exceptional performance, high initiative, sound judgement.',           'bg'=>'bg-emerald-50','border'=>'border-emerald-200','text'=>'text-emerald-700','num'=>'bg-emerald-500'],
                        ['score'=>4,'cat'=>'Above Average',  'def'=>'Consistently meets all requirements, exceeds in major aspects.',       'bg'=>'bg-[#6B9080]/5','border'=>'border-[#6B9080]/25','text'=>'text-[#1a3d34]','num'=>'bg-[#6B9080]'],
                        ['score'=>3,'cat'=>'Average',        'def'=>'Meets the normal requirements of the position.',                       'bg'=>'bg-slate-50','border'=>'border-slate-200','text'=>'text-slate-600','num'=>'bg-slate-400'],
                        ['score'=>2,'cat'=>'Below Average',  'def'=>'Below expectations, requires improvement and remedial steps.',         'bg'=>'bg-amber-50','border'=>'border-amber-200','text'=>'text-amber-700','num'=>'bg-amber-400'],
                        ['score'=>1,'cat'=>'Unsatisfactory', 'def'=>'Inadequate; counselling or appropriate action required.',              'bg'=>'bg-red-50','border'=>'border-red-200','text'=>'text-red-700','num'=>'bg-red-500'],
                    ]; @endphp
                    @foreach($ratingLegend as $r)
                    <div class="flex-1 min-w-36 {{ $r['bg'] }} border {{ $r['border'] }} rounded-xl p-3">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="w-6 h-6 {{ $r['num'] }} rounded-lg flex items-center justify-center text-white text-xs font-black flex-shrink-0">{{ $r['score'] }}</span>
                            <span class="text-[10px] font-black {{ $r['text'] }} uppercase tracking-wide">{{ $r['cat'] }}</span>
                        </div>
                        <p class="text-[9px] text-slate-400 leading-relaxed">{{ $r['def'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Assessment areas --}}
            <div class="overflow-x-auto">
            <table class="doc-tbl" id="attTable" style="min-width:680px;">
                <thead>
                    <tr>
                        <th class="l" style="width:40%;">Area / Assessment</th>
                        <th class="c" style="width:140px;">
                            Self Rating<br>
                            <span style="font-weight:400;text-transform:none;font-size:8px;">Pick 1 – 5</span>
                        </th>
                        <th class="c" style="width:140px;">
                            Superior Rating<br>
                            <span style="font-weight:400;text-transform:none;font-size:8px;">Pick 1 – 5</span>
                        </th>
                        <th class="l">Appraiser's Comment</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($assessmentAreas as $i => $area)
                <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-slate-50/50' }}" style="border-bottom:1px solid rgba(107,144,128,.10);">
                    <td style="padding:14px 16px;">
                        <p style="font-size:11px;font-weight:800;color:#1e293b;margin-bottom:3px;">{{ $area['no'] }}) {{ $area['title'] }}</p>
                        <p style="font-size:10px;color:#94a3b8;line-height:1.55;font-style:italic;">{{ $area['description'] }}</p>
                    </td>
                    {{-- Self rating --}}
                    <td style="padding:10px 8px;vertical-align:top;" class="text-center">
                        <p style="font-size:8px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">Self</p>
                        <div class="rating-group justify-center">
                            @foreach([1,2,3,4,5] as $sc)
                            <input type="radio" id="self_{{ $area['no'] }}_{{ $sc }}" name="self_area_{{ $area['no'] }}" value="{{ $sc }}" class="self-radio">
                            <label for="self_{{ $area['no'] }}_{{ $sc }}">{{ $sc }}</label>
                            @endforeach
                        </div>
                    </td>
                    {{-- Superior rating --}}
                    <td style="padding:10px 8px;vertical-align:top;" class="text-center">
                        <p style="font-size:8px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">Superior</p>
                        <div class="rating-group justify-center">
                            @foreach([1,2,3,4,5] as $sc)
                            <input type="radio" id="sup_{{ $area['no'] }}_{{ $sc }}" name="superior_area_{{ $area['no'] }}" value="{{ $sc }}" class="sup-radio">
                            <label for="sup_{{ $area['no'] }}_{{ $sc }}" style="background:white; border-color:rgba(26,61,52,.25); color:#94a3b8;">{{ $sc }}</label>
                            @endforeach
                        </div>
                    </td>
                    {{-- Comment --}}
                    <td style="padding:10px 12px;vertical-align:top;">
                        <textarea rows="3" placeholder="Appraiser's comment…" class="f-area" style="min-height:70px;font-size:11px;"></textarea>
                    </td>
                </tr>
                @endforeach

                {{-- Summary row --}}
                <tr style="background:linear-gradient(90deg,rgba(107,144,128,.08),rgba(107,144,128,.04));">
                    <td style="padding:14px 16px;">
                        <p style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.10em;color:#6B9080;">No. of Areas Assessed</p>
                        <p style="font-size:24px;font-weight:900;color:#1a3d34;margin-top:2px;">{{ count($assessmentAreas) }}</p>
                        <p style="font-size:9px;color:#94a3b8;margin-top:4px;font-style:italic;">
                            Formula: Total ÷ {{ count($assessmentAreas) * 5 }} × 25
                        </p>
                    </td>
                    <td style="padding:14px 8px;" class="text-center">
                        <p style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:6px;">Self Score</p>
                        <p id="selfTotal" style="font-size:22px;font-weight:900;color:#cbd5e1;">—</p>
                        <p id="selfPct" style="font-size:9px;color:#94a3b8;margin-top:2px;"></p>
                    </td>
                    <td style="padding:14px 8px;" class="text-center">
                        <p style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;margin-bottom:6px;">Superior Score</p>
                        <p id="supTotal" style="font-size:22px;font-weight:900;color:#cbd5e1;">—</p>
                        <p id="supPct" style="font-size:9px;color:#94a3b8;margin-top:2px;"></p>
                    </td>
                    <td></td>
                </tr>
                </tbody>
            </table>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             SECTION 4 — PERFORMANCE ANALYSIS
        ═══════════════════════════════════════════════════════════════ --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6">
            <div class="sec-bar">
                <div class="sec-num">4</div>
                <span class="sec-title">Executive / Non-Executive : Performance Analysis</span>
            </div>

            <div class="px-6 py-6 space-y-6">

                {{-- A) Rating summary --}}
                <div>
                    <div class="part-label">A &nbsp;·&nbsp; Rating Summary</div>
                    <p class="text-[11px] text-slate-400 italic mb-5">Summary score of the Appraisal Review in Section 2 (KPI) and Section 3 (Attitude &amp; Competency).</p>

                    <div class="grid grid-cols-2 gap-6">
                        {{-- Score table --}}
                        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden" id="sec4Table">
                            <table class="doc-tbl">
                                <thead>
                                    <tr>
                                        <th class="l">Section</th>
                                        <th class="c" style="width:100px;">Self Score</th>
                                        <th class="c" style="width:100px;">Appraiser</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="bg-white" style="border-bottom:1px solid rgba(107,144,128,.10);">
                                        <td style="padding:12px 14px;">
                                            <p style="font-size:11px;font-weight:700;color:#334155;">Section 2</p>
                                            <p style="font-size:9px;color:#94a3b8;">KPI Performance</p>
                                        </td>
                                        <td class="text-center"><input type="number" inputmode="decimal" step="any" min="0" max="100" placeholder="—" class="n-input s4-input"></td>
                                        <td class="text-center"><input type="number" inputmode="decimal" step="any" min="0" max="100" placeholder="—" class="n-input s4-input"></td>
                                    </tr>
                                    <tr class="bg-slate-50/50" style="border-bottom:1px solid rgba(107,144,128,.10);">
                                        <td style="padding:12px 14px;">
                                            <p style="font-size:11px;font-weight:700;color:#334155;">Section 3</p>
                                            <p style="font-size:9px;color:#94a3b8;">Attitude &amp; Competency</p>
                                        </td>
                                        <td class="text-center"><input type="number" inputmode="decimal" step="any" min="0" max="100" placeholder="—" class="n-input s4-input"></td>
                                        <td class="text-center"><input type="number" inputmode="decimal" step="any" min="0" max="100" placeholder="—" class="n-input s4-input"></td>
                                    </tr>
                                    <tr style="background:linear-gradient(90deg,rgba(26,61,52,.06),rgba(107,144,128,.04));">
                                        <td style="padding:12px 14px;">
                                            <p style="font-size:12px;font-weight:900;color:#1a3d34;text-transform:uppercase;letter-spacing:.05em;">Rating</p>
                                            <p style="font-size:9px;color:#94a3b8;">Combined total</p>
                                        </td>
                                        <td class="text-center" style="padding:12px;"><span id="s4SelfTotal" style="font-size:20px;font-weight:900;color:#cbd5e1;">—</span></td>
                                        <td class="text-center" style="padding:12px;"><span id="s4AppTotal"  style="font-size:20px;font-weight:900;color:#cbd5e1;">—</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Scoring matrix --}}
                        <div>
                            <p class="f-label mb-3">Scoring Matrix</p>
                            <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden">
                                <div class="bg-[#1a3d34] px-4 py-2 text-center">
                                    <span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.85);">Performance Grade</span>
                                </div>
                                @php $matrix = [
                                    ['range'=>'90 – 100','label'=>'Outstanding',       'bg'=>'bg-emerald-50','border'=>'border-emerald-100','text'=>'text-emerald-700','badge'=>'bg-emerald-100'],
                                    ['range'=>'70 – 89', 'label'=>'Meets Expectations','bg'=>'bg-[#6B9080]/5','border'=>'border-[#6B9080]/15','text'=>'text-[#1a3d34]','badge'=>'bg-[#6B9080]/15'],
                                    ['range'=>'50 – 69', 'label'=>'Below Average',     'bg'=>'bg-amber-50','border'=>'border-amber-100','text'=>'text-amber-700','badge'=>'bg-amber-100'],
                                    ['range'=>'1 – 49',  'label'=>'Unsatisfactory',    'bg'=>'bg-red-50','border'=>'border-red-100','text'=>'text-red-700','badge'=>'bg-red-100'],
                                ]; @endphp
                                @foreach($matrix as $m)
                                <div class="flex items-center justify-between {{ $m['bg'] }} border-b {{ $m['border'] }} px-4 py-2.5">
                                    <span style="font-size:11px;font-weight:800;color:#475569;">{{ $m['range'] }}</span>
                                    <span class="text-[10px] font-black px-3 py-1 rounded-full {{ $m['badge'] }} {{ $m['text'] }}">{{ $m['label'] }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                {{-- B) Performance Analysis --}}
                <div>
                    <div class="part-label">B &nbsp;·&nbsp; Performance Analysis</div>
                    <p class="text-[11px] text-slate-400 italic mb-5">To be completed by Appraiser.</p>
                    <div class="grid grid-cols-2 gap-5">
                        @foreach([
                            ['label'=>'Strengths'],
                            ['label'=>'Work Ethics / Attitude'],
                            ['label'=>'Areas Need Improvement'],
                            ['label'=>'Training Required'],
                        ] as $pf)
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#6B9080] flex-shrink-0"></span>
                                <p class="f-label">{{ $pf['label'] }}</p>
                            </div>
                            <input type="text" placeholder="Enter here…" class="f-input">
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                {{-- Appraiser signature --}}
                <div>
                    <p class="text-[11px] text-slate-500 italic leading-relaxed mb-6">
                        I hereby confirm that the foregoing appraisal is a fair and objective evaluation of the appraisee's performance during the period under review.
                    </p>
                    <div class="flex justify-end">
                        <div class="text-center w-64">
                            <div class="sig-line mx-2"></div>
                            <p class="text-xs font-bold text-slate-700">{{ $reportsToName !== '-' ? $reportsToName : '_______________' }}</p>
                            <p class="f-label mt-1">Signature of Appraiser – Manager / VP</p>
                            <p class="text-[9px] text-slate-400 mt-2">Date: _______________</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                {{-- Appraisee acknowledgment --}}
                <div>
                    <p class="text-[11px] text-slate-500 italic leading-relaxed mb-3">
                        I hereby confirm that I have read, understood and accept/disagree with the foregoing appraisal.
                        <span class="text-[#6B9080] font-semibold not-italic">(If you disagree please specify below)</span>
                    </p>
                    <textarea rows="4" placeholder="Write your response here…" class="f-area mb-6"></textarea>
                    <div class="flex justify-end">
                        <div class="text-center w-64">
                            <div class="sig-line mx-2"></div>
                            <p class="text-xs font-bold text-slate-700">{{ $currentUserName }}</p>
                            <p class="f-label mt-1">Signature of Appraisee</p>
                            <p class="text-[9px] text-slate-400 mt-2">Date: _______________</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             SECTION 5 — RECOMMENDATIONS & DECISIONS
        ═══════════════════════════════════════════════════════════════ --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-2">
            <div class="sec-bar">
                <div class="sec-num">5</div>
                <span class="sec-title">Recommendations &amp; Decisions</span>
            </div>

            <div class="px-6 py-6 space-y-7">

                @php $sec5 = [
                    ['key'=>'manager','label'=>'A','title'=>'Promotability and Other Remarks and Recommendations by the Appraiser (Manager)'],
                    ['key'=>'vp',     'label'=>'B','title'=>'Remarks and/or Recommendations by VP'],
                    ['key'=>'slt',    'label'=>'C','title'=>'Remarks by SLT'],
                ]; @endphp

                @foreach($sec5 as $idx => $blk)
                @if($idx > 0)<div class="border-t border-dashed border-[#6B9080]/20 pt-7"></div>@endif

                <div>
                    <div class="part-label">{{ $blk['label'] }} &nbsp;·&nbsp; {{ $blk['title'] }}</div>

                    <textarea rows="4" placeholder="Write remarks here…" class="f-area mb-5"></textarea>

                    <div class="flex items-end justify-between gap-6 flex-wrap">
                        {{-- Checkboxes --}}
                        <div class="flex items-center gap-6">
                            @foreach(['Confirmation','Salary Review','Promotion'] as $opt)
                            <label class="flex items-center gap-2 cursor-pointer group select-none">
                                <span class="w-4 h-4 rounded border-2 border-[#6B9080]/40 flex items-center justify-center">
                                    <input type="checkbox" name="{{ $blk['key'] }}_decision[]" value="{{ $opt }}" class="sr-only peer">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-[#6B9080] hidden peer-checked:block"></span>
                                </span>
                                <span class="text-[11px] font-semibold text-slate-700 group-hover:text-[#6B9080] transition">{{ $opt }}</span>
                            </label>
                            @endforeach
                        </div>
                        {{-- Signature + Date --}}
                        <div class="text-center min-w-56">
                            <div class="sig-line mx-2"></div>
                            <p class="f-label mt-1">Signature</p>
                            <div class="flex items-center gap-2 mt-2 justify-center">
                                <span class="f-label">Date</span>
                                <input type="date" class="border border-[#6B9080]/25 rounded-lg px-2 py-1 text-xs text-slate-600 bg-white outline-none focus:border-[#6B9080] transition">
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

            </div>
        </div>

    </div>{{-- /px-10 py-8 --}}
</div>{{-- /doc-card --}}

</div>
</div>{{-- /px-4 --}}
</td></tr>
</tbody>
</table>
</main>

<script>
(function () {
    const AREAS   = {{ count($assessmentAreas) }};
    const MAX     = AREAS * 5;

    function sc(v, base) {
        if (!base) return 'color:#cbd5e1';
        const p = v / base * 100;
        if (p >= 80) return 'color:#059669';
        if (p >= 60) return 'color:#6B9080';
        if (p >= 40) return 'color:#d97706';
        return 'color:#dc2626';
    }

    function recalcS3() {
        const selfChecked = document.querySelectorAll('input.self-radio:checked');
        const supChecked  = document.querySelectorAll('input.sup-radio:checked');

        const selfSum = [...selfChecked].reduce((s,r) => s + parseInt(r.value), 0);
        const supSum  = [...supChecked].reduce((s,r) => s + parseInt(r.value), 0);

        const selfEl = document.getElementById('selfTotal');
        const supEl  = document.getElementById('supTotal');
        const spEl   = document.getElementById('selfPct');
        const stEl   = document.getElementById('supPct');

        if (selfChecked.length) {
            selfEl.textContent = selfSum;
            selfEl.style = sc(selfSum, MAX);
            spEl.textContent = (selfSum / MAX * 25).toFixed(1) + ' / 25 pts';
        } else { selfEl.textContent = '—'; selfEl.style = 'color:#cbd5e1'; spEl.textContent = `(${selfChecked.length}/${AREAS})`; }

        if (supChecked.length) {
            supEl.textContent = supSum;
            supEl.style = sc(supSum, MAX);
            stEl.textContent = (supSum / MAX * 25).toFixed(1) + ' / 25 pts';
        } else { supEl.textContent = '—'; supEl.style = 'color:#cbd5e1'; stEl.textContent = `(${supChecked.length}/${AREAS})`; }
    }

    document.getElementById('attTable')?.addEventListener('change', recalcS3);

    // Section 4 live sum
    function recalcS4() {
        const inputs = document.querySelectorAll('#sec4Table input.s4-input');
        const s2s = parseFloat(inputs[0]?.value), s2a = parseFloat(inputs[1]?.value);
        const s3s = parseFloat(inputs[2]?.value), s3a = parseFloat(inputs[3]?.value);

        const selfEl = document.getElementById('s4SelfTotal');
        const appEl  = document.getElementById('s4AppTotal');

        if (!isNaN(s2s) && !isNaN(s3s)) {
            const t = s2s + s3s;
            selfEl.textContent = t.toFixed(1);
            selfEl.style = sc(t, 200);
        } else { selfEl.textContent = '—'; selfEl.style = 'color:#cbd5e1'; }

        if (!isNaN(s2a) && !isNaN(s3a)) {
            const t = s2a + s3a;
            appEl.textContent = t.toFixed(1);
            appEl.style = sc(t, 200);
        } else { appEl.textContent = '—'; appEl.style = 'color:#cbd5e1'; }
    }

    document.getElementById('sec4Table')?.addEventListener('input', recalcS4);
})();
</script>
</body>
</html>
