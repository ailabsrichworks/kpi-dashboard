<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $qLabel }} Evaluation · {{ $currentFinancialYear }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        *, body { font-family: 'Inter', sans-serif; }

        /* The fixed Save Draft/Submit bar at the bottom of the screen sits on
           top of the last ~90px of the viewport without occupying real layout
           space, so the browser's native scroll-into-view (used by clicks,
           Tab-focus, and Playwright/automation alike) doesn't know to leave
           room for it — it happily aligns a row flush with the true bottom
           edge, landing it right under the bar where clicks get swallowed.
           scroll-padding-bottom reserves that space for scroll targeting. */
        html { scroll-padding-bottom: 100px; }

        .doc-card { box-shadow: 0 8px 40px rgba(15,23,42,.10); }

        .sec-bar {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 24px;
            background: linear-gradient(90deg, #1a3d34, #2d5548);
        }
        .sec-num {
            width: 26px; height: 26px; border-radius: 50%;
            background: rgba(255,255,255,.18); border: 1.5px solid rgba(255,255,255,.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 900; color: #fff; flex-shrink: 0;
        }
        .sec-title { font-size: 11px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: .12em; }

        .part-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 10px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .10em;
            color: #6B9080; margin-bottom: 14px;
        }
        .part-label::after { content: ''; flex: 1; height: 1px; background: rgba(107,144,128,.25); }

        .f-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .09em; color: #94a3b8; margin-bottom: 3px; }
        .f-val   { font-size: 13px; font-weight: 600; color: #1e293b; text-transform: uppercase; }

        /* Part D — optional date with calendar icon */
        .partd-wrap { position: relative; display: inline-flex; align-items: center; gap: 2px; vertical-align: middle; }
        .partd-cal  { color: #6B9080; cursor: pointer; display: inline-flex; align-items: center; padding: 2px 4px; border-radius: 4px; transition: color .15s, background .15s; }
        .partd-cal:hover { color: #4a7c6b; background: rgba(107,144,128,.15); }
        .partd-val  { font-size: 11px; font-weight: 600; color: #1e293b; display: none; border-bottom: 1.5px solid rgba(107,144,128,.40); padding-bottom: 1px; }
        .partd-clr  { display: none; font-size: 14px; line-height: 1; color: #94a3b8; cursor: pointer; background: none; border: none; padding: 0 2px; vertical-align: middle; }
        .partd-clr:hover { color: #ef4444; }

        .f-input {
            width: 100%; border: none; border-bottom: 1.5px solid rgba(107,144,128,.30);
            padding: 5px 0; font-size: 13px; font-weight: 600; color: #1e293b;
            background: transparent; outline: none; transition: border-color .15s;
            text-transform: uppercase;
        }
        .f-input:focus { border-bottom-color: #6B9080; }

        .f-area {
            width: 100%; min-height: 90px;
            border: 1.5px solid rgba(107,144,128,.30); border-radius: 10px;
            padding: 10px 14px; font-size: 12px; color: #334155;
            background: white; outline: none; resize: vertical; transition: border-color .15s;
            line-height: 1.6;
        }
        .f-area:focus { border-color: #6B9080; }

        /* Print-only stand-in for a textarea — see beforeprint handler below.
           A <textarea> can't grow to fit its content, so on-screen long text
           just scrolled inside the fixed box and printed truncated. This is
           a plain block element instead, so it always lays out at full height. */
        .f-area-print { display: none; }

        .n-input {
            width: 60px; text-align: center;
            border: 1.5px solid rgba(107,144,128,.30); border-radius: 8px;
            padding: 4px; font-size: 11px; font-weight: 600; color: #334155;
            background: white; outline: none; transition: border-color .15s;
        }
        .n-input:focus { border-color: #6B9080; }

        .t-input {
            width: 100%; border: none; border-bottom: 1px solid rgba(107,144,128,.25);
            padding: 4px 0; font-size: 11px; color: #334155;
            background: transparent; outline: none; transition: border-color .15s;
        }
        .t-input:focus { border-bottom-color: #6B9080; }
        .t-input::placeholder { color: #cbd5e1; }

        .doc-tbl { width: 100%; font-size: 11px; border-collapse: collapse; }
        .doc-tbl th {
            background: #1a3d34; color: #fff; padding: 10px 12px;
            font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em;
        }
        .doc-tbl th.l { text-align: left; }
        .doc-tbl th.c { text-align: center; }
        .doc-tbl td { padding: 8px 12px; border-bottom: 1px solid rgba(107,144,128,.10); vertical-align: middle; }
        .doc-tbl tbody tr:last-child td { border-bottom: none; }

        .cat-hdr td    { background: #1a3d34; color: #fff; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: .14em; padding: 8px 16px; }
        .subcat-hdr td { background: rgba(107,144,128,.08); color: #2d5548; font-size: 10px; font-weight: 700; padding: 7px 16px 7px 22px; border-bottom: 1px solid rgba(107,144,128,.18); letter-spacing: .03em; }
        .q-tag { display:inline-block; font-size:8px; font-weight:900; color:#1a3d34; background:rgba(107,144,128,.15); border:1px solid rgba(107,144,128,.35); border-radius:4px; padding:1px 6px; letter-spacing:.06em; text-transform:uppercase; vertical-align:middle; flex-shrink:0; }

        .sc-great { color: #059669; }
        .sc-good  { color: #6B9080; }
        .sc-warn  { color: #d97706; }
        .sc-poor  { color: #dc2626; }
        .sc-none  { color: #cbd5e1; }

        .sig-line { border-bottom: 1.5px dashed rgba(107,144,128,.40); height: 44px; margin-bottom: 6px; }

        /* Rating pills */
        .rating-group { display: flex; gap: 4px; }
        .rating-group input[type=radio] { display: none; }
        .rating-group label {
            width: 28px; height: 28px; border-radius: 50%;
            border: 1.5px solid rgba(107,144,128,.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: #64748b;
            cursor: pointer; transition: all .15s;
        }
        .rating-group input[type=radio]:checked + label {
            background: #1a3d34; border-color: #1a3d34; color: #fff;
        }
        .rating-group label:hover { border-color: #6B9080; color: #6B9080; }

        /* ── Print table header (hidden on screen) ─────────── */
        #print-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        #print-thead { display: none; }

        /* #print-table/#print-thead exist purely so the printed page gets a
           repeating header via table-header-group — real <table> layout is
           only needed while printing. Left as a table on screen too, it
           wraps the ENTIRE page's live, interactive content in one giant
           table cell, which breaks click hit-testing for anything deeply
           nested inside it (buttons ending up geometrically "in" the page
           per getBoundingClientRect but not what elementFromPoint finds at
           that point — verified directly in a real browser). Reverting to
           plain block flow on screen fixes that; @media print below
           restores real table display so the print trick keeps working. */
        #print-table, #print-table > tbody, #print-table > tbody > tr, #print-table > tbody > tr > td {
            display: block;
        }

        /* ── Print ─────────────────────────────────────────── */
        @media print {
            .partd-cal, .partd-clr { display: none !important; }

            *, *::before, *::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            @page { size: A4 portrait; margin: 10mm 10mm 12mm; }

            #print-table { display: table !important; width: 100% !important; border-collapse: collapse !important; zoom: 0.85 !important; }
            #print-table > tbody { display: table-row-group !important; }
            #print-table > tbody > tr { display: table-row !important; }
            #print-table > tbody > tr > td { display: table-cell !important; }
            #print-thead { display: table-header-group !important; }
            #print-thead td { padding: 4mm 0 3mm; background: white !important; }
            #doc-hdr { display: none !important; }

            #sidebar, #sidebarCloseBtn, .no-print, .sticky { display: none !important; }
            #mainContent { margin-left: 0 !important; }
            html, body { background: #fff !important; }
            .px-4 { padding-left: 4px !important; padding-right: 4px !important; }
            .pt-3 { padding-top: 0 !important; }
            .pb-10 { padding-bottom: 0 !important; }
            /* Doc-card's inner content padding is still full-size (px-10 py-8) —
               shrink it so content reaches close to the doc-card's green border
               instead of leaving a wide gap on every side. */
            .px-10 { padding-left: 10px !important; padding-right: 10px !important; }
            .py-8  { padding-top: 8px !important; padding-bottom: 8px !important; }

            /* Plain corporate print palette — black / gray / white only,
               no brand-green gradients, no blue accent, no rainbow status
               colors. */
            .doc-card { box-shadow: none !important; border: 1px solid #94a3b8 !important; border-radius: 12px !important; }
            .sec-bar { background: #1e293b !important; }
            .doc-tbl th { background: #1e293b !important; color: #fff !important; }
            .part-label { color: #1e293b !important; }
            .h-\[3px\] { background: #1e293b !important; }
            .cat-hdr td    { background: #1e293b !important; color: #fff !important; }
            .subcat-hdr td { background: #f1f5f9 !important; }
            .q-tag { color: #1e293b !important; background: #fff !important; border-color: #94a3b8 !important; }

            /* Field/label text carrying the brand-green color — plain black,
               not blue (blue is reserved for header bars above). */
            [class*="text-[#6B9080]"], [class*="text-[#1a3d34]"] { color: #1e293b !important; }
            [class*="border-[#6B9080]"] { border-color: #94a3b8 !important; }
            /* Divider lines / panel washes — recolor but keep visible */
            [class~="bg-[#6B9080]/30"] { background-color: #94a3b8 !important; }
            [class~="bg-[#6B9080]/8"], [class~="bg-[#6B9080]/5"] { background-color: #f1f5f9 !important; }
            /* Checkbox fill squares + bullet dots — solid accent, keep
               filled (so ticked boxes stay visible), just not green */
            [class~="bg-[#6B9080]"] { background-color: #1e293b !important; }

            /* Red/amber/green status indicators (KPI achievement score,
               attendance stat cards, attendance score rows, Section 6
               rating summary) print exactly as the system shows them —
               only the decorative brand-green/blue header accents and
               field-label text are flattened above. */

            /* Print happens after the appraisal is fully filled in — show the
               chosen rating as a plain value instead of the on-screen 1–5
               picker, and drop the legend explaining what each number means
               (only useful while actively rating, not once complete). */
            .rating-scale-legend { display: none !important; }
            .rating-group label { display: none !important; }
            .rating-group input[type=radio]:checked + label {
                display: inline-flex !important;
                width: auto !important; height: auto !important;
                border: none !important; background: transparent !important;
                color: #1e293b !important; font-size: 13px !important; font-weight: 900 !important;
            }

            /* ── Flatten the "live web form" look for print ─────
               On screen, locked/read-only fields are faded (opacity, grey
               fill, not-allowed cursor) to signal they're not editable by
               the current viewer. On a printed page that just reads as
               half-invisible boxes, so print at full legibility instead. */
            input, textarea, select, .rating-group, .sig-pad-wrap {
                opacity: 1 !important;
            }
            input, textarea, select {
                background: #fff !important;
                color: #1e293b !important;
                -webkit-text-fill-color: #1e293b !important;
            }
            /* Underlined/boxed inputs read as clickable form fields — drop
               the colored border/underline so filled values sit as plain
               document text; keep a faint line only where still empty. */
            .f-input, .t-input {
                border-bottom-color: rgba(100,116,139,.25) !important;
            }
            .n-input {
                border-color: rgba(100,116,139,.30) !important;
                border-radius: 6px !important;
            }
            /* Hide placeholder prompt text ("Write your summary here…" etc.)
               — empty fields should print as blank lines, not form hints. */
            ::placeholder { color: transparent !important; }

            /* A <textarea> can only show as much text as its fixed height
               allows — the rest just sits below a scrollbar and gets cut
               off on paper. Hide the textarea and print the plain-text
               stand-in (filled in by the beforeprint handler) instead,
               which lays out to its full height and is free to flow onto
               the next page rather than clipping. */
            .f-area { display: none !important; }
            .f-area-print {
                display: block !important;
                width: 100%;
                border: 1px solid rgba(100,116,139,.30);
                border-radius: 6px;
                padding: 10px 14px;
                font-size: 12px;
                color: #1e293b;
                line-height: 1.6;
                white-space: pre-wrap;
                word-wrap: break-word;
                min-height: 20px;
            }

            /* Attendance count boxes look like a live spinner input — on
               print (always filled in by then) just show the number. */
            .att-count-input {
                border: none !important;
                background: transparent !important;
                width: auto !important;
                padding: 0 !important;
                -moz-appearance: textfield !important;
            }
            .att-count-input::-webkit-inner-spin-button,
            .att-count-input::-webkit-outer-spin-button {
                -webkit-appearance: none !important;
                margin: 0 !important;
            }

            /* ── Signature pads ──────────────────────────────── */
            /* Hide the placeholder hint text */
            .sig-hint { display: none !important; }
            /* Remove the dashed border box — only the drawn content shows */
            .sig-pad-wrap > div:first-child {
                border: none !important;
                background: transparent !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }
            /* Hide Clear / Upload buttons */
            .sig-pad-wrap > div + div { display: none !important; }
            /* Hide entire pad when no signature was drawn (set by beforeprint JS) */
            .sig-pad-wrap.no-sig { display: none !important; }

            /* Each section starts on a new page (except the first) */
            .print-sec + .print-sec {
                break-before: page;
                page-break-before: always;
            }
            /* Section 1 always starts on its own page, separate from the
               title / purpose-of-review / employee-strip block above it */
            #sec1-particulars {
                break-before: page !important;
                page-break-before: always !important;
            }
            /* Section header must stay with its content — never orphaned at bottom of page */
            .sec-bar {
                break-after: avoid;
                page-break-after: avoid;
            }
            /* Part/sub-section labels stay with their content */
            .part-label, .f-label {
                break-after: avoid;
                page-break-after: avoid;
            }
            /* Prevent rows, paragraphs, and sub-cards from splitting */
            tr, p, .f-area { break-inside: avoid; page-break-inside: avoid; }
            /* Attendance scoring rows and rating cards stay whole */
            .print-sec > div > div { break-inside: avoid; page-break-inside: avoid; }
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
                <h1 class="text-base font-black leading-tight">{{ $qLabel }} Performance Evaluation · {{ $currentFinancialYear }}</h1>
                <p class="text-white/65 text-[10px] mt-0.5">{{ $currentUserName }} · {{ $userPosition }} · {{ $departmentName }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($isAppraiserView ?? false)
            {{-- ── Appraiser buttons ── --}}
            @if($myLevelLocked ?? false)
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-full shadow-sm">
                ✓ Your section is submitted
            </span>
            @else
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-blue-700 border border-blue-200 px-3 py-1.5 rounded-full shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span> Appraiser View · {{ $currentUserName }}
            </span>
            @endif
            @elseif(($status ?? 'draft') === 'submitted')
            {{-- ── Submitted — awaiting appraiser ── --}}
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-blue-700 border border-blue-200 px-3 py-1.5 rounded-full shadow-sm">
                ✓ Submitted · Awaiting Appraiser
            </span>
            @elseif(($status ?? 'draft') === 'appraised')
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-amber-700 border border-amber-200 px-3 py-1.5 rounded-full shadow-sm">
                ✍ Appraised · Your Signature Needed
            </span>
            @elseif(($status ?? 'draft') === 'completed')
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-full shadow-sm">
                ✓ Completed
            </span>
            @elseif($isWindowOpen)
            {{-- ── Appraisee — window badge only; buttons are at page bottom ── --}}
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-full shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> ⚠️ Window Open · Until {{ $windowEnd }}
            </span>
            @elseif($isFuture ?? false)
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-sky-700 border border-sky-200 px-3 py-1.5 rounded-full shadow-sm">
                ⏳ Not Yet Open · Opens {{ $windowStart }}
            </span>
            @else
            <span class="flex items-center gap-1.5 text-[10px] font-bold bg-white text-slate-500 border border-slate-200 px-3 py-1.5 rounded-full shadow-sm">
                🔒 Window Closed · {{ $windowStart }} – {{ $windowEnd }}
            </span>
            @endif
            <button onclick="window.print()" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-xl font-bold text-xs transition border border-white/20 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / PDF
            </button>
        </div>
    </div>
    {{-- Status banner --}}
    @if($isAppraiserView ?? false)
    @php
        $appraiserLevelInstructions = match($appraiserLevel ?? 'manager') {
            'vp'  => 'Fill in your Section 7 (VP) remarks and recommendation.',
            'slt' => 'Fill in your Section 7 (SLT) remarks and recommendation.',
            default => 'Fill in Section 2 (Appraiser Score), Section 3 (Superior Rating & Comment), Section 4 (Attendance), Section 6B, and Section 7 (Manager), then mark as Appraised.',
        };
    @endphp
    <div class="mt-2 rounded-xl bg-blue-50 border border-blue-200 px-4 py-2 flex items-center gap-2 text-xs text-blue-700">
        👁 <span><strong>Appraiser view.</strong> {{ $appraiserLevelInstructions }}</span>
        @if($submittedAt)<span class="ml-auto text-blue-500 font-medium">Submitted: {{ \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }}</span>@endif
    </div>
    @elseif(($status ?? 'draft') === 'submitted')
    <div class="mt-2 rounded-xl bg-blue-50 border border-blue-200 px-4 py-2 flex items-center gap-2 text-xs text-blue-700">
        ✓ <span><strong>Submitted.</strong> Awaiting appraiser review. Form is now read-only.</span>
        @if($submittedAt)<span class="ml-auto text-blue-500 font-medium">Submitted: {{ \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }}</span>@endif
    </div>
    @elseif(($status ?? 'draft') === 'appraised')
    <div class="mt-2 rounded-xl bg-amber-50 border border-amber-200 px-4 py-2 flex items-center gap-2 text-xs text-amber-700">
        ✍ <span><strong>Appraised.</strong> Your appraiser has reviewed and signed. Please read their feedback below and sign to acknowledge.</span>
        @if($submittedAt)<span class="ml-auto text-amber-500 font-medium">Updated: {{ \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }}</span>@endif
    </div>
    @elseif(($status ?? 'draft') === 'completed')
    <div class="mt-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-2 flex items-center gap-2 text-xs text-emerald-700">
        ✓ <span><strong>Completed.</strong> You've signed and acknowledged this appraisal.</span>
        @if($submittedAt)<span class="ml-auto text-emerald-500 font-medium">Updated: {{ \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }}</span>@endif
    </div>
    @elseif($isWindowOpen)
    <div class="mt-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-2 flex items-center gap-2 text-xs text-emerald-700">
        ⚠️ <span><strong>Evaluation window is open.</strong> Save draft anytime. Submit when ready — you cannot edit after submitting.</span>
        @if($submittedAt)<span class="ml-auto text-emerald-500 font-medium">Last saved: {{ \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }}</span>@endif
    </div>
    @elseif($isFuture ?? false)
    <div class="mt-2 rounded-xl bg-sky-50 border border-sky-200 px-4 py-2 flex items-center gap-2 text-xs text-sky-700">
        ⏳ <span><strong>Evaluation not yet open.</strong> This quarter's window opens {{ $windowStart }} – {{ $windowEnd }}. Form is read-only until then.</span>
    </div>
    @else
    <div class="mt-2 rounded-xl bg-slate-100 border border-slate-200 px-4 py-2 flex items-center gap-2 text-xs text-slate-500">
        🔒 <span><strong>Window closed.</strong> Evaluation period was {{ $windowStart }} – {{ $windowEnd }}.</span>
        @if($submittedAt)<span class="ml-auto font-medium">Last saved: {{ \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d M Y, H:i') }}</span>@endif
    </div>
    @endif
</div>

{{-- Print table: thead repeats company name+Q2 on every page --}}
@php
    // Lock form body server-side — appraiser view is unlocked here and handled by JS.
    // 'appraised' uses a soft JS-level lock (not `inert`) so the acknowledgment
    // panel can be selectively unlocked for the appraisee to sign.
    $formLocked  = !($isAppraiserView ?? false)
                && (!$isWindowOpen || ($status ?? 'draft') === 'submitted');
    $softLocked  = !($isAppraiserView ?? false) && ($status ?? 'draft') === 'appraised';
@endphp
<div id="form-body"@if($formLocked) inert style="pointer-events:none;user-select:none;"@endif>
<table id="print-table">
<thead id="print-thead">
<tr><td>
    <div style="display:flex;justify-content:space-between;align-items:center;width:100%">
        <span style="font-size:12px;font-weight:900;color:#1a3d34">{{ session('company_display_name') }}</span>
        <div style="display:flex;flex-direction:column;align-items:center;gap:2px">
            <div style="width:40px;height:40px;border-radius:9px;background:linear-gradient(135deg,#1a3d34,#6B9080);display:flex;align-items:center;justify-content:center">
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

        {{-- Doc header (hidden in print — replaced by print-thead) --}}
        <div id="doc-hdr" class="flex items-center justify-between mb-7 pb-6 border-b border-slate-100">
            <p class="text-xl font-black text-[#1a3d34]">{{ session('company_display_name') }}</p>
            @php
                // This tile's own accent — the user's configured "1st accent"
                // (theme_accent) when they've set one, black by default
                // otherwise (deliberately not the shared gold default every
                // other .theme-header-banner element falls back to).
                $qBadgeAccent = session('theme_accent') ?: '#000000';
            @endphp
            <div class="flex flex-col items-center gap-0.5">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-lg" style="background: linear-gradient(135deg, color-mix(in srgb, {{ $qBadgeAccent }} 85%, white), {{ $qBadgeAccent }} 45%, color-mix(in srgb, {{ $qBadgeAccent }} 60%, black));">
                    <span class="text-xl font-black text-white">{{ $qLabel }}</span>
                </div>
                <span class="text-[9px] font-bold text-[#6B9080] uppercase tracking-widest">{{ $currentFinancialYear }}</span>
            </div>
        </div>

        {{-- Title --}}
        <div class="text-center mb-7">
            <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-[.22em] mb-3">— Private &amp; Confidential —</p>
            <h2 class="text-lg font-black text-[#1a3d34] uppercase tracking-[.06em] mb-1">Executive / Non-Executive Performance Appraisal</h2>
            <div class="flex items-center justify-center gap-2 mt-2">
                <span class="h-px w-12 bg-[#6B9080]/30"></span>
                <span class="text-[10px] font-semibold text-[#6B9080] uppercase tracking-widest">Complete Report · Quarter {{ $displayQuarter }} · {{ $currentFinancialYear }}</span>
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
                    @foreach([['id'=>'por_confirmation','label'=>'Confirmation'],['id'=>'por_quarterly','label'=>'Quarterly Review'],['id'=>'por_others','label'=>'Others']] as $opt)
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <span class="w-4 h-4 rounded border-2 border-[#6B9080]/50 flex items-center justify-center"><input type="checkbox" id="{{ $opt['id'] }}" name="{{ $opt['id'] }}" {{ $opt['id']==='por_quarterly'?'checked':'' }} class="sr-only peer"><span class="w-2.5 h-2.5 rounded-sm bg-[#6B9080] hidden peer-checked:block"></span></span>
                        <span class="text-[11px] font-semibold text-slate-700">{{ $opt['label'] }}</span>
                    </label>
                    @endforeach
                </div>
                <div class="flex-1 min-w-48">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Please specify (if Others)</p>
                    <input type="text" name="por_other_text" placeholder="Describe purpose…" class="f-input">
                </div>
                <div class="text-right">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Year / Period</p>
                    <p class="text-base font-black text-[#1a3d34]">{{ now()->year }} <span class="text-[#6B9080]">/</span> {{ $qLabel }}</p>
                </div>
            </div>
        </div>

        {{-- Employee strip --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-7">
            <table class="w-full text-xs">
                <tbody>
                @php $fields=[['label'=>'Name','value'=>$currentUserName],['label'=>'Current Position','value'=>$userPosition],['label'=>'Reporting To (Appraiser)','value'=>$reportsToName],['label'=>'Department / Division','value'=>$departmentName],['label'=>'Year / Period Under Review','value'=>$currentFinancialYear.' / '.$qLabel]]; @endphp
                @foreach($fields as $i => $f)
                <tr class="{{ $i%2===0?'bg-white':'bg-slate-50/60' }} {{ $i<count($fields)-1?'border-b border-[#6B9080]/12':'' }}">
                    <td class="px-5 py-3 w-52 border-r border-[#6B9080]/12"><span class="f-label">{{ $f['label'] }}</span></td>
                    <td class="px-5 py-3"><span class="f-val">{{ $f['value'] }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             SECTION 1 — EMPLOYEE PARTICULARS
        ═══════════════════════════════════════════════════════ --}}
        <div id="sec1-particulars" class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6 print-sec">
            <div class="sec-bar"><div class="sec-num">1</div><span class="sec-title">To Be Completed by Employee Under Review</span></div>
            <div class="px-6 py-6 space-y-7">

                <div>
                    <div class="part-label">Part A &nbsp;·&nbsp; Employee's Particulars</div>
                    <div class="grid grid-cols-2 gap-x-10 gap-y-5">
                        <div><p class="f-label">Name</p><p class="f-val">{{ $currentUserName }}</p><div class="mt-1 h-px bg-slate-200"></div></div>
                        <div><p class="f-label">Start Date</p><input type="text" name="start_date" value="{{ $joinDate !== '—' ? $joinDate : '' }}" placeholder="DD MMM YYYY" class="f-input"></div>
                        <div><p class="f-label">Current Position</p><p class="f-val">{{ $userPosition }}</p><div class="mt-1 h-px bg-slate-200"></div></div>
                        <div><p class="f-label">Department / Division</p><p class="f-val">{{ $departmentName }}</p><div class="mt-1 h-px bg-slate-200"></div></div>
                        <div><p class="f-label">Date Joined</p><p class="f-val">{{ $joinDate }}</p><div class="mt-1 h-px bg-slate-200"></div></div>
                        <div><p class="f-label">Months / Years of Service</p><p class="f-val">{{ $tenure }}</p><div class="mt-1 h-px bg-slate-200"></div></div>
                    </div>
                    @php
                        $ytdYear  = now()->year;
                        $ytdMonth = \Carbon\Carbon::create()->month(now()->month)->format('M');
                        $partAFields = [
                            'Medical Leave (Days)'   => $attendanceYTD['mc_days'],
                            'Emergency Leave (Days)' => $attendanceYTD['other_leave_days'],
                            'No. of Lateness'        => $attendanceYTD['late_count'],
                        ];
                    @endphp
                    <div class="mt-5">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;color:#6B9080;">Annual Attendance</span>
                            <span style="font-size:9px;font-weight:700;color:#94a3b8;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:2px 8px;">Jan – {{ $ytdMonth }} {{ $ytdYear }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-6">
                        @foreach($partAFields as $lf => $lv)
                        <div>
                            <p class="f-label">{{ $lf }}</p>
                            <p class="f-val" style="padding:5px 0;pointer-events:none;user-select:none;-webkit-user-select:none;">{{ $attendanceYTD['has_data'] ? $lv : '0' }}</p>
                        </div>
                        @endforeach
                        </div>
                    </div>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                <div>
                    <div class="part-label">Part B &nbsp;·&nbsp; Summary of Duties &amp; Achievements</div>
                    <p class="text-[11px] text-slate-400 italic mb-3">Summarize present duties and indicate if any key tasks set for the year / period have been achieved.</p>
                    <textarea name="part_b" class="f-area" placeholder="Write your summary here…" rows="4"></textarea>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                <div>
                    <div class="part-label">Part C &nbsp;·&nbsp; Key Tasks for Forthcoming Period</div>
                    <p class="text-[11px] text-slate-400 italic mb-3"><em>[Where applicable]</em> List what you see as your key tasks for the forthcoming year / period.</p>
                    <textarea name="part_c" class="f-area" placeholder="List your key tasks…" rows="4"></textarea>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                <div>
                    <div class="part-label">Part D &nbsp;·&nbsp; Appraiser Confirmation</div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
                        <p class="text-[11px] text-slate-500 italic leading-relaxed mb-4">
                            I hereby confirm that the above information provided by the appraisee is correct and that the appraisee has been directly reporting to me since
                            <span class="partd-wrap mx-1">
                                <span id="partd-val" class="partd-val"></span>
                                <span id="partd-cal" class="partd-cal" onclick="openPartDPicker()" title="Select date">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                </span>
                                <button type="button" id="partd-clr" class="partd-clr" onclick="clearPartDDate()" title="Clear date">×</button>
                                <input type="date" id="partd-date" name="partd_date" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;" onchange="setPartDDate(this.value)">
                            </span>.
                        </p>
                        <div class="grid grid-cols-2 gap-6">
                            <div><p class="f-label">Appraiser Name</p><input type="text" name="part_d_name" value="{{ $reportsToName !== '-' ? $reportsToName : '' }}" placeholder="Full name" class="f-input"></div>
                            <div><p class="f-label">Designation</p><input type="text" name="part_d_designation" value="{{ $reportsToPosition !== '-' ? $reportsToPosition : '' }}" placeholder="Job title" class="f-input"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             SECTION 2 — OKR / KPI
        ═══════════════════════════════════════════════════════ --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6 print-sec">
            <div class="sec-bar"><div class="sec-num">2</div><span class="sec-title">OKR / KPI Quarterly Performance Review &nbsp;·&nbsp; {{ $qLabel }}</span></div>

            @if(empty($kpis))
            <div class="p-10 text-center"><p class="text-slate-400 text-sm">No KPIs found for {{ $currentFinancialYear }}.</p></div>
            @else
            <div class="overflow-x-auto">
            <table class="doc-tbl" id="sec2Table" style="min-width:700px;">
                <thead>
                    <tr>
                        <th class="c" style="width:44px;">No.</th>
                        <th class="l">Initiative</th>
                        <th class="c" style="width:68px;">A<br><span style="font-weight:500;text-transform:none;font-size:8px;">Actual</span></th>
                        <th class="c" style="width:68px;">B<br><span style="font-weight:500;text-transform:none;font-size:8px;">Target</span></th>
                        <th class="c" style="width:72px;">C · Score<br><span style="font-weight:400;text-transform:none;font-size:8px;">(A÷B)×5</span></th>
                        <th class="c" style="width:72px;">Appraiser<br><span style="font-weight:400;text-transform:none;font-size:8px;">Score</span></th>
                        @if($isAppraiserView ?? false)
                        <th class="c no-print" style="width:64px;">Details</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @php
                    $sec2Colspan = ($isAppraiserView ?? false) ? 7 : 6;
                    $sec2StatusEchoes = ['not started', 'on track', 'at risk', 'in trouble', 'completed'];
                    $sec2IsRealRemark = fn ($text) => $text !== '' && !in_array(strtolower($text), $sec2StatusEchoes, true);
                    // Actual/Target were rendered as bare numbers with no unit cue --
                    // 1900000 reads identically whether it's a plain count, a currency
                    // amount, or (if it happened to be small) a percentage. Formatting
                    // per kpi.unit so each is unambiguous at a glance: "number" gets a
                    // thousands separator only, "currency" gets an RM prefix + 2dp,
                    // "percentage" gets 2dp + a % suffix. Mirrors the fmtLnkVal helper
                    // already used for this on kpi/index.blade.php's linkage panel.
                    $sec2FmtVal = function ($v, $u) {
                        if ($v === null || $v === '') return '—';
                        $n = (float) $v;
                        if ($u === 'currency')   return 'RM ' . number_format($n, 2);
                        if ($u === 'percentage') return number_format($n, 2) . '%';
                        return number_format($n, 0);
                    };
                    $categoryOrder = ['Financial', 'Growth & Customer', 'Initiatives', 'People'];
                    $sec2Raw = [];
                    foreach ($kpis as $kpi) {
                        $cat = $kpi['category'] ?? 'Uncategorized';
                        $sub = $kpi['sub_category'] ?? 'General';
                        $sec2Raw[$cat][$sub][] = $kpi;
                    }
                    // Sort categories: defined order first, then alphabetical for any extras
                    $sec2Grouped = [];
                    foreach ($categoryOrder as $co) {
                        if (isset($sec2Raw[$co])) {
                            $sec2Grouped[$co] = $sec2Raw[$co];
                            unset($sec2Raw[$co]);
                        }
                    }
                    foreach ($sec2Raw as $co => $subs) { $sec2Grouped[$co] = $subs; }
                    // Sort sub-categories within each category alphabetically
                    foreach ($sec2Grouped as $cat => &$subs) { ksort($subs); }
                    unset($subs);
                    $subCatNo = 0;
                @endphp
                @foreach($sec2Grouped as $catName => $subCats)
                <tr class="cat-hdr"><td colspan="{{ $sec2Colspan }}">{{ $catName }}</td></tr>
                @foreach($subCats as $subName => $subKpis)
                @php $subCatNo++; $subItemNo = 0; @endphp
                <tr class="subcat-hdr"><td colspan="{{ $sec2Colspan }}">{{ $subName }}</td></tr>
                @foreach($subKpis as $kpi)
                @php
                    $subItemNo++;
                    $qData  = ($allQuarters[$kpi['id']] ?? [])[$qLabel] ?? null;
                    $qTitle = $qData['quarter_title'] ?? '';
                    $qAct   = isset($qData['quarter_actual']) ? (float)$qData['quarter_actual'] : '';
                    $qTgt   = isset($qData['quarter_target']) ? (float)$qData['quarter_target'] : (float)($kpi['base_target'] ?? '');

                    $qRemarkRaw = trim($qData['remark'] ?? '');
                    $qReviewRaw = trim($qData['completion_review'] ?? '');
                    $qStatus    = strtolower(trim($qData['status'] ?? ''));
                    // Once a quarter is marked "completed" it carries a completion
                    // review (written when the appraisee submitted proof) — show
                    // that instead of the plain remark, since it's the more
                    // complete, purpose-written account of what was delivered.
                    $qRemarkText = ($qStatus === 'completed' && $qReviewRaw !== '')
                        ? $qReviewRaw
                        : ($sec2IsRealRemark($qRemarkRaw)
                            ? $qRemarkRaw
                            : ($sec2IsRealRemark($qReviewRaw) ? $qReviewRaw : ''));

                    $qProofFiles = [];
                    if (!empty($qData['completion_proof_urls'])) {
                        $qDecoded = json_decode($qData['completion_proof_urls'], true);
                        if (is_array($qDecoded)) $qProofFiles = $qDecoded;
                    }

                    $qProgressPct = ($qTgt !== '' && (float)$qTgt > 0)
                        ? round(((float)$qAct) / ((float)$qTgt) * 100, 1)
                        : 0;

                    $qDetail = [
                        'kpi_id'   => $kpi['id'],
                        'title'    => $kpi['kpi_title'] ?? '',
                        'weight'   => $kpi['weightage'] ?? null,
                        'quarter'  => $qLabel,
                        'subtitle' => $qTitle,
                        'unit'     => $kpi['unit'] ?? 'number',
                        'actual'   => $qAct !== '' ? $qAct : null,
                        'target'   => $qTgt !== '' ? $qTgt : null,
                        'progress' => $qProgressPct,
                        'remark'   => $qRemarkText,
                        'files'    => $qProofFiles,
                    ];
                @endphp
                <tr class="{{ $subItemNo%2===0?'':'bg-slate-50/40' }} sec2-row">
                    <td class="text-center text-[10px] font-bold text-[#1a3d34]">{{ $subCatNo }}.{{ $subItemNo }}</td>
                    <td style="padding:8px 12px;">
                        <div style="font-size:10px;font-weight:700;color:#1a3d34;margin-bottom:3px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                            <span>{{ $kpi['kpi_title'] }}</span>
                            <span style="flex-shrink:0;font-size:8px;font-weight:900;color:#6B9080;background:#fff;border:1px solid rgba(107,144,128,.25);padding:2px 7px;border-radius:999px;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;">{{ $kpi['weightage']??'—' }}% weight</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span class="q-tag">{{ $qLabel }}</span>
                            <input type="text" name="kpi_title_{{ $kpi['id'] }}" value="{{ $qTitle }}" placeholder="Describe initiative for {{ $qLabel }}…" class="t-input">
                        </div>
                    </td>
                    <td class="text-center"><span class="sec2-actual font-bold text-sm text-slate-700" data-val="{{ $qAct !== '' ? $qAct : '' }}">{{ $sec2FmtVal($qAct !== '' ? $qAct : null, $kpi['unit'] ?? 'number') }}</span></td>
                    <td class="text-center"><span class="sec2-target font-bold text-sm text-slate-500" data-val="{{ $qTgt !== '' ? $qTgt : '' }}">{{ $sec2FmtVal($qTgt !== '' ? $qTgt : null, $kpi['unit'] ?? 'number') }}</span></td>
                    <td class="text-center">
                        <span class="sec2-score font-black text-sm sc-none">—</span>
                        <input type="hidden" name="kpi_self_{{ $kpi['id'] }}" data-wt="{{ $kpi['weightage'] ?? 0 }}" class="kpi-self-hidden">
                    </td>
                    <td class="text-center"><input type="number" inputmode="decimal" name="kpi_app_{{ $kpi['id'] }}" data-wt="{{ $kpi['weightage'] ?? 0 }}" step="0.1" min="0" max="5" placeholder="—" class="n-input kpi-app-input" readonly style="pointer-events:none;opacity:0.55;background:#f8fafc;cursor:not-allowed;"></td>
                    @if($isAppraiserView ?? false)
                    <td class="text-center no-print">
                        <input type="hidden" name="kpi_comment_{{ $kpi['id'] }}" class="kpi-comment-hidden">
                        <button type="button" class="kpi-view-btn kpi-comment-btn" data-kpi-id="{{ $kpi['id'] }}" data-detail="{{ json_encode($qDetail, JSON_UNESCAPED_UNICODE) }}" onclick="openQuarterDetail(this)" style="display:inline-flex;align-items:center;gap:4px;font-size:9px;font-weight:800;color:#94a3b8;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:5px 9px;cursor:pointer;">👁 <span class="kpi-comment-label">View</span></button>
                    </td>
                    @endif
                </tr>
                @endforeach
                @endforeach
                @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:rgba(26,61,52,.06);">
                        <td colspan="4" class="text-right font-black text-xs text-[#1a3d34] uppercase tracking-wide px-4 py-3">Total Score Section 2</td>
                        <td class="text-center py-3"><span id="sec2Total" class="font-black text-base sc-none">—</span></td>
                        <td class="text-center"><span id="sec2AppPct" class="text-xs font-bold text-slate-400">—</span></td>
                        @if($isAppraiserView ?? false)<td class="no-print"></td>@endif
                    </tr>
                    <tr style="background:rgba(26,61,52,.03);">
                        <td colspan="4" class="text-right text-[9px] font-bold text-slate-400 uppercase tracking-wide px-4 py-2">% Total (Score ÷ 30 × 70)</td>
                        <td colspan="2" class="text-center"><span id="sec2Pct" class="text-sm font-black text-slate-400">—</span></td>
                        @if($isAppraiserView ?? false)<td class="no-print"></td>@endif
                    </tr>
                </tfoot>
            </table>
            </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════
             SECTION 3 — ATTITUDE & COMPETENCY
        ═══════════════════════════════════════════════════════ --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6 print-sec">
            <div class="sec-bar"><div class="sec-num">3</div><span class="sec-title">Attitude &amp; Competency Assessment</span></div>

            <div class="px-6 pt-5 pb-4 border-b border-slate-100 rating-scale-legend">
                <p class="text-[9px] font-black text-[#6B9080] uppercase tracking-widest mb-3">Rating Scale</p>
                <div class="flex gap-2 flex-wrap">
                    @php $ratingLegend=[
                        ['score'=>1,'cat'=>'Unsatisfactory','def'=>'Inadequate; counselling or appropriate action required.',             'bg'=>'bg-red-50','border'=>'border-red-200','text'=>'text-red-700','num'=>'bg-red-500'],
                        ['score'=>2,'cat'=>'Below Average', 'def'=>'Below expectations, requires improvement and remedial steps.',        'bg'=>'bg-amber-50','border'=>'border-amber-200','text'=>'text-amber-700','num'=>'bg-amber-400'],
                        ['score'=>3,'cat'=>'Average',       'def'=>'Meets the normal requirements of the position.',                      'bg'=>'bg-slate-50','border'=>'border-slate-200','text'=>'text-slate-600','num'=>'bg-slate-400'],
                        ['score'=>4,'cat'=>'Above Average', 'def'=>'Consistently meets all requirements, exceeds in major aspects.',      'bg'=>'bg-[#6B9080]/5','border'=>'border-[#6B9080]/25','text'=>'text-[#1a3d34]','num'=>'bg-[#6B9080]'],
                        ['score'=>5,'cat'=>'Outstanding',   'def'=>'Exceptional performance, high initiative, sound judgement.',          'bg'=>'bg-emerald-50','border'=>'border-emerald-200','text'=>'text-emerald-700','num'=>'bg-emerald-500'],
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

            <div class="overflow-x-auto">
            <table class="doc-tbl" style="min-width:680px;">
                <thead>
                    <tr>
                        <th class="l" style="width:40%;">Area / Assessment</th>
                        <th class="c" style="width:140px;">Self Rating<br><span style="font-weight:400;text-transform:none;font-size:8px;">Pick 1 – 5</span></th>
                        <th class="c" style="width:140px;">Superior Rating<br><span style="font-weight:400;text-transform:none;font-size:8px;">Pick 1 – 5</span></th>
                        <th class="l">Appraiser's Comment</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($assessmentAreas as $i => $area)
                <tr class="{{ $i%2===0?'bg-white':'bg-slate-50/50' }}" style="border-bottom:1px solid rgba(107,144,128,.10);">
                    <td style="padding:14px 16px;">
                        <p style="font-size:11px;font-weight:800;color:#1e293b;margin-bottom:3px;">{{ $area['no'] }}) {{ $area['title'] }}</p>
                        <p style="font-size:10px;color:#94a3b8;line-height:1.55;font-style:italic;">{{ $area['description'] }}</p>
                    </td>
                    <td style="padding:10px 8px;vertical-align:top;" class="text-center">
                        <p style="font-size:8px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">Self</p>
                        <div class="rating-group justify-center">
                            @foreach([1,2,3,4,5] as $sc)<input type="radio" id="s_{{ $area['no'] }}_{{ $sc }}" name="self_{{ $area['no'] }}" value="{{ $sc }}"><label for="s_{{ $area['no'] }}_{{ $sc }}">{{ $sc }}</label>@endforeach
                        </div>
                    </td>
                    <td style="padding:10px 8px;vertical-align:top;background:rgba(107,144,128,.03);" class="text-center">
                        <p style="font-size:8px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">Superior</p>
                        <div class="rating-group justify-center sup-rating-group" style="pointer-events:none;opacity:0.5;">
                            @foreach([1,2,3,4,5] as $sc)<input type="radio" id="sup_{{ $area['no'] }}_{{ $sc }}" name="sup_{{ $area['no'] }}" value="{{ $sc }}"><label for="sup_{{ $area['no'] }}_{{ $sc }}">{{ $sc }}</label>@endforeach
                        </div>
                    </td>
                    <td style="padding:10px 12px;vertical-align:top;background:rgba(107,144,128,.03);">
                        <textarea name="att_comment_{{ $area['no'] }}" placeholder="Filled by appraiser…" rows="3" class="f-area att-comment-input" style="margin-top:6px;min-height:60px;font-size:11px;padding:6px 10px;pointer-events:none;opacity:0.5;" readonly></textarea>
                        <button type="button" class="att-rephrase-btn no-print" data-area-title="{{ $area['title'] }}" onclick="rephraseAttComment(this)" style="display:inline-flex;align-items:center;gap:3px;margin-top:6px;font-size:8px;font-weight:800;color:#4a7c6b;background:#f0f9f6;border:1px solid #d1e7e0;border-radius:6px;padding:3px 7px;cursor:pointer;pointer-events:none;opacity:0.5;">✨ Rephrase</button>
                    </td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:rgba(26,61,52,.06);">
                        <td style="padding:10px 16px;"><span style="font-size:9px;font-weight:800;color:#6B9080;text-transform:uppercase;letter-spacing:.08em;">No. of Areas Assessed: 12 &nbsp;·&nbsp; Formula: Total ÷ 60 × 25</span></td>
                        <td class="text-center py-3"><span id="s3Self" class="font-black text-base sc-none">—</span></td>
                        <td class="text-center"><span id="s3Sup" class="font-black text-base sc-none">—</span></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             SECTION 4 — ATTENDANCE
        ═══════════════════════════════════════════════════════ --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6 print-sec">
            <div class="sec-bar"><div class="sec-num">4</div><span class="sec-title">Attendance Record</span></div>
            <div class="px-6 py-6">
                @if($attendanceSummary['has_data'])
                    <p class="text-[11px] text-slate-400 italic mb-5">
                        Attendance data from HR import — {{ implode(', ', $attendanceSummary['months']) }}.
                    </p>
                    <div class="grid grid-cols-4 gap-4 mb-5">
                        @foreach([
                            ['label' => 'Working Days',   'value' => $attendanceSummary['working_days'],        'color' => 'text-slate-700'],
                            ['label' => 'Days Present',   'value' => $attendanceSummary['present_days'],        'color' => 'text-emerald-700'],
                            ['label' => 'Days Absent',    'value' => $attendanceSummary['absent_days'],         'color' => 'text-red-600'],
                            ['label' => 'Late Incidents', 'value' => $attendanceSummary['late_count'],          'color' => 'text-amber-600'],
                            ['label' => 'Insufficient',   'value' => $attendanceSummary['insufficient_count'],  'color' => 'text-purple-600'],
                            ['label' => 'Medical Leave',  'value' => $attendanceSummary['mc_days'],             'color' => 'text-blue-600'],
                            ['label' => 'Annual Leave',   'value' => $attendanceSummary['al_days'],             'color' => 'text-violet-600'],
                            ['label' => 'Other Leave',    'value' => $attendanceSummary['other_leave_days'],    'color' => 'text-slate-500'],
                        ] as $af)
                        <div class="border border-[#6B9080]/20 rounded-xl px-4 py-3 bg-slate-50/60 text-center">
                            <p class="text-[10px] text-slate-500 uppercase tracking-wide mb-1">{{ $af['label'] }}</p>
                            <p class="text-2xl font-black {{ $af['color'] }}">{{ $af['value'] }}</p>
                        </div>
                        @endforeach
                    </div>
                    @if($attendanceSummary['total_late_minutes'] > 0)
                    <p class="text-[11px] text-amber-600 mb-4">
                        Total late time: {{ intdiv($attendanceSummary['total_late_minutes'], 60) }}h {{ $attendanceSummary['total_late_minutes'] % 60 }}min across all late incidents.
                    </p>
                    @endif
                @else
                    <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl px-5 py-4 mb-5">
                        <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-[12px] text-slate-500">Attendance data for {{ $qLabel }} has not been imported yet. HR will upload the data via <strong>Import &amp; Analysis</strong>.</p>
                    </div>
                @endif

                {{-- ── Attendance Score Assessment ── --}}
                @php
                $attPrefill = [
                    1 => (int)($attendanceSummary['late_count']          ?? 0),
                    2 => (int)($attendanceSummary['absent_days']          ?? 0),
                    3 => (int)($attendanceSummary['insufficient_count']   ?? 0),
                    4 => (int)($attendanceSummary['other_leave_days']     ?? 0),
                    5 => 0,
                ];
                $attCriteria = [
                    1 => ['label' => 'Lateness',             'desc' => 'Arriving to work up to 30 minutes after start of official work hours.'],
                    2 => ['label' => 'Absent',               'desc' => 'No clock-in record.'],
                    3 => ['label' => 'Insufficient',         'desc' => 'Early departure — before end of official work hours.'],
                    4 => ['label' => 'EL / Unpaid Leave',    'desc' => 'Unplanned leave, especially delayed or unauthorised.'],
                    5 => ['label' => 'Disciplinary Matters', 'desc' => 'Issuance of Reminder, Warning, or Showcause Letters.'],
                ];
                @endphp
                <div class="mt-5 overflow-hidden rounded-xl border border-[#6B9080]/25">
                    <div style="background:rgba(26,61,52,.06);padding:10px 16px;border-bottom:1px solid rgba(107,144,128,.15);display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:10px;font-weight:900;color:#1a3d34;text-transform:uppercase;letter-spacing:.1em;">Attendance Score Assessment · {{ $qLabel }}</span>
                        <span style="font-size:9px;font-weight:700;color:#6B9080;">Auto-scored · Max 5 pts → 5% of overall &nbsp;·&nbsp; 0=1pt · 1–5=0.7 · 6–10=0.3 · ≥11=0</span>
                    </div>
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:rgba(107,144,128,.08);border-bottom:1px solid rgba(107,144,128,.15);">
                                <th style="padding:8px 14px;text-align:left;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#6B9080;width:145px;">Category</th>
                                <th style="padding:8px 14px;text-align:left;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#6B9080;">Description</th>
                                <th style="padding:8px 14px;text-align:center;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#6B9080;width:90px;">Count</th>
                                <th style="padding:8px 14px;text-align:center;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#6B9080;width:80px;">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attCriteria as $ai => $ac)
                            <tr style="border-bottom:1px solid rgba(107,144,128,.08);{{ $ai%2===0 ? 'background:#fff;' : 'background:rgba(107,144,128,.03);' }}">
                                <td style="padding:10px 14px;font-size:11px;font-weight:700;color:#1e293b;white-space:nowrap;">{{ $ac['label'] }}</td>
                                <td style="padding:10px 14px;font-size:11px;color:#64748b;">{{ $ac['desc'] }}</td>
                                <td style="padding:8px 10px;text-align:center;">
                                    <input type="number" name="att_count_{{ $ai }}" value="{{ $attPrefill[$ai] }}"
                                        min="0" step="1"
                                        class="att-count-input"
                                        style="width:60px;padding:4px 6px;text-align:center;font-size:13px;font-weight:700;border:1px solid #d1d5db;border-radius:6px;pointer-events:none;opacity:0.7;cursor:not-allowed;background:#f8fafc;">
                                </td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <span id="att-row-score-{{ $ai }}" style="font-size:14px;font-weight:900;color:#cbd5e1;">—</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:rgba(26,61,52,.06);border-top:1px solid rgba(107,144,128,.15);">
                                <td colspan="3" style="padding:10px 14px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#1a3d34;text-align:right;">Score Total</td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <span id="att-score-total" style="font-size:15px;font-weight:900;color:#cbd5e1;">—</span>
                                    <span style="font-size:9px;font-weight:600;color:#94a3b8;margin-left:3px;">/ 5 pts</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-5">
                    <p class="f-label mb-2">Remarks</p>
                    <textarea name="att_remarks" rows="3" placeholder="Enter attendance remarks or notes…" class="f-area"></textarea>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             SECTION 5 — CULTURE & VALUES (Q4 only)
        ═══════════════════════════════════════════════════════ --}}
        @if($quarter === 'Q4')
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6 print-sec">
            <div class="sec-bar"><div class="sec-num">5</div><span class="sec-title">Culture &amp; Values Assessment</span></div>
            <div class="px-6 py-6">
                <p class="text-[11px] text-slate-400 italic mb-5">Rate how consistently the employee demonstrates the company's core values.</p>
                <table class="doc-tbl mb-5">
                    <thead>
                        <tr>
                            <th class="l">Core Value</th>
                            <th class="c" style="width:140px;">Self Rating<br><span style="font-weight:400;text-transform:none;font-size:8px;">Pick 1 – 5</span></th>
                            <th class="c" style="width:140px;">Appraiser Rating<br><span style="font-weight:400;text-transform:none;font-size:8px;">Pick 1 – 5</span></th>
                            <th class="l">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $cultureValues=['Integrity & Honesty','Teamwork & Collaboration','Customer Focus','Innovation & Creativity','Accountability & Ownership','Continuous Learning']; @endphp
                    @foreach($cultureValues as $ci => $cv)
                    <tr class="{{ $ci%2===0?'bg-white':'bg-slate-50/50' }}" style="border-bottom:1px solid rgba(107,144,128,.10);">
                        <td style="padding:12px 16px;font-size:12px;font-weight:700;color:#1e293b;">{{ $cv }}</td>
                        <td class="text-center" style="padding:10px 8px;">
                            <div class="rating-group justify-center">
                                @foreach([1,2,3,4,5] as $sc)<input type="radio" id="cv_s_{{ $ci }}_{{ $sc }}" name="cv_self_{{ $ci }}" value="{{ $sc }}"><label for="cv_s_{{ $ci }}_{{ $sc }}">{{ $sc }}</label>@endforeach
                            </div>
                        </td>
                        <td class="text-center" style="padding:10px 8px;">
                            <div class="rating-group justify-center cv-app-rating-group" style="pointer-events:none;opacity:0.5;">
                                @foreach([1,2,3,4,5] as $sc)<input type="radio" id="cv_a_{{ $ci }}_{{ $sc }}" name="cv_app_{{ $ci }}" value="{{ $sc }}"><label for="cv_a_{{ $ci }}_{{ $sc }}">{{ $sc }}</label>@endforeach
                            </div>
                        </td>
                        <td style="padding:10px 12px;"><input type="text" name="cv_remark_{{ $ci }}" placeholder="Remarks…" class="t-input cv-remark-input" style="pointer-events:none;opacity:0.5;" readonly></td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                <div>
                    <p class="f-label mb-2">Overall Culture Remarks</p>
                    <textarea name="cv_overall" rows="3" placeholder="Enter overall remarks on culture and values…" class="f-area"></textarea>
                </div>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════
             SECTION 6 — PERFORMANCE SUMMARY
        ═══════════════════════════════════════════════════════ --}}
        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-6 print-sec">
            <div class="sec-bar"><div class="sec-num">6</div><span class="sec-title">Performance Summary &amp; Analysis</span></div>
            <div class="px-6 py-6 space-y-6">

                <div>
                    <div class="part-label">A &nbsp;·&nbsp; Rating Summary</div>
                    <p class="text-[11px] text-slate-400 italic mb-5">Combined score from all sections of this appraisal review.</p>
                    <div class="grid grid-cols-2 gap-6 items-start">
                        <div class="border border-[#6B9080]/25 rounded-xl overflow-hidden">
                            <table class="doc-tbl">
                                <thead><tr><th class="l">Section</th><th class="c" style="width:100px;">Self Score</th><th class="c" style="width:100px;">Appraiser</th></tr></thead>
                                @php
                                    $s4ScoreVal = null;
                                    if (($attendanceSummary['has_data'] ?? false) && ($attendanceSummary['working_days'] ?? 0) > 0) {
                                        $s4ScoreVal = round($attendanceSummary['present_days'] / $attendanceSummary['working_days'] * 100, 1);
                                    }
                                @endphp
                                <tbody>
                                    <tr class="bg-white" style="border-bottom:1px solid rgba(107,144,128,.10);">
                                        <td style="padding:12px 14px;"><p style="font-size:11px;font-weight:700;color:#334155;">Section 2</p><p style="font-size:9px;color:#94a3b8;">KPI Performance (70%)</p></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s2_self" style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s2_self" id="hid_s6_s2_self"></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s2_app"  style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s2_app"  id="hid_s6_s2_app"></td>
                                    </tr>
                                    <tr class="bg-slate-50/50" style="border-bottom:1px solid rgba(107,144,128,.10);">
                                        <td style="padding:12px 14px;"><p style="font-size:11px;font-weight:700;color:#334155;">Section 3</p><p style="font-size:9px;color:#94a3b8;">Attitude &amp; Competency (25%)</p></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s3_self" style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s3_self" id="hid_s6_s3_self"></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s3_app"  style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s3_app"  id="hid_s6_s3_app"></td>
                                    </tr>
                                    <tr class="bg-white" style="border-bottom:1px solid rgba(107,144,128,.10);">
                                        <td style="padding:12px 14px;"><p style="font-size:11px;font-weight:700;color:#334155;">Section 4</p><p style="font-size:9px;color:#94a3b8;">Attendance (5%)</p></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s4_self" style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s4_self" id="hid_s6_s4_self"></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s4_app"  style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s4_app"  id="hid_s6_s4_app"></td>
                                    </tr>
                                    @if($quarter === 'Q4')
                                    <tr class="bg-slate-50/50" style="border-bottom:1px solid rgba(107,144,128,.10);">
                                        <td style="padding:12px 14px;"><p style="font-size:11px;font-weight:700;color:#334155;">Section 5</p><p style="font-size:9px;color:#94a3b8;">Culture &amp; Values (5%)</p></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s5_self" style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s5_self" id="hid_s6_s5_self"></td>
                                        <td class="text-center" style="padding:12px;"><span id="disp_s6_s5_app"  style="font-size:18px;font-weight:900;color:#cbd5e1;">—</span><input type="hidden" name="s6_s5_app"  id="hid_s6_s5_app"></td>
                                    </tr>
                                    @endif
                                    <tr style="background:linear-gradient(90deg,rgba(26,61,52,.06),rgba(107,144,128,.04));">
                                        <td style="padding:12px 14px;"><p style="font-size:12px;font-weight:900;color:#1a3d34;text-transform:uppercase;letter-spacing:.05em;">Total Rating</p><p style="font-size:9px;color:#94a3b8;">Combined score</p></td>
                                        <td class="text-center" style="padding:12px;"><span id="s6SelfTotal" style="font-size:20px;font-weight:900;color:#cbd5e1;">—</span></td>
                                        <td class="text-center" style="padding:12px;"><span id="s6AppTotal" style="font-size:20px;font-weight:900;color:#cbd5e1;">—</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Bell curve: right column, side-by-side with rating table --}}
                        <div>
                            <p class="f-label mb-3">Performance Distribution</p>
                            <div style="background:linear-gradient(180deg,#f7faf9 0%,#ffffff 100%);border-radius:16px;padding:20px 12px 12px;border:1px solid rgba(107,144,128,.15);">
                            <svg viewBox="0 0 1000 370" style="width:100%;display:block;overflow:visible;" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    {{-- Gaussian bell: σ=150, 4 equal zones of 250px, peak at x=500 y=40, baseline y=300 --}}
                                    <clipPath id="bc_clip">
                                        <path d="M 0,300 L 0,299 C 80,299 160,273 200,265 C 250,254 330,130 400,92 C 445,58 470,43 500,40 C 530,43 555,58 600,92 C 670,130 750,254 800,265 C 840,273 920,299 1000,299 L 1000,300 Z"/>
                                    </clipPath>
                                    <filter id="bc_shadow" x="-30%" y="-80%" width="160%" height="280%">
                                        <feDropShadow dx="0" dy="2" stdDeviation="5" flood-opacity="0.18"/>
                                    </filter>
                                    {{-- Soft glow for the live-score dot --}}
                                    <filter id="bc_glow" x="-150%" y="-150%" width="400%" height="400%">
                                        <feGaussianBlur stdDeviation="6" result="blur"/>
                                        <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                                    </filter>
                                    {{-- Zone gradients: subtle light-to-rich for depth --}}
                                    <linearGradient id="gradZone1" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#FF6B6E"/><stop offset="100%" stop-color="#ED1C24"/>
                                    </linearGradient>
                                    <linearGradient id="gradZone2" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#FFB347"/><stop offset="100%" stop-color="#FF8C00"/>
                                    </linearGradient>
                                    <linearGradient id="gradZone3" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#FFEB80"/><stop offset="100%" stop-color="#FFD700"/>
                                    </linearGradient>
                                    <linearGradient id="gradZone4" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#4CD787"/><stop offset="100%" stop-color="#00B050"/>
                                    </linearGradient>
                                </defs>
                                {{-- Soft cast shadow under the curve for a floating look --}}
                                <ellipse cx="500" cy="304" rx="460" ry="7" fill="#0f172a" opacity="0.14" filter="url(#bc_shadow)"/>
                                {{-- 4 equal zones of 250px each --}}
                                <rect x="0"   y="0" width="250" height="300" fill="url(#gradZone1)" clip-path="url(#bc_clip)"/>
                                <rect x="250" y="0" width="250" height="300" fill="url(#gradZone2)" clip-path="url(#bc_clip)"/>
                                <rect x="500" y="0" width="250" height="300" fill="url(#gradZone3)" clip-path="url(#bc_clip)"/>
                                <rect x="750" y="0" width="250" height="300" fill="url(#gradZone4)" clip-path="url(#bc_clip)"/>
                                {{-- Zone dividers --}}
                                <line x1="250" y1="235" x2="250" y2="300" stroke="rgba(255,255,255,.6)" stroke-width="2.5"/>
                                <line x1="500" y1="40"  x2="500" y2="300" stroke="rgba(255,255,255,.85)" stroke-width="3.5"/>
                                <line x1="750" y1="235" x2="750" y2="300" stroke="rgba(255,255,255,.6)" stroke-width="2.5"/>
                                {{-- Baseline --}}
                                <line x1="0" y1="300" x2="1000" y2="300" stroke="#e2e8f0" stroke-width="1"/>
                                {{-- Zone name badges (bg = zone colour, text = white on red/green, black on yellow/orange) --}}
                                <rect x="50"  y="306" width="150" height="22" rx="11" fill="#ED1C24" filter="url(#bc_shadow)"/>
                                <text x="125" y="321" text-anchor="middle" fill="#FFFFFF" style="font-size:11px;font-weight:800;">Unsatisfactory</text>
                                <rect x="300" y="306" width="150" height="22" rx="11" fill="#FF8C00" filter="url(#bc_shadow)"/>
                                <text x="375" y="321" text-anchor="middle" fill="#000000" style="font-size:11px;font-weight:800;">Below Average</text>
                                <rect x="550" y="306" width="150" height="22" rx="11" fill="#FFD700" filter="url(#bc_shadow)"/>
                                <text x="625" y="321" text-anchor="middle" fill="#000000" style="font-size:11px;font-weight:800;">Meets Expectations</text>
                                <rect x="800" y="306" width="150" height="22" rx="11" fill="#00B050" filter="url(#bc_shadow)"/>
                                <text x="875" y="321" text-anchor="middle" fill="#FFFFFF" style="font-size:11px;font-weight:800;">Outstanding</text>
                                {{-- Score ranges --}}
                                <text x="125" y="347" text-anchor="middle" fill="#94a3b8" style="font-size:11px;font-weight:600;">1 – 49</text>
                                <text x="375" y="347" text-anchor="middle" fill="#94a3b8" style="font-size:11px;font-weight:600;">50 – 69</text>
                                <text x="625" y="347" text-anchor="middle" fill="#94a3b8" style="font-size:11px;font-weight:600;">70 – 89</text>
                                <text x="875" y="347" text-anchor="middle" fill="#94a3b8" style="font-size:11px;font-weight:600;">90 – 100</text>
                                {{-- Indicator: vertical line + dot + floating score label --}}
                                <g id="bellIndicator" style="display:none;">
                                    <line id="bellLine" x1="500" y1="0" x2="500" y2="300" stroke="#1e293b" stroke-width="3" stroke-dasharray="8,5" stroke-linecap="round"/>
                                    <circle id="bellGlow" cx="500" cy="100" r="9" fill="#1e293b" filter="url(#bc_glow)" opacity="0.55">
                                        <animate attributeName="r" values="9;15;9" dur="1.8s" repeatCount="indefinite"/>
                                        <animate attributeName="opacity" values="0.55;0.1;0.55" dur="1.8s" repeatCount="indefinite"/>
                                    </circle>
                                    <circle id="bellDot" cx="500" cy="100" r="9" fill="#1e293b" stroke="white" stroke-width="3"/>
                                    <rect id="bellBg" x="440" y="-58" width="120" height="48" rx="10" ry="10" fill="white" stroke="#1e293b" stroke-width="1.5" filter="url(#bc_shadow)"/>
                                    <text id="bellScoreNum" x="500" y="-25" text-anchor="middle" fill="#1e293b" style="font-size:22px;font-weight:900;"></text>
                                    <text id="bellGradeName" x="500" y="-11" text-anchor="middle" fill="#6B9080" style="font-size:10px;font-weight:700;"></text>
                                </g>
                            </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                <div id="sec6b">
                    <div class="flex items-center justify-between mb-1">
                        <div class="part-label">B &nbsp;·&nbsp; Performance Analysis</div>
                        <span style="font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:.08em;display:flex;align-items:center;gap:4px;">🔒 APPRAISER ONLY</span>
                    </div>
                    <p class="text-[11px] text-slate-400 italic mb-5">To be completed by Appraiser. Read-only for appraisee.</p>
                    <div class="grid grid-cols-2 gap-5">
                        @foreach([['label'=>'Strengths','name'=>'s6_strengths'],['label'=>'Work Ethics / Attitude','name'=>'s6_ethics'],['label'=>'Areas Need Improvement','name'=>'s6_improvement'],['label'=>'Training Required','name'=>'s6_training']] as $pf)
                        <div>
                            <div class="flex items-center gap-2 mb-2"><span class="w-1.5 h-1.5 rounded-full bg-[#6B9080] flex-shrink-0"></span><p class="f-label">{{ $pf['label'] }}</p></div>
                            <input type="text" name="{{ $pf['name'] }}" placeholder="—" class="f-input perf-analysis-input" readonly style="pointer-events:none;opacity:0.55;background:#f8fafc;cursor:not-allowed;">
                            <button type="button" class="perf-rephrase-btn no-print" data-field-label="{{ $pf['label'] }}" onclick="rephrasePerfField(this)" style="display:inline-flex;align-items:center;gap:3px;margin-top:6px;font-size:8px;font-weight:800;color:#4a7c6b;background:#f0f9f6;border:1px solid #d1e7e0;border-radius:6px;padding:3px 7px;cursor:pointer;">✨ Rephrase</button>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                <div id="sec6b_sig">
                    <p class="text-[11px] text-slate-500 italic leading-relaxed mb-6">I hereby confirm that the foregoing appraisal is a fair and objective evaluation of the appraisee's performance during the period under review.</p>
                    <div class="flex justify-end">
                        <div style="width:280px;">
                            {{-- Appraiser signature — locked for appraisee --}}
                            <div class="sig-pad-wrap" data-sig-id="sig_appraiser" style="pointer-events:none;opacity:0.55;">
                                <div style="border:2px dashed rgba(107,144,128,.3);border-radius:10px;background:#f8fafc;position:relative;overflow:hidden;">
                                    <canvas class="sig-canvas" width="560" height="110" style="width:100%;height:110px;display:block;"></canvas>
                                    <div class="sig-hint" style="position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:11px;font-weight:600;">Appraiser signature</div>
                                </div>
                                <input type="hidden" name="sig_appraiser" class="sig-hidden">
                                <input type="hidden" name="sig_appraiser_date" class="sig-date-hidden">
                            </div>
                            <p class="text-xs font-bold text-slate-700 text-center mt-2">{{ $reportsToName !== '-' ? $reportsToName : '_______________' }}</p>
                            <p class="f-label text-center mt-1">Signature of Appraiser – Manager / VP</p>
                            @php
                                // Signed before this date-stamp existed at all — approximate
                                // with when the record was last touched rather than show nothing.
                                $sigAppraiserDate = $savedData['sig_appraiser_date']
                                    ?? (!empty($savedData['sig_appraiser']) && $submittedAt
                                        ? \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d F Y')
                                        : null);
                            @endphp
                            <p class="text-[9px] text-slate-400 text-center mt-2">Date: <span id="sigDate_sig_appraiser">{{ $sigAppraiserDate ?? '_______________' }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-dashed border-[#6B9080]/20"></div>

                @php
                    // Appraisee can only sign after the appraiser has reviewed and signed (status = appraised).
                    $ackUnlocked = !($isAppraiserView ?? false) && ($status ?? 'draft') === 'appraised';
                @endphp
                <div id="sec6_ack">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-[11px] text-slate-500 italic leading-relaxed flex-1">I hereby confirm that I have read, understood and accept/disagree with the foregoing appraisal. <span class="text-[#6B9080] font-semibold not-italic">(If you disagree please specify below)</span></p>
                        @if(!($isAppraiserView ?? false) && !$ackUnlocked)
                        <span style="font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:.08em;display:flex;align-items:center;gap:4px;white-space:nowrap;margin-left:12px;">🔒 AFTER APPRAISER SIGNS</span>
                        @endif
                    </div>
                    <textarea name="s6_response" rows="4" placeholder="Write your response here… (required)" class="f-area mb-6"@if(!$ackUnlocked) readonly style="pointer-events:none;opacity:0.55;background:#f8fafc;cursor:not-allowed;"@endif></textarea>
                    <div class="flex justify-end">
                        <div style="width:280px;">
                            {{-- Appraisee signature — unlocked only once the appraiser has signed --}}
                            <div class="sig-pad-wrap" data-sig-id="sig_appraisee"@if(!$ackUnlocked) style="pointer-events:none;opacity:0.55;"@endif>
                                <div style="border:2px dashed rgba(107,144,128,.5);border-radius:10px;background:#f9fafb;position:relative;overflow:hidden;cursor:crosshair;">
                                    <canvas class="sig-canvas" width="560" height="110" style="width:100%;height:110px;display:block;touch-action:none;"></canvas>
                                    <div class="sig-hint" style="position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:11px;font-weight:600;">{{ $ackUnlocked ? '✍ Draw signature here' : 'Available after appraiser signs' }}</div>
                                </div>
                                <div style="display:flex;gap:6px;margin-top:6px;justify-content:center;">
                                    <button type="button" onclick="sigClear(this)" style="font-size:10px;padding:3px 12px;border-radius:6px;border:1px solid #e2e8f0;background:white;color:#64748b;cursor:pointer;font-weight:600;">Clear</button>
                                    <label style="font-size:10px;padding:3px 12px;border-radius:6px;border:1px solid #e2e8f0;background:white;color:#64748b;cursor:pointer;font-weight:600;display:inline-block;">
                                        📎 Upload<input type="file" accept="image/*" class="sr-only sig-file-input" onchange="sigUpload(this)">
                                    </label>
                                </div>
                                <input type="hidden" name="sig_appraisee" class="sig-hidden">
                                <input type="hidden" name="sig_appraisee_date" class="sig-date-hidden">
                            </div>
                            <p class="text-xs font-bold text-slate-700 text-center mt-2">{{ $currentUserName }}</p>
                            <p class="f-label text-center mt-1">Signature of Appraisee</p>
                            @php
                                $sigAppraiseeDate = $savedData['sig_appraisee_date']
                                    ?? (!empty($savedData['sig_appraisee']) && $submittedAt
                                        ? \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d F Y')
                                        : null);
                            @endphp
                            <p class="text-[9px] text-slate-400 text-center mt-2">Date: <span id="sigDate_sig_appraisee">{{ $sigAppraiseeDate ?? '_______________' }}</span></p>
                        </div>
                    </div>
                    @if($ackUnlocked)
                    <div class="flex justify-end mt-4 no-print">
                        <button id="ackBtn" type="button" onclick="confirmAcknowledge()" style="display:flex;align-items:center;gap:6px;padding:10px 22px;border-radius:12px;font-size:12px;font-weight:700;border:none;background:linear-gradient(135deg,#2d5548,#4a7c6b);color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(45,85,72,.30);">
                            ✍ Sign &amp; Acknowledge
                        </button>
                    </div>
                    @endif
                </div>

            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════
             SECTION 7 — RECOMMENDATIONS & DECISIONS
             Appraiser-only — never rendered for the appraisee's own view.
        ═══════════════════════════════════════════════════════ --}}
        @if($isAppraiserView ?? false)
        <div id="sec7" class="border border-[#6B9080]/25 rounded-xl overflow-hidden mb-2 print-sec">
            <div class="sec-bar"><div class="sec-num">7</div><span class="sec-title">Recommendations &amp; Decisions</span><span style="margin-left:auto;font-size:10px;font-weight:700;color:rgba(255,255,255,.55);letter-spacing:.08em;">🔒 APPRAISER ONLY</span></div>
            <div class="px-6 py-6 space-y-7">
                <div class="no-print" style="display:flex;align-items:flex-start;gap:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;">
                    <span style="font-size:14px;line-height:1;">❗</span>
                    <p style="font-size:11px;color:#92400e;line-height:1.5;">Ticking <strong>Confirmation</strong>, <strong>Salary Review</strong> and/or <strong>Promotion</strong> is what sends this to the next level for review — signing without ticking any box completes your part here and nothing further is required from VP/SLT.</p>
                </div>
                @php $sec7=[['key'=>'manager','label'=>'A','title'=>'Promotability and Other Remarks and Recommendations by the Appraiser (Manager)'],['key'=>'vp','label'=>'B','title'=>'Remarks and/or Recommendations by VP'],['key'=>'slt','label'=>'C','title'=>'Remarks by SLT']]; @endphp
                @php $sec7Rendered=0; @endphp
                @foreach($sec7 as $blk)
                @continue(!in_array($blk['key'], $section7Levels ?? ['manager','vp','slt']))
                @if($sec7Rendered>0)<div class="border-t border-dashed border-[#6B9080]/20 pt-7"></div>@endif
                @php $sec7Rendered++; @endphp
                @php $sec7SltGateLocked = $blk['key'] === 'slt' && ($section7SltLocked ?? false); @endphp
                <div id="sec7_{{ $blk['key'] }}" @if($sec7SltGateLocked) data-gate-locked="1" @endif>
                    <div class="part-label">{{ $blk['label'] }} &nbsp;·&nbsp; {{ $blk['title'] }}</div>
                    @if($sec7SltGateLocked)
                    <div class="no-print" style="display:flex;align-items:center;gap:8px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:10px;padding:8px 14px;margin-bottom:12px;">
                        <span style="font-size:13px;line-height:1;">⏳</span>
                        <p style="font-size:11px;color:#64748b;">Waiting for VP to sign Part B — you'll be able to add your remarks and sign once that's done.</p>
                    </div>
                    @endif
                    <textarea name="s7_{{ $blk['key'] }}_remarks" rows="4" placeholder="—" class="f-area mb-5" readonly style="pointer-events:none;opacity:0.55;background:#f8fafc;cursor:not-allowed;resize:none;"></textarea>
                    <div class="flex items-end justify-between gap-6 flex-wrap">
                        <div class="flex items-center gap-6">
                            @foreach(['confirmation','salary_review','promotion'] as $oi => $okey)
                            @php $olabel = ['Confirmation','Salary Review','Promotion'][$oi]; @endphp
                            <label class="flex items-center gap-2 select-none" style="pointer-events:none;opacity:0.55;cursor:not-allowed;">
                                <span class="w-4 h-4 rounded border-2 border-[#6B9080]/40 flex items-center justify-center"><input type="checkbox" name="s7_{{ $blk['key'] }}_{{ $okey }}" id="s7_{{ $blk['key'] }}_{{ $okey }}" class="sr-only peer"><span class="w-2.5 h-2.5 rounded-sm bg-[#6B9080] hidden peer-checked:block"></span></span>
                                <span class="text-[11px] font-semibold text-slate-700">{{ $olabel }}</span>
                            </label>
                            @endforeach
                        </div>
                        <div style="min-width:224px;">
                            <div class="sig-pad-wrap" data-sig-id="s7_{{ $blk['key'] }}_sig" style="pointer-events:none;opacity:0.55;">
                                <div style="border:2px dashed rgba(107,144,128,.3);border-radius:10px;background:#f8fafc;position:relative;overflow:hidden;">
                                    <canvas class="sig-canvas" width="448" height="90" style="width:100%;height:90px;display:block;"></canvas>
                                    <div class="sig-hint" style="position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:11px;font-weight:600;">Signature</div>
                                </div>
                                <input type="hidden" name="s7_{{ $blk['key'] }}_sig" class="sig-hidden">
                                <input type="hidden" name="s7_{{ $blk['key'] }}_date" class="sig-date-hidden">
                            </div>
                            @php
                                // Legacy rows may still hold a raw ISO date from the old
                                // native date-input version of this field — reformat those
                                // too rather than showing them inconsistently.
                                $s7DateRaw = $savedData["s7_{$blk['key']}_date"] ?? null;
                                $s7Date = null;
                                if ($s7DateRaw) {
                                    try { $s7Date = \Carbon\Carbon::parse($s7DateRaw)->format('d F Y'); } catch (\Throwable $e) { $s7Date = $s7DateRaw; }
                                } elseif (!empty($savedData["s7_{$blk['key']}_sig"]) && $submittedAt) {
                                    $s7Date = \Carbon\Carbon::parse($submittedAt)->timezone('Asia/Kuala_Lumpur')->format('d F Y');
                                }
                            @endphp
                            <div class="flex items-center gap-2 mt-2 justify-center">
                                <span class="f-label">Date</span>
                                <span id="sigDate_s7_{{ $blk['key'] }}_sig" style="font-size:11px;color:#64748b;">{{ $s7Date ?? '_______________' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>{{-- /px-10 py-8 --}}
</div>{{-- /doc-card --}}

@if(!($isAppraiserView ?? false) && $isWindowOpen && ($status ?? 'draft') === 'draft')
{{-- ── Fixed bottom action bar (hidden on print) ── --}}
<div class="no-print" style="position:fixed;bottom:0;left:230px;right:0;z-index:50;background:#ffffff;border-top:1px solid rgba(107,144,128,.20);padding:14px 110px 14px 32px;display:flex;align-items:center;justify-content:flex-end;gap:10px;box-shadow:0 -4px 24px rgba(15,23,42,.08);pointer-events:none;">
    <span style="font-size:11px;font-weight:600;color:#94a3b8;margin-right:6px;">Evaluation window open until {{ $windowEnd }}</span>
    <button id="draftBtn" onclick="saveEvaluation('draft')" style="pointer-events:auto;display:flex;align-items:center;gap:6px;padding:10px 20px;border-radius:12px;font-size:12px;font-weight:700;border:1.5px solid #e2e8f0;background:#fff;color:#475569;cursor:pointer;transition:background .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Draft
    </button>
    <button id="submitBtn" onclick="confirmSubmit()" style="pointer-events:auto;display:flex;align-items:center;gap:6px;padding:10px 22px;border-radius:12px;font-size:12px;font-weight:700;border:none;background:linear-gradient(135deg,#2d5548,#4a7c6b);color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(45,85,72,.30);transition:opacity .15s;" onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
        ↑ Submit to Appraiser
    </button>
</div>
{{-- Add bottom padding so content isn't hidden under the bar --}}
<div style="height:72px;"></div>
@endif

@if(($isAppraiserView ?? false) && !($myLevelLocked ?? false))
{{-- ── Appraiser's fixed bottom action bar (hidden on print) — sits last, mirrors the appraisee's own Save Draft / Submit bar ── --}}
<div id="apprActionBar" class="no-print" style="position:fixed;bottom:0;left:230px;right:0;z-index:50;background:#ffffff;border-top:1px solid rgba(107,144,128,.20);padding:14px 110px 14px 32px;display:flex;align-items:center;justify-content:flex-end;gap:10px;box-shadow:0 -4px 24px rgba(15,23,42,.08);pointer-events:none;">
    <span style="font-size:11px;font-weight:600;color:#94a3b8;margin-right:6px;">Appraiser view — {{ $currentUserName }}</span>
    <button id="apprDraftBtn" onclick="saveEvaluation('draft')" style="pointer-events:auto;display:flex;align-items:center;gap:6px;padding:10px 20px;border-radius:12px;font-size:12px;font-weight:700;border:1.5px solid #e2e8f0;background:#fff;color:#475569;cursor:pointer;transition:background .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Draft
    </button>
    <button id="apprSubmitBtn" onclick="confirmAppraiserSubmit()" style="pointer-events:auto;display:flex;align-items:center;gap:6px;padding:10px 22px;border-radius:12px;font-size:12px;font-weight:700;border:none;background:linear-gradient(135deg,#2d5548,#4a7c6b);color:#fff;cursor:pointer;box-shadow:0 2px 8px rgba(45,85,72,.30);transition:opacity .15s;" onmouseover="this.style.opacity='0.88'" onmouseout="this.style.opacity='1'">
        {{ ($appraiserLevel ?? '') === 'manager' ? '✓ Submit & Mark as Appraised' : '✓ Submit' }}
    </button>
</div>
{{-- Add bottom padding so content isn't hidden under the bar --}}
<div style="height:72px;"></div>
@endif

</div>
</div>{{-- /px-4 --}}
</td></tr>
</tbody>
</table>
</div>{{-- /form-body --}}
</main>

@if($isAppraiserView ?? false)
{{-- Section 2 "View" popup — the detail shown here is embedded server-side
     as JSON on each row's button (data-detail), so opening it never
     depends on a network round-trip that could silently fail. --}}
<div id="quarterDetailModal" class="no-print" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:24px;" onclick="if(event.target===this) closeQuarterDetail()">
    <div style="background:#fff;border-radius:20px;max-width:520px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(15,23,42,.35);">
        <div style="position:sticky;top:0;background:#fff;padding:16px 20px 0;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;z-index:1;">
            <div style="min-width:0;">
                <p id="qdTitle" style="font-size:14px;font-weight:900;color:#1a3d34;line-height:1.35;margin:0 0 6px;"></p>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <span id="qdQuarter" class="q-tag"></span>
                    <span id="qdWeight" style="font-size:8px;font-weight:900;color:#6B9080;background:#fff;border:1px solid rgba(107,144,128,.25);padding:2px 7px;border-radius:999px;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;"></span>
                </div>
                <p id="qdSubtitle" style="font-size:10px;color:#94a3b8;margin:2px 0 0;"></p>
            </div>
            <button type="button" onclick="closeQuarterDetail()" style="background:#f1f5f9;border:1px solid #e2e8f0;width:28px;height:28px;border-radius:50%;font-size:14px;color:#64748b;cursor:pointer;flex-shrink:0;">✕</button>
        </div>
        <div style="padding:16px 20px 22px;">
            <div style="display:flex;gap:10px;margin-bottom:14px;">
                <div style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px;text-align:center;">
                    <p style="font-size:8px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 3px;">Actual</p>
                    <p id="qdActual" style="font-size:16px;font-weight:900;color:#1e293b;margin:0;"></p>
                </div>
                <div style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px;text-align:center;">
                    <p style="font-size:8px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 3px;">Target</p>
                    <p id="qdTarget" style="font-size:16px;font-weight:900;color:#1e293b;margin:0;"></p>
                </div>
                <div id="qdProgressBox" style="flex:1;border-radius:12px;padding:10px 12px;text-align:center;">
                    <p id="qdProgressLabel" style="font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;margin:0 0 3px;">Progress</p>
                    <p id="qdProgress" style="font-size:16px;font-weight:900;margin:0;"></p>
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <p style="font-size:9px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 5px;">Remark</p>
                <p id="qdRemark" style="font-size:11px;color:#334155;line-height:1.6;margin:0;"></p>
            </div>
            <div>
                <p style="font-size:9px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 6px;">Attachment / Proof</p>
                <div id="qdFiles" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
            </div>
            <div id="qdCommentSection" style="margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0;">
                <p style="font-size:9px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 6px;">Appraiser Comment</p>
                <p id="qdCommentReadonly" style="display:none;font-size:11px;color:#334155;line-height:1.6;margin:0;"></p>
                <textarea id="qdCommentText" rows="3" maxlength="2000" placeholder="Why did you give this score?" style="display:none;width:100%;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px;font-size:12px;font-family:inherit;color:#1e293b;resize:vertical;box-sizing:border-box;"></textarea>
                <div id="qdCommentAiBox" style="display:none;margin-top:8px;background:#f0f9f6;border:1px solid #d1e7e0;border-radius:12px;padding:10px 12px;">
                    <p style="font-size:9px;font-weight:900;color:#4a7c6b;text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px;">✨ ANIRA suggested rephrase</p>
                    <p id="qdCommentAiText" style="font-size:11px;color:#1a3d34;line-height:1.6;margin:0 0 8px;white-space:pre-wrap;"></p>
                    <div style="display:flex;gap:8px;">
                        <button type="button" onclick="acceptQdCommentRephrase()" style="font-size:9px;font-weight:800;color:#fff;background:#4a7c6b;border:none;border-radius:8px;padding:6px 10px;cursor:pointer;">Use this</button>
                        <button type="button" onclick="dismissQdCommentRephrase()" style="font-size:9px;font-weight:800;color:#64748b;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px;cursor:pointer;">Dismiss</button>
                    </div>
                </div>
                <div id="qdCommentActions" style="display:none;margin-top:8px;gap:8px;">
                    <button type="button" id="qdCommentRephraseBtn" onclick="rephraseQdComment()" style="display:inline-flex;align-items:center;gap:6px;font-size:9px;font-weight:800;color:#4a7c6b;background:#f0f9f6;border:1px solid #d1e7e0;border-radius:8px;padding:6px 10px;cursor:pointer;">✨ Rephrase with AI</button>
                    <button type="button" onclick="saveQdComment()" style="font-size:9px;font-weight:800;color:#fff;background:#1a3d34;border:none;border-radius:8px;padding:6px 10px;cursor:pointer;">Save Comment</button>
                </div>
            </div>
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e2e8f0;">
                <button type="button" id="qdAniraToggle" onclick="toggleAniraScore()" style="display:flex;align-items:center;justify-content:space-between;width:100%;background:none;border:none;padding:0;cursor:pointer;">
                    <span style="font-size:10px;font-weight:900;color:#1a3d34;text-transform:uppercase;letter-spacing:.06em;">🤖 ANIRA Score</span>
                    <span id="qdAniraChevron" style="font-size:11px;color:#94a3b8;">▾</span>
                </button>
                <div id="qdAniraBody" style="display:none;margin-top:10px;"></div>
            </div>
        </div>
    </div>
</div>

{{-- Section 2 "Comment" -- why the appraiser gave that score. Deliberately
     NOT a custom modal: an earlier version used one and it silently failed
     to open for at least one real user on a real device, with no JS error
     ever surfacing (even with a try/catch + alert() wrapped around it) --
     meaning the custom modal/DOM-lookup approach itself is untrustworthy on
     whatever that environment is, not just one bug inside it. window.prompt/
     confirm/alert are plain browser primitives with no custom CSS, no
     DOM traversal, and no display-toggling to get wrong -- if THESE don't
     show up, the button's click handler itself isn't firing at all, which
     is a completely different (and much narrower) problem to chase next.
     The actual value still lives in a hidden field per row
     (kpi_comment_{kpi id}), so it still saves/restores through the exact
     same collectFormData()/restoreFormData() path as every other field. --}}

<script>
var _aniraScoreCache = {};

// Mirrors the PHP $sec2FmtVal helper above -- same rules, so the "View" modal
// never shows a bare number that reads differently than the table row it came from.
function fmtKpiVal(v, unit) {
    if (v === null || v === undefined || v === '') return '—';
    var n = parseFloat(v);
    if (isNaN(n)) return '—';
    if (unit === 'currency')   return 'RM ' + n.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (unit === 'percentage') return n.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '%';
    return Math.round(n).toLocaleString('en-MY');
}

function openQuarterDetail(btn) {
    var detail;
    try { detail = JSON.parse(btn.dataset.detail); } catch (e) { console.error('openQuarterDetail: bad data', e); return; }

    window._currentQuarterDetail = detail;
    var aniraBody = document.getElementById('qdAniraBody');
    aniraBody.style.display = 'none';
    aniraBody.dataset.loaded = '';
    aniraBody.innerHTML = '';
    document.getElementById('qdAniraChevron').textContent = '▾';

    document.getElementById('qdQuarter').textContent = detail.quarter || '';
    document.getElementById('qdWeight').textContent = (detail.weight ?? '—') + '% weight';
    document.getElementById('qdTitle').textContent = detail.title || 'Untitled KPI';
    document.getElementById('qdSubtitle').textContent = detail.subtitle || '';

    document.getElementById('qdActual').textContent = fmtKpiVal(detail.actual, detail.unit);
    document.getElementById('qdTarget').textContent = fmtKpiVal(detail.target, detail.unit);

    var pct = detail.progress || 0;
    var style = pct >= 90 ? {bg:'#ecfdf5', text:'#059669'}
        : pct >= 70 ? {bg:'#f0f9f6', text:'#6B9080'}
        : pct >= 50 ? {bg:'#fffbeb', text:'#d97706'}
        : {bg:'#fef2f2', text:'#dc2626'};
    var progressBox = document.getElementById('qdProgressBox');
    progressBox.style.background = style.bg;
    document.getElementById('qdProgressLabel').style.color = style.text;
    document.getElementById('qdProgressLabel').style.opacity = '.75';
    var progressText = document.getElementById('qdProgress');
    progressText.textContent = detail.target !== null ? pct.toFixed(1) + '%' : '—';
    progressText.style.color = style.text;

    document.getElementById('qdRemark').textContent = (detail.remark && detail.remark.trim() !== '') ? detail.remark : 'NONE';

    // Appraiser Comment -- lives in the same per-KPI hidden field the
    // Section 2 row's own Comment button reads/writes, so whichever one
    // was used last is always what's shown here.
    var qdHidden = detail.kpi_id ? document.querySelector('[name="kpi_comment_' + detail.kpi_id + '"]') : null;
    var qdCommentVal = (qdHidden && qdHidden.value) || '';
    var qdEditable = (typeof _appraiserLevel !== 'undefined') && _appraiserLevel === 'manager';
    document.getElementById('qdCommentAiBox').style.display = 'none';
    if (qdEditable) {
        document.getElementById('qdCommentReadonly').style.display = 'none';
        var qdText = document.getElementById('qdCommentText');
        qdText.style.display = 'block';
        qdText.value = qdCommentVal;
        document.getElementById('qdCommentActions').style.display = 'flex';
    } else {
        document.getElementById('qdCommentText').style.display = 'none';
        document.getElementById('qdCommentActions').style.display = 'none';
        var qdRo = document.getElementById('qdCommentReadonly');
        qdRo.style.display = 'block';
        qdRo.textContent = qdCommentVal || 'No comment added yet.';
    }

    var filesWrap = document.getElementById('qdFiles');
    filesWrap.innerHTML = '';
    if (detail.files && detail.files.length) {
        detail.files.forEach(function (f) {
            var isImg = (f.type || '').indexOf('image/') === 0;
            var a = document.createElement('a');
            a.href = f.url || '#';
            a.target = '_blank';
            a.rel = 'noopener';
            if (isImg) {
                var img = document.createElement('img');
                img.src = f.url || '';
                img.alt = f.name || 'attachment';
                img.style.cssText = 'width:44px;height:44px;border-radius:10px;object-fit:cover;border:1px solid #e2e8f0;display:block;';
                a.appendChild(img);
            } else {
                a.style.cssText = 'display:inline-flex;align-items:center;gap:4px;font-size:9px;font-weight:700;color:#1a3d34;background:rgba(107,144,128,.10);border:1px solid rgba(107,144,128,.30);border-radius:8px;padding:5px 8px;text-decoration:none;';
                a.textContent = '📎 ' + (f.name || 'File');
            }
            filesWrap.appendChild(a);
        });
    } else {
        filesWrap.innerHTML = '<p style="font-size:10px;color:#94a3b8;margin:0;">NON</p>';
    }

    document.getElementById('quarterDetailModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeQuarterDetail() {
    document.getElementById('quarterDetailModal').style.display = 'none';
    document.body.style.overflow = '';
}

function saveQdComment() {
    var detail = window._currentQuarterDetail;
    if (!detail || !detail.kpi_id) return;
    var hidden = document.querySelector('[name="kpi_comment_' + detail.kpi_id + '"]');
    var text = document.getElementById('qdCommentText').value.trim();
    if (hidden) hidden.value = text;

    // Keep the row's own Comment button label/color in sync, so nobody has
    // to reopen this popup to see which rows already have a comment.
    var rowBtn = document.querySelector('.kpi-comment-btn[data-kpi-id="' + detail.kpi_id + '"]');
    if (rowBtn && typeof applyKpiComment === 'function') applyKpiComment(hidden, rowBtn, text);

    if (typeof showToast === 'function') showToast('Comment saved.', true);
}

function rephraseQdComment() {
    var text = document.getElementById('qdCommentText').value.trim();
    if (!text) {
        if (typeof showToast === 'function') showToast('Write a comment first, then rephrase it.', false);
        return;
    }

    var btn = document.getElementById('qdCommentRephraseBtn');
    var originalLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = '✨ Rephrasing…';

    fetch('{{ route('ai.rephrase-comment') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            comment:   text,
            kpi_title: document.getElementById('qdTitle').textContent,
            quarter:   document.getElementById('qdQuarter').textContent,
        }),
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.message || 'Failed');
            document.getElementById('qdCommentAiText').textContent = data.rephrased || '';
            document.getElementById('qdCommentAiBox').style.display = 'block';
        })
        .catch(function (err) {
            console.error('rephraseQdComment failed:', err);
            if (typeof showToast === 'function') showToast("Couldn't rephrase right now. Try again.", false);
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = originalLabel;
        });
}

function acceptQdCommentRephrase() {
    document.getElementById('qdCommentText').value = document.getElementById('qdCommentAiText').textContent;
    document.getElementById('qdCommentAiBox').style.display = 'none';
}

function dismissQdCommentRephrase() {
    document.getElementById('qdCommentAiBox').style.display = 'none';
}

// ── Section 3 (attitude/competency) "Appraiser's Comment" rephrase ─────────
// One click, replaces the input directly -- no confirm step, no separate
// popup. A terse note like "ok" or a single word gets expanded into one
// clear, professional sentence in place.
function rephraseAttComment(btn) {
    var input = btn.previousElementSibling;
    var text = (input && input.value || '').trim();

    if (!text) {
        if (typeof showToast === 'function') showToast('Write a comment first, then rephrase it.', false);
        return;
    }

    var originalLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = '✨ …';

    fetch('{{ route('ai.rephrase-comment') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            comment:   text,
            kpi_title: btn.dataset.areaTitle || '',
        }),
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.message || 'Failed');
            input.value = data.rephrased || text;
            if (typeof showToast === 'function') showToast('Rephrased.', true);
        })
        .catch(function (err) {
            console.error('rephraseAttComment failed:', err);
            if (typeof showToast === 'function') showToast("Couldn't rephrase right now. Try again.", false);
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = originalLabel;
        });
}

function rephrasePerfField(btn) {
    var input = btn.previousElementSibling;
    var text = (input && input.value || '').trim();

    if (!text) {
        if (typeof showToast === 'function') showToast('Write something first, then rephrase it.', false);
        return;
    }

    var originalLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = '✨ …';

    fetch('{{ route('ai.rephrase-comment') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            comment:   text,
            kpi_title: 'Performance Analysis — ' + (btn.dataset.fieldLabel || ''),
        }),
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.message || 'Failed');
            input.value = data.rephrased || text;
            if (typeof showToast === 'function') showToast('Rephrased.', true);
        })
        .catch(function (err) {
            console.error('rephrasePerfField failed:', err);
            if (typeof showToast === 'function') showToast("Couldn't rephrase right now. Try again.", false);
        })
        .finally(function () {
            btn.disabled = false;
            btn.textContent = originalLabel;
        });
}

function toggleAniraScore() {
    var body = document.getElementById('qdAniraBody');
    var chevron = document.getElementById('qdAniraChevron');
    var opening = body.style.display === 'none';
    body.style.display = opening ? 'block' : 'none';
    chevron.textContent = opening ? '▴' : '▾';
    if (opening && body.dataset.loaded !== '1') loadAniraScore();
}

function loadAniraScore() {
    var detail = window._currentQuarterDetail;
    var body = document.getElementById('qdAniraBody');
    if (!detail) return;

    var key = [detail.title, detail.quarter, detail.actual, detail.target, detail.remark].join('|');
    if (_aniraScoreCache[key]) {
        renderAniraScore(_aniraScoreCache[key]);
        body.dataset.loaded = '1';
        return;
    }

    body.innerHTML = '';
    var loading = document.createElement('p');
    loading.style.cssText = 'font-size:10px;color:#94a3b8;margin:0;';
    loading.textContent = 'Thinking…';
    body.appendChild(loading);

    fetch('{{ route('ai.score-quarter') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            kpi_title:      detail.title,
            subtitle:       detail.subtitle,
            quarter:        detail.quarter,
            actual:         detail.actual,
            target:         detail.target,
            progress:       detail.progress,
            remark:         detail.remark,
            has_attachment: !!(detail.files && detail.files.length),
        }),
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.message || 'Failed');
            _aniraScoreCache[key] = data;
            renderAniraScore(data);
            body.dataset.loaded = '1';
        })
        .catch(function (err) {
            console.error('loadAniraScore failed:', err);
            body.innerHTML = '';
            var errEl = document.createElement('p');
            errEl.style.cssText = 'font-size:10px;color:#dc2626;margin:0;';
            errEl.textContent = "Couldn't load ANIRA score. Try again.";
            body.appendChild(errEl);
        });
}

function renderAniraScore(data) {
    var body = document.getElementById('qdAniraBody');
    body.innerHTML = '';

    var verdictStyles = {
        'Excellent':       {bg:'#ecfdf5', text:'#059669'},
        'Good':            {bg:'#f0f9f6', text:'#6B9080'},
        'Needs Attention': {bg:'#fffbeb', text:'#d97706'},
        'Critical':        {bg:'#fef2f2', text:'#dc2626'},
    };
    var vs = verdictStyles[data.verdict] || {bg:'#f1f5f9', text:'#64748b'};

    if (data.verdict) {
        var badge = document.createElement('span');
        badge.style.cssText = 'display:inline-block;font-size:9px;font-weight:900;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;background:' + vs.bg + ';color:' + vs.text + ';';
        badge.textContent = data.verdict;
        body.appendChild(badge);
    }

    if (data.points && data.points.length) {
        var ul = document.createElement('ul');
        ul.style.cssText = 'margin:8px 0 0;padding-left:16px;';
        data.points.forEach(function (pt) {
            var li = document.createElement('li');
            li.style.cssText = 'font-size:10.5px;color:#334155;line-height:1.6;margin-bottom:3px;';
            li.textContent = pt;
            ul.appendChild(li);
        });
        body.appendChild(ul);
    }
}

// ── Section 2 "Comment" ─────────────────────────────────────────────────────
// Uses plain window.prompt/confirm/alert on purpose -- see the HTML comment
// above where the old custom modal used to be for why.
function applyKpiComment(hidden, btn, text) {
    if (hidden) hidden.value = text;
    var label = btn.querySelector('.kpi-comment-label');
    if (label) label.textContent = text ? 'Edit' : 'View';
    btn.style.color = text ? '#4a7c6b' : '#94a3b8';
    btn.style.background = text ? '#f0f9f6' : '#f8fafc';
    btn.style.borderColor = text ? '#d1e7e0' : '#e2e8f0';
}

// Reflect any comments already saved (restored via restoreFormData) onto
// each row's button label/color once the page's saved data has loaded.
function refreshKpiCommentButtons() {
    document.querySelectorAll('.kpi-comment-btn').forEach(function (btn) {
        var hidden = document.querySelector('[name="kpi_comment_' + btn.dataset.kpiId + '"]');
        applyKpiComment(hidden, btn, (hidden && hidden.value) || '');
    });
}
</script>
@endif

<script>
(function(){
    // Section 2 OKR score calc
    function sc(v,mx){ if(v>=mx*.9)return'sc-great'; if(v>=mx*.7)return'sc-good'; if(v>=mx*.5)return'sc-warn'; return'sc-poor'; }
    window.calcAllSec2Scores = function() {
        document.querySelectorAll('.sec2-row').forEach(function(row) {
            var aEl = row.querySelector('.sec2-actual');
            var bEl = row.querySelector('.sec2-target');
            var scoreEl = row.querySelector('.sec2-score');
            var selfHid = row.querySelector('.kpi-self-hidden');
            if (!scoreEl) return;
            var a = parseFloat(aEl?.dataset?.val);
            var b = parseFloat(bEl?.dataset?.val);
            if (!isNaN(a) && !isNaN(b) && b > 0) {
                var s = Math.min((a / b) * 5, 5);
                scoreEl.textContent = s.toFixed(2);
                scoreEl.className = 'sec2-score font-black text-sm ' + sc(s, 5);
                if (selfHid) selfHid.value = s.toFixed(4);
            } else {
                scoreEl.textContent = '—';
                scoreEl.className = 'sec2-score font-black text-sm sc-none';
                if (selfHid) selfHid.value = '';
            }
        });
        updateS6();
    };
    calcAllSec2Scores();

    // Part D optional date
    window.openPartDPicker = function() {
        var inp = document.getElementById('partd-date');
        try { inp.showPicker(); } catch(e) { inp.click(); }
    };
    window.setPartDDate = function(val) {
        var valEl = document.getElementById('partd-val');
        var calEl = document.getElementById('partd-cal');
        var clrEl = document.getElementById('partd-clr');
        if (val) {
            var d = new Date(val + 'T00:00:00');
            valEl.textContent = d.toLocaleDateString('en-GB', {day:'2-digit', month:'long', year:'numeric'});
            valEl.style.display = 'inline';
            calEl.style.display = 'none';
            clrEl.style.display = 'inline';
        } else {
            valEl.style.display = 'none';
            calEl.style.display = 'inline-flex';
            clrEl.style.display = 'none';
        }
    };
    window.clearPartDDate = function() {
        document.getElementById('partd-date').value = '';
        window.setPartDDate('');
    };

    // Section 3 live sum (self + superior)
    function updateS3() {
        var selfTotal = 0, selfCount = 0, supTotal = 0, supCount = 0;
        for (var i = 1; i <= 12; i++) {
            var selfVal = document.querySelector('input[name="self_' + i + '"]:checked');
            var supVal  = document.querySelector('input[name="sup_'  + i + '"]:checked');
            if (selfVal) { selfTotal += parseInt(selfVal.value); selfCount++; }
            if (supVal)  { supTotal  += parseInt(supVal.value);  supCount++; }
        }
        var selfEl = document.getElementById('s3Self');
        var supEl  = document.getElementById('s3Sup');
        function scoreColor(v) { return v >= 50 ? '#059669' : v >= 40 ? '#6B9080' : v >= 30 ? '#d97706' : '#dc2626'; }
        if (selfEl) {
            if (selfCount > 0) { var s = (selfTotal / 60 * 25).toFixed(1); selfEl.textContent = s; selfEl.style.color = scoreColor(parseFloat(s)); }
            else { selfEl.textContent = '—'; selfEl.style.color = '#cbd5e1'; }
        }
        if (supEl) {
            if (supCount > 0) { var s2 = (supTotal / 60 * 25).toFixed(1); supEl.textContent = s2; supEl.style.color = scoreColor(parseFloat(s2)); }
            else { supEl.textContent = '—'; supEl.style.color = '#cbd5e1'; }
        }
    }
    // This whole script block is wrapped in an IIFE, so plain function
    // declarations stay private to it — but the second <script> block below
    // (unrelated to this one, runs in the global scope) calls updateS3() and
    // updateS6() directly on page load. Without this, that call throws
    // "updateS3 is not defined", which halts ALL of that script's remaining
    // code — including the appraiser unlock/lock logic that runs right after it.
    window.updateS3 = updateS3;
    document.querySelectorAll('input[name^="self_"], input[name^="sup_"]').forEach(function(r) {
        r.addEventListener('change', function(){ updateS3(); updateS6(); });
    });

    // ── Section 6 auto-compute ────────────────────────────────────────────────
    var _isQ4 = '{{ $quarter }}' === 'Q4';

    function attScoreFromCount(n) {
        n = parseInt(n); if (isNaN(n) || n < 0) n = 0;
        if (n === 0)  return 1.00;
        if (n <= 5)   return 0.70;
        if (n <= 10)  return 0.30;
        return 0.00;
    }
    function calcAttScore() {
        var total = 0, found = false;
        for (var i = 1; i <= 5; i++) {
            var inp = document.querySelector('input[name="att_count_' + i + '"]');
            if (!inp) continue;
            found = true;
            var cnt   = inp.value === '' ? 0 : parseInt(inp.value);
            var score = attScoreFromCount(cnt);
            var rowEl = document.getElementById('att-row-score-' + i);
            if (rowEl) {
                var clr = score >= 1 ? '#059669' : score >= 0.7 ? '#6B9080' : score >= 0.3 ? '#d97706' : '#dc2626';
                rowEl.textContent = score.toFixed(2);
                rowEl.style.color = clr;
            }
            total += score;
        }
        if (!found) return null;
        var totalEl = document.getElementById('att-score-total');
        if (totalEl) {
            var clr = total >= 4.5 ? '#059669' : total >= 3 ? '#6B9080' : total >= 2 ? '#d97706' : '#dc2626';
            totalEl.textContent = total.toFixed(2);
            totalEl.style.color = clr;
        }
        return total;
    }

    function s6Clr(v){ return v>=90?'#15803d':v>=80?'#16a34a':v>=50?'#d97706':v>=35?'#ea6f00':'#e85d04'; }

    function scoreToX(s) {
        if (s <= 49)  return (s - 1)  / 48 * 250;
        if (s <= 69)  return 250 + (s - 50) / 19 * 250;
        if (s <= 89)  return 500 + (s - 70) / 19 * 250;
        return 750 + (s - 90) / 10 * 250;
    }
    function bellY(x) {
        return 300 - 260 * Math.exp(-(x - 500) * (x - 500) / 45000);
    }
    function updateGauge(score) {
        var g = document.getElementById('bellIndicator');
        if (!g) return;
        if (score === null || isNaN(score)) { g.style.display = 'none'; return; }
        var grade, clr, textClr;
        if (score >= 90)      { grade = 'Outstanding';          clr = '#00B050'; textClr = '#00B050'; }
        else if (score >= 70) { grade = 'Meets Expectations';   clr = '#FFD700'; textClr = '#B8860B'; }
        else if (score >= 50) { grade = 'Below Average';        clr = '#FF8C00'; textClr = '#FF8C00'; }
        else                  { grade = 'Unsatisfactory';       clr = '#ED1C24'; textClr = '#ED1C24'; }
        var bx = scoreToX(score);
        var by = bellY(bx);
        var lx = Math.max(60, Math.min(940, bx));
        g.style.display = '';
        document.getElementById('bellLine').setAttribute('x1', bx);
        document.getElementById('bellLine').setAttribute('x2', bx);
        document.getElementById('bellLine').setAttribute('stroke', '#000000');
        document.getElementById('bellDot').setAttribute('cx', bx);
        document.getElementById('bellDot').setAttribute('cy', by);
        document.getElementById('bellDot').setAttribute('fill', clr);
        document.getElementById('bellGlow').setAttribute('cx', bx);
        document.getElementById('bellGlow').setAttribute('cy', by);
        document.getElementById('bellGlow').setAttribute('fill', clr);
        document.getElementById('bellBg').setAttribute('x', lx - 60);
        document.getElementById('bellBg').setAttribute('stroke', clr);
        document.getElementById('bellScoreNum').setAttribute('x', lx);
        document.getElementById('bellScoreNum').textContent = score.toFixed(1);
        document.getElementById('bellScoreNum').setAttribute('fill', textClr);
        document.getElementById('bellGradeName').setAttribute('x', lx);
        document.getElementById('bellGradeName').textContent = grade;
        document.getElementById('bellGradeName').setAttribute('fill', textClr);
    }
    function setS6(key, val) {
        var num = (val !== null && !isNaN(parseFloat(val))) ? parseFloat(val) : null;
        var d = document.getElementById('disp_' + key);
        var h = document.getElementById('hid_'  + key);
        if (d) { d.textContent = num !== null ? num.toFixed(1) : '—'; d.style.color = num !== null ? s6Clr(num) : '#cbd5e1'; }
        if (h) h.value = num !== null ? num : '';
    }
    function s2Clr(v){ return v>=4.5?'#059669':v>=3.5?'#6B9080':v>=2.5?'#d97706':'#dc2626'; }
    function updateS6() {
        // S2: weighted sum of kpi_self / kpi_app → scale to 70
        var s2Self = 0, s2App = 0, s2Wt = 0, s2AppWt = 0;
        document.querySelectorAll('[name^="kpi_self_"]').forEach(function(el){
            var v = parseFloat(el.value), wt = parseFloat(el.dataset.wt||0);
            if (!isNaN(v) && wt > 0) { s2Self += v * wt; s2Wt += wt; }
        });
        document.querySelectorAll('[name^="kpi_app_"]').forEach(function(el){
            var v = parseFloat(el.value), wt = parseFloat(el.dataset.wt||0);
            if (!isNaN(v) && wt > 0) { s2App += v * wt; s2AppWt += wt; }
        });
        setS6('s6_s2_self', s2Wt > 0 ? (s2Self / s2Wt / 5 * 70) : null);
        setS6('s6_s2_app',  s2AppWt > 0 ? (s2App / s2AppWt / 5 * 70) : null);

        // Update Section 2 tfoot totals
        var selfAvg = s2Wt    > 0 ? s2Self   / s2Wt    : null;
        var appAvg  = s2AppWt > 0 ? s2App    / s2AppWt : null;
        var totEl   = document.getElementById('sec2Total');
        var appEl   = document.getElementById('sec2AppPct');
        var pctEl   = document.getElementById('sec2Pct');
        if (totEl) { if (selfAvg!==null){totEl.textContent=selfAvg.toFixed(2);totEl.style.color=s2Clr(selfAvg);} else {totEl.textContent='—';totEl.style.color='#cbd5e1';} }
        if (appEl)  { if (appAvg!==null){appEl.textContent=appAvg.toFixed(2);appEl.style.color=s2Clr(appAvg);} else {appEl.textContent='—';appEl.style.color='#cbd5e1';} }
        if (pctEl)  { var pct=selfAvg!==null?(selfAvg/5*70):null; if(pct!==null){pctEl.textContent=pct.toFixed(1)+'%';pctEl.style.color=s2Clr(pct/14);} else {pctEl.textContent='—';pctEl.style.color='#cbd5e1';} }

        // S3: from live s3Self / s3Sup spans
        var s3ST = document.getElementById('s3Self')?.textContent;
        var s3AT = document.getElementById('s3Sup')?.textContent;
        setS6('s6_s3_self', s3ST && s3ST !== '—' ? parseFloat(s3ST) : null);
        setS6('s6_s3_app',  s3AT && s3AT !== '—' ? parseFloat(s3AT) : null);

        // S4: attendance score — sum of 5 category scores (0/0.3/0.7/1 each), max 5 pts
        var s4 = calcAttScore();
        setS6('s6_s4_self', s4);
        setS6('s6_s4_app',  s4);

        // S5 (Q4): culture values radio sums → scale to 5
        if (_isQ4) {
            var cv5S = 0, cv5A = 0, cvN = 0;
            for (var ci = 0; ci <= 5; ci++) {
                var csvS = document.querySelector('input[name="cv_self_'+ci+'"]:checked');
                var csvA = document.querySelector('input[name="cv_app_'+ci+'"]:checked');
                if (csvS) { cv5S += parseInt(csvS.value); cvN++; }
                if (csvA)   cv5A += parseInt(csvA.value);
            }
            setS6('s6_s5_self', cvN > 0 ? (cv5S / 30 * 5) : null);
            setS6('s6_s5_app',  cvN > 0 ? (cv5A / 30 * 5) : null);
        }

        // Total
        var selfKeys = ['s6_s2_self','s6_s3_self','s6_s4_self'];
        var appKeys  = ['s6_s2_app', 's6_s3_app', 's6_s4_app'];
        if (_isQ4) { selfKeys.push('s6_s5_self'); appKeys.push('s6_s5_app'); }
        var sSum=0,sCnt=0,aSum=0,aCnt=0;
        selfKeys.forEach(function(k){ var v=parseFloat(document.getElementById('hid_'+k)?.value); if(!isNaN(v)){sSum+=v;sCnt++;} });
        appKeys.forEach(function(k){  var v=parseFloat(document.getElementById('hid_'+k)?.value); if(!isNaN(v)){aSum+=v;aCnt++;} });
        var sEl=document.getElementById('s6SelfTotal'), aEl=document.getElementById('s6AppTotal');
        if(sEl){sEl.textContent=sCnt>0?sSum.toFixed(1):'—';sEl.style.color=sCnt>0?s6Clr(sSum):'#cbd5e1';}
        if(aEl){aEl.textContent=aCnt>0?aSum.toFixed(1):'—';aEl.style.color=aCnt>0?s6Clr(aSum):'#cbd5e1';}
        updateGauge(sCnt > 0 ? sSum : null);
    }
    window.updateS6 = updateS6;

    // Wire: Section 2 self/app inputs → updateS6
    document.querySelectorAll('[name^="kpi_self_"],[name^="kpi_app_"]').forEach(function(el){ el.addEventListener('input', updateS6); });
    // Wire: culture values radios (Q4) → updateS6
    document.querySelectorAll('[name^="cv_self_"],[name^="cv_app_"]').forEach(function(r){ r.addEventListener('change', updateS6); });
    // Wire: attendance count inputs → updateS6
    document.querySelectorAll('.att-count-input').forEach(function(inp){ inp.addEventListener('input', updateS6); });
})();
</script>

{{-- ─── Save / Restore / Lock ─────────────────────────────────────────────── --}}
<div id="toast" style="display:none;position:fixed;bottom:28px;left:50%;transform:translateX(-50%);z-index:9999;padding:10px 22px;border-radius:12px;font-size:12px;font-weight:700;box-shadow:0 4px 20px rgba(0,0,0,.18);transition:opacity .3s;"></div>

<script>
const _savedData       = @json($savedData ?? null);
const _quarter         = '{{ $quarter }}';
const _isWindowOpen    = @json($isWindowOpen ?? false);
const _status          = '{{ $status ?? "draft" }}';
const _isSubmitted     = _status === 'submitted' || _status === 'appraised' || _status === 'completed';
const _isAppraiserView = @json($isAppraiserView ?? false);
const _appraiserLevel  = '{{ $appraiserLevel ?? "" }}';
const _myLevelLocked   = @json($myLevelLocked ?? false);
const _softLocked      = @json($softLocked ?? false);
const _canSignAsAppraisee = !_isAppraiserView && _status === 'appraised';
const _saveUrl         = _isAppraiserView
    ? '{{ $appraiserSaveUrl ?? "" }}'
    : '{{ route('performance.report.save', $quarter) }}';
const _csrfToken       = '{{ csrf_token() }}';

// ── collect all named inputs into a plain object ─────────────────────────────
function collectFormData() {
    const data = {};
    document.querySelectorAll('[name]').forEach(function(el) {
        const n = el.name;
        if (!n) return;
        if (el.type === 'radio') {
            // only capture the checked radio; initialise to null so unchecked groups are still saved
            if (el.checked) data[n] = el.value;
            else if (!(n in data)) data[n] = null;
        } else if (el.type === 'checkbox') {
            data[n] = el.checked;
        } else {
            data[n] = el.value;
        }
    });
    return data;
}

// ── restore all named inputs from saved object ────────────────────────────────
function restoreFormData(saved) {
    if (!saved || typeof saved !== 'object') return;
    Object.keys(saved).forEach(function(n) {
        const els = document.querySelectorAll('[name="' + n + '"]');
        els.forEach(function(el) {
            if (el.type === 'radio') {
                el.checked = (saved[n] !== null && el.value == saved[n]);
                if (el.checked) el.dispatchEvent(new Event('change'));
            } else if (el.type === 'checkbox') {
                el.checked = !!saved[n];
                // trigger visual update for custom checkbox spans
                el.dispatchEvent(new Event('change'));
            } else {
                el.value = saved[n] ?? '';
                // restore Part D date display
                if (n === 'partd_date' && saved[n]) {
                    window.setPartDDate(saved[n]);
                }
            }
        });
    });
    // re-trigger S6 sum
    const firstS6 = document.querySelector('.s6-input');
    if (firstS6) firstS6.dispatchEvent(new Event('input'));
    // re-trigger S2 scores
    if (typeof calcAllSec2Scores === 'function') calcAllSec2Scores();
}

// ── lock form body (JS fallback — PHP already renders inert for closed quarters) ──
function lockForm() {
    const formBody = document.getElementById('form-body') || document.querySelector('main');
    if (!formBody) return;
    formBody.setAttribute('inert', '');
    formBody.style.pointerEvents = 'none';
    formBody.style.userSelect    = 'none';
    // disabled covers radio/checkbox which readonly ignores
    formBody.querySelectorAll('input:not([type="hidden"]), textarea, select, button').forEach(function(el) {
        el.disabled = true;
        el.setAttribute('tabindex', '-1');
    });
}

// ── toast helper ──────────────────────────────────────────────────────────────
function showToast(msg, ok) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background   = ok ? '#059669' : '#dc2626';
    t.style.color        = '#fff';
    t.style.display      = 'block';
    t.style.opacity      = '1';
    setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.style.display='none'; },300); }, 3000);
}

// ── save ──────────────────────────────────────────────────────────────────────
window.saveEvaluation = function(action) {
    action = action || 'draft';
    // Belt-and-braces: the Save Draft/Submit buttons are hidden once this level
    // has submitted, but the server also rejects this — this just avoids a
    // pointless round trip.
    if (_isAppraiserView && _myLevelLocked) { showToast('Your section is already submitted and locked.', false); return; }
    // Mirrors the server-side check in PerformanceController::missingAppraiserFields()
    // — catches it before the round trip, server still enforces it either way.
    if (_isAppraiserView && action === 'submit') {
        var apprData = collectFormData();
        var apprMissing = [];
        if (_appraiserLevel === 'manager') {
            if (!apprData['s6_s2_app'])          apprMissing.push('Appraiser score (Section 2)');
            if (!apprData['s6_s3_app'])          apprMissing.push('Superior rating (Section 3)');
            if (!apprData['s7_manager_remarks']) apprMissing.push('Recommendations & remarks (Section 7)');
            if (!apprData['sig_appraiser'])      apprMissing.push('Appraiser signature');
            if (!apprData['s7_manager_sig'])     apprMissing.push('Section 7 signature');
        } else if (_appraiserLevel === 'vp') {
            if (!apprData['s7_vp_remarks']) apprMissing.push('VP remarks (Section 7)');
            if (!apprData['s7_vp_sig'])     apprMissing.push('VP signature (Section 7)');
        } else if (_appraiserLevel === 'slt') {
            if (!apprData['s7_slt_remarks']) apprMissing.push('SLT remarks (Section 7)');
            if (!apprData['s7_slt_sig'])     apprMissing.push('SLT signature (Section 7)');
        }
        if (apprMissing.length > 0) {
            showToast('Please complete: ' + apprMissing.join(', ') + '.', false);
            return;
        }
    }
    // 'acknowledge' happens after the submission window closes, so it's exempt from the window check.
    if (!_isAppraiserView && action !== 'acknowledge' && !_isWindowOpen) { showToast('Evaluation window is closed.', false); return; }
    // Validate Section 3 only for appraisee submit
    if (!_isAppraiserView && action === 'submit') {
        var missing = [];
        for (var i = 1; i <= 12; i++) {
            if (!document.querySelector('input[name="self_' + i + '"]:checked')) missing.push(i);
        }
        if (missing.length > 0) {
            showToast('Section 3: Please rate all ' + missing.length + ' remaining area(s).', false);
            missing.forEach(function(n) {
                var row = document.querySelector('input[name="self_' + n + '"]')?.closest('tr');
                if (row) { row.style.background = 'rgba(239,68,68,.08)'; setTimeout(function(){ row.style.background = ''; }, 2500); }
            });
            return;
        }
    }
    // Appraisee must draw/upload a signature before acknowledging
    if (!_isAppraiserView && action === 'acknowledge') {
        var sigHidden = document.querySelector('input[name="sig_appraisee"]');
        if (!sigHidden || !sigHidden.value) {
            showToast('Please sign before acknowledging.', false);
            return;
        }
    }
    const data = collectFormData();
    var activeBtn = document.getElementById(
        action === 'draft'       ? (_isAppraiserView ? 'apprDraftBtn'  : 'draftBtn') :
        action === 'submit'      ? (_isAppraiserView ? 'apprSubmitBtn' : 'submitBtn') :
        action === 'acknowledge' ? 'ackBtn' : 'saveBtn'
    );
    if (activeBtn) activeBtn.disabled = true;
    fetch(_saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken },
        body: JSON.stringify({ form_data: data, action: action }),
    })
    .then(function(r){ return r.json(); })
    .then(function(res){
        if (res && res.success) {
            if (_isAppraiserView) {
                if (action === 'submit' && _appraiserLevel === 'manager') {
                    showToast('Marked as Appraised ✓', true);
                    setTimeout(function(){ window.location.href = '/notifications'; }, 1200);
                } else if (action === 'submit') {
                    showToast('Submitted ✓', true);
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    showToast('Draft saved ✓', true);
                }
            } else if (action === 'submit') {
                showToast('Submitted to appraiser ✓', true);
                setTimeout(function(){ location.reload(); }, 1200);
            } else if (action === 'acknowledge') {
                showToast('Signed & acknowledged ✓', true);
                setTimeout(function(){ location.reload(); }, 1200);
            } else {
                showToast('Draft saved ✓', true);
            }
        } else {
            showToast(res.error || 'Save failed.', false);
        }
    })
    .catch(function(){ showToast('Network error.', false); })
    .finally(function(){ if (activeBtn) activeBtn.disabled = false; });
};

function confirmSubmit() {
    if (confirm('Submit this evaluation to your appraiser?\n\nYou will not be able to edit it after submitting.')) {
        saveEvaluation('submit');
    }
}

function confirmAppraiserSubmit() {
    var msg = _appraiserLevel === 'manager'
        ? 'Mark this appraisal as complete?\n\nThis locks your section, advances the appraisal to the appraisee for acknowledgment, and notifies them. You will not be able to edit your section after this.'
        : 'Submit your remarks?\n\nYou will not be able to edit this section after submitting.';
    if (confirm(msg)) {
        saveEvaluation('submit');
    }
}

function confirmAcknowledge() {
    var responseEl = document.querySelector('textarea[name="s6_response"]');
    var responseText = (responseEl && responseEl.value || '').trim();
    if (!responseText) {
        if (typeof showToast === 'function') showToast('Please write your response before signing.', false);
        if (responseEl) responseEl.focus();
        return;
    }
    if (confirm('Sign and acknowledge this appraisal?\n\nYou will not be able to edit it after acknowledging.')) {
        saveEvaluation('acknowledge');
    }
}

// Unlock the appraisee's final acknowledgment section — only reachable once status = appraised
function unlockAppraiseeAcknowledgment() {
    var el = document.getElementById('sec6_ack');
    if (!el) return;
    el.style.pointerEvents = 'auto';
    el.style.opacity = '1';
    el.querySelectorAll('textarea').forEach(function(inp) {
        inp.removeAttribute('readonly');
        inp.style.pointerEvents = 'auto';
        inp.style.opacity = '';
        inp.style.background = '';
        inp.style.cursor = '';
    });
    el.querySelectorAll('.sig-pad-wrap').forEach(function(wrap) {
        wrap.style.pointerEvents = 'auto';
        wrap.style.opacity = '1';
        if (!wrap._sigInited) { sigInit(wrap); wrap._sigInited = true; }
    });
}

// Which parts of the form the current appraiser level may edit — a VP or
// SLT only owns their own Section 7 block, never the manager's Section 6B
// / KPI scores / attendance, and never another level's Section 7 block.
function unlockAppraiserSections() {
    // Already submitted — leave the form-body-level lock (set by the caller)
    // in place instead of unlocking this level's section again.
    if (_myLevelLocked) return;

    // The fixed Save Draft/Submit bar lives inside <main>, so it inherits the
    // pointer-events:none the caller set on the whole form body — without this
    // it's visually there but physically unclickable. Not one of the section
    // IDs below since it isn't part of the editable form content.
    var actionBar = document.getElementById('apprActionBar');
    if (actionBar) { actionBar.style.pointerEvents = 'auto'; actionBar.style.opacity = '1'; }

    var sectionIds = _appraiserLevel === 'manager'
        ? ['sec6b', 'sec6b_sig', 'sec7_manager']
        : _appraiserLevel === 'vp'
            ? ['sec7_vp']
            : _appraiserLevel === 'slt'
                ? ['sec7_slt']
                : [];

    sectionIds.forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        // SLT's Part C stays locked — box, checkboxes, and signature — until
        // VP has actually signed Part B (see the server-side gate in
        // appraiserSave()); the "waiting for VP" banner is the only thing
        // this section shows until then.
        if (id === 'sec7_slt' && el.dataset.gateLocked === '1') return;
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
        el.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select').forEach(function(inp) {
            inp.removeAttribute('readonly');
            inp.style.pointerEvents = 'auto';
            inp.style.opacity = '';
            inp.style.background = '';
            inp.style.cursor = '';
            inp.style.resize = '';
        });
        el.querySelectorAll('label').forEach(function(lbl) {
            lbl.style.pointerEvents = 'auto';
            lbl.style.opacity = '';
            lbl.style.cursor = '';
        });
        el.querySelectorAll('.sig-pad-wrap').forEach(function(wrap) {
            wrap.style.pointerEvents = 'auto';
            wrap.style.opacity = '1';
            if (!wrap._sigInited) { sigInit(wrap); wrap._sigInited = true; }
        });
    });

    if (_appraiserLevel !== 'manager') return;

    // Unlock appraiser score inputs in Section 2 — manager only
    document.querySelectorAll('.kpi-app-input').forEach(function(inp) {
        inp.removeAttribute('readonly');
        inp.style.pointerEvents = 'auto';
        inp.style.opacity = '';
        inp.style.background = '';
        inp.style.cursor = '';
    });
    // Unlock Superior Rating (1-5) and Appraiser's Comment in Section 3 — manager only.
    // Radios are visually hidden (display:none) — the clickable surface is the
    // paired <label> — so explicitly reset each label rather than trusting
    // inherited pointer-events to cascade down from the wrapping div.
    document.querySelectorAll('.sup-rating-group').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '';
        el.querySelectorAll('label').forEach(function(lbl) {
            lbl.style.pointerEvents = 'auto';
            lbl.style.opacity = '';
            lbl.style.cursor = 'pointer';
        });
    });
    document.querySelectorAll('.att-comment-input').forEach(function(inp) {
        inp.removeAttribute('readonly');
        inp.style.pointerEvents = 'auto';
        inp.style.opacity = '';
    });
    document.querySelectorAll('.att-rephrase-btn').forEach(function(btn) {
        btn.style.pointerEvents = 'auto';
        btn.style.opacity = '';
    });
    // Unlock attendance count inputs — manager only
    document.querySelectorAll('.att-count-input').forEach(function(inp) {
        inp.style.pointerEvents = 'auto';
        inp.style.opacity       = '1';
        inp.style.cursor        = 'text';
        inp.style.background    = '#fff';
        inp.removeAttribute('readonly');
    });
    // Unlock Appraiser Rating + Remarks in Section 5 (Q4 only) — manager only
    document.querySelectorAll('.cv-app-rating-group').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '';
        el.querySelectorAll('label').forEach(function(lbl) {
            lbl.style.pointerEvents = 'auto';
            lbl.style.opacity = '';
            lbl.style.cursor = 'pointer';
        });
    });
    document.querySelectorAll('.cv-remark-input').forEach(function(inp) {
        inp.removeAttribute('readonly');
        inp.style.pointerEvents = 'auto';
        inp.style.opacity = '';
    });
}

// Belt-and-braces: explicitly disable every appraisee-authored field for the
// appraiser view, rather than relying only on the inherited pointer-events
// lock from the form body — a radio's paired <label> (the clickable visual)
// can end up interactive independently of the input's own lock state, so
// both the input and its label are handled here.
function lockAppraiseeOwnFields() {
    var fieldSelector = 'input[name^="self_"], input[name^="cv_self_"], input[name^="kpi_title_"], ' +
        'textarea[name="part_b"], textarea[name="part_c"], textarea[name="att_remarks"]';

    document.querySelectorAll(fieldSelector).forEach(function(el) {
        el.disabled = true;
        el.style.pointerEvents = 'none';
    });

    document.querySelectorAll('input[name^="self_"], input[name^="cv_self_"]').forEach(function(radio) {
        var lbl = document.querySelector('label[for="' + radio.id + '"]');
        if (lbl) {
            lbl.style.pointerEvents = 'none';
            lbl.style.opacity = '0.55';
            lbl.style.cursor = 'not-allowed';
        }
    });
}

// ── signature pad ─────────────────────────────────────────────────────────────
// Stamps the "Date: ___" line next to a signature pad with the day it was
// actually signed — previously always blank, since nothing ever wrote to it.
function stampSigDate(wrap) {
    var dateHidden = wrap.querySelector('.sig-date-hidden');
    if (!dateHidden) return;
    var formatted = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });
    dateHidden.value = formatted;
    var span = document.getElementById('sigDate_' + wrap.dataset.sigId);
    if (span) span.textContent = formatted;
}

function clearSigDate(wrap) {
    var dateHidden = wrap.querySelector('.sig-date-hidden');
    if (dateHidden) dateHidden.value = '';
    var span = document.getElementById('sigDate_' + wrap.dataset.sigId);
    if (span) span.textContent = '_______________';
}

// Section 7's date field used to be a native <input type="date">, whose
// restored value is a raw ISO string (YYYY-MM-DD) — reformat those to match
// the "09 September 2026" style used everywhere else; anything already in
// that form (every date stamped from now on) passes through unchanged.
function formatSigDateForDisplay(raw) {
    if (!raw) return raw;
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        var parts = raw.split('-');
        var d = new Date(Date.UTC(+parts[0], +parts[1] - 1, +parts[2]));
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric', timeZone: 'UTC' });
    }
    return raw;
}

function sigInit(wrap) {
    var canvas  = wrap.querySelector('.sig-canvas');
    var hint    = wrap.querySelector('.sig-hint');
    var hidden  = wrap.querySelector('.sig-hidden');
    if (!canvas) return;
    var ctx  = canvas.getContext('2d');
    var drawing = false;
    var lastX = 0, lastY = 0;

    function pt(e) {
        var r = canvas.getBoundingClientRect();
        var src = e.touches ? e.touches[0] : e;
        return {
            x: (src.clientX - r.left) * (canvas.width  / r.width),
            y: (src.clientY - r.top)  * (canvas.height / r.height)
        };
    }
    function startDraw(e) {
        e.preventDefault();
        drawing = true;
        var p = pt(e);
        lastX = p.x; lastY = p.y;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
    }
    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        var p = pt(e);
        ctx.lineWidth   = 2.5;
        ctx.lineCap     = 'round';
        ctx.lineJoin    = 'round';
        ctx.strokeStyle = '#1e293b';
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        lastX = p.x; lastY = p.y;
    }
    function endDraw(e) {
        if (!drawing) return;
        drawing = false;
        if (hint) hint.style.display = 'none';
        if (hidden) hidden.value = canvas.toDataURL('image/png');
        stampSigDate(wrap);
        // Appraisee signature is only drawable once the appraiser has reviewed and signed (status = appraised).
        // Signing itself doesn't submit — the appraisee still clicks "Sign & Acknowledge" explicitly.
    }

    canvas.addEventListener('mousedown',  startDraw);
    canvas.addEventListener('mousemove',  draw);
    canvas.addEventListener('mouseup',    endDraw);
    canvas.addEventListener('mouseleave', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove',  draw,      { passive: false });
    canvas.addEventListener('touchend',   endDraw);
}

function sigClear(btn) {
    var wrap   = btn.closest('.sig-pad-wrap');
    var canvas = wrap.querySelector('.sig-canvas');
    var hint   = wrap.querySelector('.sig-hint');
    var hidden = wrap.querySelector('.sig-hidden');
    if (canvas) canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    if (hint)   hint.style.display = '';
    if (hidden) hidden.value = '';
    clearSigDate(wrap);
}

function sigUpload(input) {
    var wrap   = input.closest('.sig-pad-wrap');
    var canvas = wrap.querySelector('.sig-canvas');
    var hint   = wrap.querySelector('.sig-hint');
    var hidden = wrap.querySelector('.sig-hidden');
    if (!input.files || !input.files[0] || !canvas) return;
    var reader = new FileReader();
    reader.onload = function(ev) {
        var img = new Image();
        img.onload = function() {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            // fit image inside canvas preserving aspect ratio
            var scale = Math.min(canvas.width / img.width, canvas.height / img.height);
            var w = img.width * scale, h = img.height * scale;
            ctx.drawImage(img, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
            if (hint)   hint.style.display = 'none';
            if (hidden) hidden.value = canvas.toDataURL('image/png');
            stampSigDate(wrap);
            // Appraisee signature is only drawable once the appraiser has reviewed and signed (status = appraised).
            // Signing itself doesn't submit — the appraisee still clicks "Sign & Acknowledge" explicitly.
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(input.files[0]);
    input.value = '';
}

function restoreAllSigs() {
    document.querySelectorAll('.sig-pad-wrap').forEach(function(wrap) {
        var hidden = wrap.querySelector('.sig-hidden');
        var canvas = wrap.querySelector('.sig-canvas');
        var hint   = wrap.querySelector('.sig-hint');

        var dateHidden = wrap.querySelector('.sig-date-hidden');
        if (dateHidden && dateHidden.value) {
            var span = document.getElementById('sigDate_' + wrap.dataset.sigId);
            if (span) span.textContent = formatSigDateForDisplay(dateHidden.value);
        }

        if (!hidden || !canvas || !hidden.value) return;
        var img = new Image();
        img.onload = function() {
            canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            if (hint) hint.style.display = 'none';
        };
        img.src = hidden.value;
    });
}

// Auto-init editable signature pads (those without pointer-events:none on the wrap itself)
document.querySelectorAll('.sig-pad-wrap').forEach(function(wrap) {
    if (wrap.style.pointerEvents !== 'none' && !wrap._sigInited) {
        sigInit(wrap);
        wrap._sigInited = true;
    }
});

// ── on page load ──────────────────────────────────────────────────────────────
if (_savedData) { restoreFormData(_savedData); restoreAllSigs(); updateS3(); }
if (typeof refreshKpiCommentButtons === 'function') refreshKpiCommentButtons();
updateS6();
if (_isAppraiserView) {
    // Lock form body then unlock appraiser sections — keeps header buttons accessible.
    // No opacity here: CSS opacity on this container would apply to every descendant
    // as a group, so a child's own opacity:1 (set by unlockAppraiserSections()) can't
    // un-fade it — the unlocked sections would stay visually washed out even though
    // they're fully clickable. pointer-events doesn't have that problem: a child's
    // pointer-events:auto correctly overrides this ancestor's pointer-events:none.
    var formBody = document.getElementById('form-body') || document.querySelector('main');
    if (formBody) { formBody.style.pointerEvents = 'none'; }
    // Section 2's "View"/"Comment" buttons are read-only-safe (Comment's own
    // write access is separately gated by _appraiserLevel inside
    // openQuarterDetail(), not by this lock), so every appraiser level can
    // use them regardless of _myLevelLocked — unlock unconditionally rather
    // than inside unlockAppraiserSections(), which early-returns once this
    // level's section is locked. Comment was missing from this list entirely
    // until now, which is why tapping it never did anything at all: the
    // button sat inside form-body's pointer-events:none with nothing to
    // ever re-enable it, regardless of what JS ran on click.
    document.querySelectorAll('.kpi-view-btn, .kpi-comment-btn').forEach(function(btn) { btn.style.pointerEvents = 'auto'; });
    unlockAppraiserSections();
    lockAppraiseeOwnFields();
} else if (_softLocked) {
    // status = appraised: soft-lock the whole form, then unlock just the acknowledgment panel
    var formBody = document.getElementById('form-body') || document.querySelector('main');
    if (formBody) { formBody.style.pointerEvents = 'none'; }
    unlockAppraiseeAcknowledgment();
} else if (!_isWindowOpen || _isSubmitted) {
    lockForm();
}

// ── Print: hide empty signature pads, swap textareas for full-text print divs ──
window.addEventListener('beforeprint', function() {
    document.querySelectorAll('.sig-pad-wrap').forEach(function(wrap) {
        var hidden = wrap.querySelector('.sig-hidden');
        if (!hidden || !hidden.value.trim()) {
            wrap.classList.add('no-sig');
        }
    });
    // A <textarea> is capped to its fixed on-screen height — anything past
    // that just scrolled and printed cut off. The print CSS hides the
    // textarea and shows this plain-text div instead, which always lays
    // out to its full height (and can flow onto the next page) so nothing
    // is ever lost.
    document.querySelectorAll('.f-area').forEach(function(ta) {
        var printDiv = ta.nextElementSibling;
        if (!printDiv || !printDiv.classList.contains('f-area-print')) {
            printDiv = document.createElement('div');
            printDiv.className = 'f-area-print';
            ta.insertAdjacentElement('afterend', printDiv);
        }
        printDiv.textContent = ta.value || '';
    });
});
window.addEventListener('afterprint', function() {
    document.querySelectorAll('.sig-pad-wrap').forEach(function(wrap) {
        wrap.classList.remove('no-sig');
    });
});
</script>


</body>
</html>
