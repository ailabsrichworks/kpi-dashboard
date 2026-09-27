<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Description</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/19.0.0/classic/ckeditor.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .doc-card { box-shadow: 0 8px 30px rgba(15,23,42,.08); }
        .doc-bar {
            background: #0a0a0a;
            color: #fff;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            min-height: 40px;
            display: flex;
            align-items: center;
        }
        .jd-editor .ck-editor__editable_inline { min-height: 120px; font-size: 12.5px; }
        .jd-editor .ck-content table { width: 100%; border-collapse: collapse; }
        .jd-editor .ck-content table td, .jd-editor .ck-content table th { border: 1px solid #94a3b8; padding: 6px 8px; }

        /* Print: just the document — no sidebar, no top bar, no editing
           chrome (toolbars, Save/Submit, back link). */
        @media print {
            #sidebar, #sidebarCloseBtn, #topBar, .no-print,
            .ck-toolbar, #jdForm .p-3.flex.justify-end { display: none !important; }
            #mainContent { margin-left: 0 !important; padding-top: 0 !important; }
            html, body { background: #fff !important; }
            .jd-editor .ck-editor__editable_inline { border: none !important; padding: 0 !important; }

            /* Keep each section's title bar glued to its own content (identity
               table, each CKEditor block, the acknowledgement + signatures)
               instead of letting the page break land between them or through
               the middle of one. */
            .doc-bar { break-after: avoid !important; page-break-after: avoid !important; }
            .doc-bar + div, .doc-bar + table {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300">
<div class="p-4 max-w-4xl mx-auto space-y-3">

    <a href="/dashboard" class="no-print text-[10px] text-slate-500 hover:text-slate-800">← Dashboard</a>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-2.5 text-[12px] font-semibold text-emerald-700">
        ✓ {{ session('success') }}
    </div>
    @endif

    @php
        $position     = $jobDescription['job_title'] ?? $user['position'] ?? '-';
        $departmentNm = $department['name'] ?? $user['department_code'] ?? '-';
        $reportingTo  = $jobDescription['reporting_line'] ?? null;
        if (!$reportingTo) {
            $reportingTo = $manager['full_name'] ?? $manager['short_name'] ?? '-';
        }
        $effectiveDate = !empty($jobDescription['effective_date'])
            ? \Carbon\Carbon::parse($jobDescription['effective_date'])->format('d M Y')
            : '-';

        $jdStatus = $jobDescription['status'] ?? 'draft';
    @endphp

    <div class="flex items-center justify-end">
        @if($jdStatus === 'submitted')
        <span class="text-[9px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">
            Submitted{{ !empty($jobDescription['submitted_at']) ? ' — ' . \Carbon\Carbon::parse($jobDescription['submitted_at'])->format('d M Y') : '' }}
        </span>
        @else
        <span class="text-[9px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-slate-200 text-slate-600">
            Draft
        </span>
        @endif
    </div>

    <form method="POST" action="{{ route('job-description.update') }}" id="jdForm">
    @csrf

    {{-- DOCUMENT --}}
    <div class="bg-white doc-card border-2 border-black overflow-hidden">

        <div class="doc-bar text-sm px-4 py-2.5">
            Job Description
        </div>

        <table class="w-full text-left border-collapse table-fixed">
            <tbody>
                <tr>
                    <td rowspan="2" class="w-40 border-r-2 border-b border-black p-2 align-middle text-center">
                        <span class="text-[13px] font-black text-slate-800">
                            {{ session('company_display_name') ?: session('company_code') ?: 'Company' }}
                        </span>
                    </td>
                    <td class="border-r border-b border-black p-3 w-1/3">
                        <p class="text-[10px] font-black text-black">Position</p>
                        <p class="text-[12px] text-slate-700 mt-1">{{ $position }}</p>
                    </td>
                    <td class="border-b border-black p-3 w-1/3">
                        <p class="text-[10px] font-black text-black">Department</p>
                        <p class="text-[12px] text-slate-700 mt-1">{{ $departmentNm }}</p>
                    </td>
                </tr>
                <tr>
                    <td class="border-r border-black p-3">
                        <p class="text-[10px] font-black text-black">Reporting To</p>
                        <p class="text-[12px] text-slate-700 mt-1">{{ $reportingTo }}</p>
                    </td>
                    <td class="p-3">
                        <p class="text-[10px] font-black text-black">Effective Date</p>
                        <p class="text-[12px] text-slate-700 mt-1">{{ $effectiveDate }}</p>
                    </td>
                </tr>
            </tbody>
        </table>

        @php
            $jdSections = [
                ['key' => 'summary',          'title' => '1) Job Purpose',                             'placeholder' => 'Describe the purpose of this role...'],
                ['key' => 'responsibilities', 'title' => '2) Key Responsibilities',                     'placeholder' => 'List the key responsibilities...'],
                ['key' => 'requirements',     'title' => '3) Qualifications &amp; Experience',           'placeholder' => 'List qualifications and experience...'],
                ['key' => 'competencies',     'title' => '4) Competencies &amp; Behavioural Expectations', 'placeholder' => 'List competencies and behavioural expectations...'],
            ];
        @endphp

        @foreach($jdSections as $i => $section)
        <div class="doc-bar text-[11px] px-4 py-2.5 border-t-2 border-black">
            {!! $section['title'] !!}
        </div>
        <div class="p-4 {{ $i < count($jdSections) - 1 ? 'border-b border-black' : '' }}">
            <div class="jd-editor bg-white border border-slate-300 rounded-lg">
                <div id="editor-{{ $section['key'] }}">{!! $jobDescription[$section['key']] ?? '' !!}</div>
            </div>
            <input type="hidden" name="{{ $section['key'] }}" id="input-{{ $section['key'] }}">
        </div>
        @endforeach

        @php
            $jobholderName   = $user['full_name'] ?? $user['short_name'] ?? $user['name'] ?? '-';
            $supervisorName  = $manager['full_name'] ?? $manager['short_name'] ?? $reportingTo;
            $sigDate = fn($key) => !empty($jobDescription[$key])
                ? \Carbon\Carbon::parse($jobDescription[$key])->format('j/n/Y')
                : '';
            $ackParties = [
                ['key' => 'hr',         'label' => 'HR &amp; Administration', 'name' => ''],
                ['key' => 'jobholder',  'label' => 'Jobholder',                'name' => $jobholderName],
                ['key' => 'supervisor', 'label' => 'Supervisor',               'name' => $supervisorName],
            ];
        @endphp

        <div class="doc-bar text-[11px] px-4 py-2.5 border-t-2 border-black">
            5) Acknowledgement
        </div>
        <div class="p-4 border-b border-black">
            <p class="text-[11px] italic text-slate-700 leading-relaxed mb-4">
                I hereby confirm that I have received, read, and understood the contents of this Job Description.
                I agree to perform the duties and responsibilities stated herein in accordance with the Company's policies, SOPs, and Management's instructions from time to time.
            </p>
            <table class="w-full text-left border-collapse border border-black">
                <thead>
                    <tr>
                        @foreach($ackParties as $p)
                        <th class="border border-black p-2 text-[10px] font-black uppercase w-1/3">{!! $p['label'] !!}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach($ackParties as $p)
                        @php $sigId = 'sig_' . $p['key']; @endphp
                        <td class="border border-black p-3 align-top">
                            <div class="sig-pad-wrap" data-sig-id="{{ $sigId }}">
                                <div style="border:1.5px dashed #cbd5e1;border-radius:8px;background:#f9fafb;position:relative;overflow:hidden;cursor:crosshair;">
                                    <canvas class="sig-canvas" width="300" height="64" style="width:100%;height:64px;display:block;touch-action:none;"></canvas>
                                    <div class="sig-hint" style="position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;color:#cbd5e1;font-size:9px;font-weight:600;text-align:center;padding:0 6px;">✍ Draw or upload sign</div>
                                </div>
                                <div style="display:flex;gap:5px;margin-top:4px;">
                                    <button type="button" onclick="sigClear(this)" style="font-size:9px;padding:2px 8px;border-radius:5px;border:1px solid #e2e8f0;background:white;color:#64748b;cursor:pointer;font-weight:600;">Clear</button>
                                    <label style="font-size:9px;padding:2px 8px;border-radius:5px;border:1px solid #e2e8f0;background:white;color:#64748b;cursor:pointer;font-weight:600;display:inline-block;">
                                        📎 Upload<input type="file" accept="image/*" class="sr-only sig-file-input" onchange="sigUpload(this)">
                                    </label>
                                </div>
                                <input type="hidden" name="{{ $sigId }}" class="sig-hidden" value="{{ $jobDescription[$sigId] ?? '' }}">
                            </div>
                            <p class="text-[11px] text-slate-700 mt-2">Name : {{ $p['name'] }}</p>
                            <p class="text-[11px] text-slate-700">Date : <span class="sig-date-display" data-for="{{ $sigId }}">{{ $sigDate($sigId . '_date') }}</span></p>
                            <input type="hidden" name="{{ $sigId }}_date" class="sig-date-hidden" data-for="{{ $sigId }}" value="{{ $jobDescription[$sigId . '_date'] ?? '' }}">
                        </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="p-3 flex justify-end gap-2 border-t-2 border-black">
            <button
                type="submit"
                name="action"
                value="draft"
                class="text-[11px] font-black px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 transition"
            >
                Save Draft
            </button>
            <button
                type="submit"
                name="action"
                value="submit"
                class="text-[11px] font-black px-4 py-2 rounded-lg bg-black text-white hover:bg-slate-800 transition"
            >
                Submit
            </button>
        </div>

    </div>
    </form>

    @if(!empty($jobDescription['updated_at']))
    <p class="text-[10px] text-slate-400 text-right pr-1">
        Last updated {{ \Carbon\Carbon::parse($jobDescription['updated_at'])->format('d M Y, h:i A') }}
    </p>
    @endif

</div>
</main>

<script>
    var jdEditors = {};

    function initJdEditor(name, placeholder) {
        ClassicEditor
            .create(document.getElementById('editor-' + name), {
                placeholder: placeholder,
                toolbar: ['bold', 'italic', 'bulletedList', 'numberedList', '|', 'insertTable'],
                table: {
                    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
                },
            })
            .then(function (editor) {
                jdEditors[name] = editor;
            })
            .catch(function (error) {
                console.error('JD editor failed to load:', name, error);
            });
    }

    initJdEditor('summary', 'Describe the purpose of this role...');
    initJdEditor('responsibilities', 'List the key responsibilities...');
    initJdEditor('requirements', 'List qualifications and experience...');
    initJdEditor('competencies', 'List competencies and behavioural expectations...');

    document.getElementById('jdForm').addEventListener('submit', function () {
        Object.keys(jdEditors).forEach(function (name) {
            document.getElementById('input-' + name).value = jdEditors[name].getData();
        });
    });

    // ── signature pad (draw / upload) with per-party sign-date stamping ────────
    function sigStampDate(sigId) {
        var dateHidden  = document.querySelector('.sig-date-hidden[data-for="' + sigId + '"]');
        var dateDisplay = document.querySelector('.sig-date-display[data-for="' + sigId + '"]');
        var now = new Date();
        var iso     = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        var display = now.getDate() + '/' + (now.getMonth() + 1) + '/' + now.getFullYear();
        if (dateHidden)  dateHidden.value = iso;
        if (dateDisplay) dateDisplay.textContent = display;
    }

    function sigInit(wrap) {
        var canvas = wrap.querySelector('.sig-canvas');
        var hint   = wrap.querySelector('.sig-hint');
        var hidden = wrap.querySelector('.sig-hidden');
        var sigId  = wrap.dataset.sigId;
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var drawing = false;

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
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        }
        function draw(e) {
            if (!drawing) return;
            e.preventDefault();
            var p = pt(e);
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#1e293b';
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        }
        function endDraw() {
            if (!drawing) return;
            drawing = false;
            if (hint) hint.style.display = 'none';
            if (hidden) hidden.value = canvas.toDataURL('image/png');
            sigStampDate(sigId);
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
        var sigId  = wrap.dataset.sigId;
        if (canvas) canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
        if (hint)   hint.style.display = '';
        if (hidden) hidden.value = '';
        var dateHidden  = document.querySelector('.sig-date-hidden[data-for="' + sigId + '"]');
        var dateDisplay = document.querySelector('.sig-date-display[data-for="' + sigId + '"]');
        if (dateHidden)  dateHidden.value = '';
        if (dateDisplay) dateDisplay.textContent = '';
    }

    function sigUpload(input) {
        var wrap   = input.closest('.sig-pad-wrap');
        var canvas = wrap.querySelector('.sig-canvas');
        var hint   = wrap.querySelector('.sig-hint');
        var hidden = wrap.querySelector('.sig-hidden');
        var sigId  = wrap.dataset.sigId;
        if (!input.files || !input.files[0] || !canvas) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
            var img = new Image();
            img.onload = function () {
                var ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                var scale = Math.min(canvas.width / img.width, canvas.height / img.height);
                var w = img.width * scale, h = img.height * scale;
                ctx.drawImage(img, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
                if (hint)   hint.style.display = 'none';
                if (hidden) hidden.value = canvas.toDataURL('image/png');
                sigStampDate(sigId);
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(input.files[0]);
        input.value = '';
    }

    document.querySelectorAll('.sig-pad-wrap').forEach(function (wrap) {
        sigInit(wrap);
        var hidden = wrap.querySelector('.sig-hidden');
        var canvas = wrap.querySelector('.sig-canvas');
        var hint   = wrap.querySelector('.sig-hint');
        if (hidden && hidden.value) {
            var img = new Image();
            img.onload = function () {
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                if (hint) hint.style.display = 'none';
            };
            img.src = hidden.value;
        }
    });
</script>

</body>
</html>
