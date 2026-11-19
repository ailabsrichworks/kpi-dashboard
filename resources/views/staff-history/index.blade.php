<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff History</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .thin-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        #editModal { display: none; }
        #editModal.open { display: flex; }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">

{{-- ═══════ HEADER (sticky) ═══════ --}}
<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="relative">
            <h1 class="text-xl font-black tracking-tight leading-tight">Staff History — {{ $companyCode }}</h1>
            <p class="text-[11px] text-white/60 mt-1">{{ $today }} · Rekod sejarah staff (berhenti, diberhentikan, kembali bekerja, dsb.)</p>
        </div>

        <form method="GET" action="{{ route('staff-history') }}" class="relative flex flex-wrap items-center gap-2">
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Company</label>
                <select name="company" onchange="this.form.submit()" class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20">
                    @foreach($companies as $c)
                        <option value="{{ $c['code'] }}" @selected($companyCode === strtoupper($c['code']))>{{ $c['name'] ?? $c['code'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Event Type</label>
                <select name="event_type" onchange="this.form.submit()" class="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20">
                    <option value="ALL" @selected($eventFilter === 'ALL')>All Events</option>
                    @foreach($eventTypes as $key => $meta)
                        <option value="{{ $key }}" @selected($eventFilter === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="px-4 pb-10 space-y-3">

    @if(session('success'))
        <div class="bg-[#DCFCE7] border border-[#15803D]/30 text-[#15803D] text-[11px] font-bold rounded-xl px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-[#FEE2E2] border border-[#B91C1C]/30 text-[#B91C1C] text-[11px] font-bold rounded-xl px-4 py-3">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-[#FEE2E2] border border-[#B91C1C]/30 text-[#B91C1C] text-[11px] font-bold rounded-xl px-4 py-3 space-y-1">
            @foreach($errors->all() as $e)
                <p>• {{ $e }}</p>
            @endforeach
        </div>
    @endif

    {{-- ═══════ ADD RECORD FORM ═══════ --}}
    <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden" x-data="{ open: true }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-5 py-4 text-left">
            <div>
                <p class="text-[13px] font-black text-slate-800">+ Add Record / Tambah Rekod Baru</p>
                <p class="text-[10px] text-slate-400 mt-0.5">Simpan rekod bila-bila staff resign, diberhentikan, kembali bekerja, atau apa-apa perubahan status lain.</p>
            </div>
            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': !open }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="open" x-transition class="px-5 pb-5 border-t border-[#E5E7EB] pt-4">
            <form method="POST" action="{{ route('staff-history.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                <input type="hidden" name="company" value="{{ $companyCode }}">

                <div class="md:col-span-1">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Staff</label>
                    <select name="employee_id" required class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                        <option value="">— Pilih Staff —</option>
                        <optgroup label="Active Staff">
                            @foreach($employees as $e)
                                @if($e['is_active'])
                                    <option value="{{ $e['id'] }}">{{ $e['short_name'] ?? $e['full_name'] }} — {{ $e['employee_id'] }} ({{ $e['role'] }})</option>
                                @endif
                            @endforeach
                        </optgroup>
                        <optgroup label="Inactive / Resigned Staff">
                            @foreach($employees as $e)
                                @if(!$e['is_active'])
                                    <option value="{{ $e['id'] }}">{{ $e['short_name'] ?? $e['full_name'] }} — {{ $e['employee_id'] }} ({{ $e['role'] }})</option>
                                @endif
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Event Type</label>
                    <select name="event_type" id="historyEventType" required onchange="suggestStatus(this.value, 'historyStatusAction')" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                        <option value="">— Pilih Jenis —</option>
                        @foreach($eventTypes as $key => $meta)
                            <option value="{{ $key }}" data-suggest="{{ $meta['suggest_status'] ?? 'no_change' }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Event Date</label>
                    <input type="date" name="event_date" required value="{{ now()->timezone('Asia/Kuala_Lumpur')->format('Y-m-d') }}" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Reason / Sebab (ringkas)</label>
                    <input type="text" name="reason" maxlength="255" placeholder="cth: Peluang kerja baru" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Staff Status Selepas Ini</label>
                    <select name="status_action" id="historyStatusAction" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                        <option value="no_change">Tiada Perubahan</option>
                        <option value="active">Aktifkan (Active)</option>
                        <option value="inactive">Tidak Aktifkan (Inactive)</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Notes / Catatan Tambahan</label>
                    <input type="text" name="notes" maxlength="2000" placeholder="Maklumat tambahan (jika ada)" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                </div>

                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="text-xs font-black px-5 py-2.5 rounded-xl bg-[#D4AF37] text-[#1a1a1a] hover:bg-[#c19c2f] transition shadow-md">
                        Simpan Rekod / Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════ LEGEND ═══════ --}}
    <div class="flex flex-wrap items-center gap-2 px-1">
        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mr-1">Legend</span>
        @foreach($eventTypes as $key => $meta)
            <span class="text-[9px] font-black px-2.5 py-1 rounded-full" style="background:{{ $meta['bg'] }};color:{{ $meta['text'] }};">{{ $meta['label'] }}</span>
        @endforeach
    </div>

    {{-- ═══════ HISTORY LIST ═══════ --}}
    <div class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden">
        <div class="p-4 pb-2">
            <p class="text-[11px] font-black text-slate-800">Rekod Sejarah Staff ({{ count($rows) }})</p>
            <p class="text-[9px] text-slate-400 mt-0.5">Tersusun mengikut tarikh event terkini dahulu.</p>
        </div>

        <div class="overflow-x-auto thin-scroll">
            <table class="w-full text-[11px]">
                <thead>
                    <tr class="text-left text-[9px] font-black text-slate-400 uppercase tracking-widest border-y border-[#E5E7EB]">
                        <th class="px-4 py-2.5">Staff</th>
                        <th class="px-4 py-2.5">Department</th>
                        <th class="px-4 py-2.5">Event</th>
                        <th class="px-4 py-2.5">Date</th>
                        <th class="px-4 py-2.5">Reason / Notes</th>
                        <th class="px-4 py-2.5">Recorded By</th>
                        <th class="px-4 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr class="border-b border-[#F1F5F9] hover:bg-[#FAFAF9]">
                            <td class="px-4 py-3">
                                <p class="font-black text-slate-800">{{ $r['employee_name'] }}</p>
                                <p class="text-[9px] text-slate-400">{{ $r['employee_code'] }} @if($r['currently_active'] === false) · <span class="text-[#B91C1C] font-bold">Inactive</span> @elseif($r['currently_active'] === true) · <span class="text-[#15803D] font-bold">Active</span> @endif</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $r['department'] }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[9px] font-black px-2.5 py-1 rounded-full whitespace-nowrap" style="background:{{ $r['event_meta']['bg'] }};color:{{ $r['event_meta']['text'] }};">{{ $r['event_meta']['label'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r['event_date'])->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-slate-600 max-w-[260px]">
                                @if($r['reason'])<p class="font-semibold">{{ $r['reason'] }}</p>@endif
                                @if($r['notes'])<p class="text-[10px] text-slate-400">{{ $r['notes'] }}</p>@endif
                                @if(!$r['reason'] && !$r['notes'])<span class="text-slate-300">—</span>@endif
                            </td>
                            <td class="px-4 py-3 text-slate-400 whitespace-nowrap">{{ $r['recorded_by_name'] }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button"
                                    onclick='openEditModal(@json([
                                        "id" => $r["id"],
                                        "employee_name" => $r["employee_name"],
                                        "event_type" => $r["event_type"],
                                        "event_date" => $r["event_date"],
                                        "reason" => $r["reason"],
                                        "notes" => $r["notes"],
                                    ]))'
                                    class="text-[10px] font-bold text-[#1D4ED8] hover:underline mr-2">Edit</button>
                                <form method="POST" action="{{ route('staff-history.destroy', $r['id']) }}" class="inline" onsubmit="return confirm('Padam rekod ini? Tindakan ini tidak boleh diundur.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[10px] font-bold text-[#B91C1C] hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400 text-[11px]">Tiada rekod sejarah untuk company ini lagi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
</main>

{{-- ═══════ EDIT MODAL ═══════ --}}
<div id="editModal" class="fixed inset-0 z-50 items-center justify-center bg-black/40 px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <p class="text-[13px] font-black text-slate-800 mb-1">Edit Rekod — <span id="editModalName"></span></p>
        <p class="text-[10px] text-slate-400 mb-4">Kemaskini maklumat rekod ini.</p>
        <form id="editModalForm" method="POST" class="space-y-3">
            @csrf
            @method('PUT')
            <input type="hidden" name="company" value="{{ $companyCode }}">

            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Event Type</label>
                <select name="event_type" id="editEventType" required onchange="suggestStatus(this.value, 'editStatusAction')" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                    @foreach($eventTypes as $key => $meta)
                        <option value="{{ $key }}" data-suggest="{{ $meta['suggest_status'] ?? 'no_change' }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Event Date</label>
                <input type="date" name="event_date" id="editEventDate" required class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Reason / Sebab</label>
                <input type="text" name="reason" id="editReason" maxlength="255" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Notes / Catatan</label>
                <input type="text" name="notes" id="editNotes" maxlength="2000" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
            </div>

            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Staff Status Selepas Ini</label>
                <select name="status_action" id="editStatusAction" class="mt-1 w-full text-xs rounded-lg px-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40">
                    <option value="no_change">Tiada Perubahan</option>
                    <option value="active">Aktifkan (Active)</option>
                    <option value="inactive">Tidak Aktifkan (Inactive)</option>
                </select>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeEditModal()" class="text-xs font-bold px-4 py-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50">Batal</button>
                <button type="submit" class="text-xs font-black px-5 py-2 rounded-xl bg-[#D4AF37] text-[#1a1a1a] hover:bg-[#c19c2f] transition">Simpan / Save</button>
            </div>
        </form>
    </div>
</div>

<script defer src="/vendor/alpinejs/alpinejs-3.14.9.min.js"></script>
<script>
    function suggestStatus(eventType, targetSelectId) {
        var select = document.getElementById('historyEventType') && document.getElementById('historyEventType').value === eventType
            ? document.getElementById('historyEventType')
            : document.getElementById('editEventType');
        var opt = select ? select.querySelector('option[value="' + eventType + '"]') : null;
        var suggested = opt ? opt.dataset.suggest : 'no_change';
        var target = document.getElementById(targetSelectId);
        if (target && suggested) {
            target.value = suggested;
        }
    }

    function openEditModal(row) {
        document.getElementById('editModalForm').action = '{{ url('/staff-history') }}/' + row.id;
        document.getElementById('editModalName').textContent = row.employee_name;
        document.getElementById('editEventType').value = row.event_type;
        document.getElementById('editEventDate').value = row.event_date;
        document.getElementById('editReason').value = row.reason || '';
        document.getElementById('editNotes').value = row.notes || '';
        document.getElementById('editStatusAction').value = 'no_change';
        document.getElementById('editModal').classList.add('open');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('open');
    }
</script>

</body>
</html>
