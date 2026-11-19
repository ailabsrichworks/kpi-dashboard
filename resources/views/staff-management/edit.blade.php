<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff Data</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="/vendor/alpinejs/alpinejs-3.14.9.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        label.field-label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .field-input {
            margin-top: 4px; width: 100%; font-size: 12px; border-radius: .5rem;
            padding: .5rem .75rem; border: 1px solid #E2E8F0;
        }
        .field-input:focus { outline: none; box-shadow: 0 0 0 3px rgba(212,175,55,.25); border-color: #D4AF37; }
    </style>
</head>
<body class="bg-[#F5F5F3] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen">

<div class="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
    <div class="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex items-center justify-between gap-3">
        <div class="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>
        <div class="relative">
            <h1 class="text-xl font-black tracking-tight leading-tight">Edit Staff — {{ $employee['short_name'] ?? $employee['full_name'] }}</h1>
            <p class="text-[11px] text-white/60 mt-1">{{ $employee['employee_id'] }} · Setiap perubahan disimpan terus ke rekod staff ini</p>
        </div>
        <a href="{{ route('staff-management', ['company' => $employee['company_code']]) }}" class="relative text-xs font-bold px-3 py-1.5 rounded-lg bg-white/15 border border-white/20 hover:bg-white/25 transition">&larr; Back to List</a>
    </div>
</div>

<div class="px-4 pb-10 space-y-3 max-w-4xl">

    @if(session('success'))
        <div class="bg-[#DCFCE7] border border-[#15803D]/30 text-[#15803D] text-[11px] font-bold rounded-xl px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-[#FEE2E2] border border-[#B91C1C]/30 text-[#B91C1C] text-[11px] font-bold rounded-xl px-4 py-3 space-y-1">
            @foreach($errors->all() as $e)
                <p>• {{ $e }}</p>
            @endforeach
        </div>
    @endif

    <div class="bg-[#FFFBEB] border border-[#D4AF37]/40 text-[#92700C] text-[10px] font-semibold rounded-xl px-4 py-3">
        Nota: Jika Company ditukar, Manager / VP / Reports To akan di-kosongkan secara automatik (sebab mereka dari company lama). Sila buka semula halaman ini selepas simpan untuk tetapkan Manager/VP baru.
    </div>

    <form method="POST" action="{{ route('staff-management.update', $employee['id']) }}" class="bg-white rounded-2xl soft-card border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-6 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <p class="text-[11px] font-black text-slate-800 uppercase tracking-wide mb-3">Identity</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="field-label">Employee ID</label>
                    <input type="text" name="employee_id" value="{{ old('employee_id', $employee['employee_id']) }}" required class="field-input">
                </div>
                <div>
                    <label class="field-label">Salutation</label>
                    <input type="text" name="salutation" value="{{ old('salutation', $employee['salutation']) }}" placeholder="Puan / Encik / Dr..." class="field-input">
                </div>
                <div>
                    <label class="field-label">Status</label>
                    <select name="status" class="field-input">
                        <option value="active" @selected($employee['is_active'])>Active</option>
                        <option value="inactive" @selected(!$employee['is_active'])>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Short Name</label>
                    <input type="text" name="short_name" value="{{ old('short_name', $employee['short_name']) }}" required class="field-input">
                </div>
                <div class="md:col-span-2">
                    <label class="field-label">Full Name</label>
                    <input type="text" name="full_name" value="{{ old('full_name', $employee['full_name']) }}" required class="field-input">
                </div>
                <div class="md:col-span-2">
                    <label class="field-label">Email</label>
                    <input type="email" name="email" value="{{ old('email', $employee['email']) }}" class="field-input">
                </div>
                <div>
                    <label class="field-label">Join Date</label>
                    <input type="date" name="join_date" value="{{ old('join_date', $employee['join_date']) }}" class="field-input">
                </div>
            </div>
        </div>

        <div>
            <p class="text-[11px] font-black text-slate-800 uppercase tracking-wide mb-3">Role & Placement</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="field-label">Role</label>
                    <select name="role" class="field-input">
                        @foreach($roles as $r)
                            <option value="{{ $r }}" @selected(old('role', $employee['role']) === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="field-label">Position</label>
                    <input type="text" name="position" value="{{ old('position', $employee['position']) }}" class="field-input">
                </div>
                <div>
                    <label class="field-label">Company</label>
                    <select name="company_code" id="companySelect" onchange="onCompanyChange(this.value)" class="field-input">
                        @foreach($companies as $c)
                            <option value="{{ $c['code'] }}" @selected(old('company_code', $employee['company_code']) === $c['code'])>{{ $c['name'] ?? $c['code'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="field-label">Department</label>
                    <select name="department_code" id="departmentSelect" class="field-input">
                        <option value="">— Pilih Department —</option>
                        @foreach($allDepartments->groupBy('company_code') as $companyCode => $depts)
                            <optgroup label="{{ $companies->firstWhere('code', $companyCode)['name'] ?? $companyCode }}" data-company="{{ $companyCode }}">
                                @foreach($depts as $d)
                                    <option value="{{ $d['code'] }}" @selected(old('department_code', $employee['department_code']) === $d['code'])>{{ $d['name'] ?? $d['code'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div>
            <p class="text-[11px] font-black text-slate-800 uppercase tracking-wide mb-3">Reporting Line</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="field-label">Manager</label>
                    <select name="manager_id" id="managerSelect" class="field-input">
                        <option value="">— None —</option>
                        @foreach($allEmployees->groupBy('company_code') as $companyCode => $staffList)
                            <optgroup label="{{ $companies->firstWhere('code', $companyCode)['name'] ?? $companyCode }}" data-company="{{ $companyCode }}">
                                @foreach($staffList as $s)
                                    <option value="{{ $s['id'] }}" @selected(old('manager_id', $employee['manager_id']) === $s['id'])>{{ $s['short_name'] ?? $s['full_name'] }} ({{ $s['role'] }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">VP</label>
                    <select name="vp_id" id="vpSelect" class="field-input">
                        <option value="">— None —</option>
                        @foreach($allEmployees->groupBy('company_code') as $companyCode => $staffList)
                            <optgroup label="{{ $companies->firstWhere('code', $companyCode)['name'] ?? $companyCode }}" data-company="{{ $companyCode }}">
                                @foreach($staffList as $s)
                                    <option value="{{ $s['id'] }}" @selected(old('vp_id', $employee['vp_id']) === $s['id'])>{{ $s['short_name'] ?? $s['full_name'] }} ({{ $s['role'] }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Reports To</label>
                    <select name="reports_to_id" id="reportsToSelect" class="field-input">
                        <option value="">— None —</option>
                        @foreach($allEmployees->groupBy('company_code') as $companyCode => $staffList)
                            <optgroup label="{{ $companies->firstWhere('code', $companyCode)['name'] ?? $companyCode }}" data-company="{{ $companyCode }}">
                                @foreach($staffList as $s)
                                    <option value="{{ $s['id'] }}" @selected(old('reports_to_id', $employee['reports_to_id']) === $s['id'])>{{ $s['short_name'] ?? $s['full_name'] }} ({{ $s['role'] }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="text-[9px] text-slate-400 mt-2">Executive guna Manager (fallback VP); Manager guna VP (fallback Reports To); VP/SLT guna Reports To. Isi medan yang sepadan dengan role staff ini.</p>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="hr_access" id="hrAccess" value="1" @checked(old('hr_access', $employee['hr_access'])) class="w-4 h-4 accent-[#D4AF37]">
            <label for="hrAccess" class="text-[11px] font-bold text-slate-700">HR Access (boleh capai ciri HR tambahan)</label>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-[#E5E7EB]">
            <a href="{{ route('staff-management', ['company' => $employee['company_code']]) }}" class="text-xs font-bold px-5 py-2.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="text-xs font-black px-6 py-2.5 rounded-xl bg-[#D4AF37] text-[#1a1a1a] hover:bg-[#c19c2f] transition shadow-md">Simpan Perubahan / Save Changes</button>
        </div>
    </form>

</div>
</main>

<script>
    function filterOptGroupsByCompany(selectId, companyCode, resetValue) {
        var select = document.getElementById(selectId);
        if (!select) return;
        var currentValue = select.value;
        var currentValid = false;
        Array.from(select.querySelectorAll('optgroup')).forEach(function (og) {
            var match = og.dataset.company === companyCode;
            og.hidden = !match;
            if (match) {
                Array.from(og.querySelectorAll('option')).forEach(function (opt) {
                    if (opt.value === currentValue) currentValid = true;
                });
            }
        });
        if (resetValue || !currentValid) {
            select.value = '';
        }
    }

    function onCompanyChange(companyCode) {
        filterOptGroupsByCompany('departmentSelect', companyCode, true);
        filterOptGroupsByCompany('managerSelect', companyCode, true);
        filterOptGroupsByCompany('vpSelect', companyCode, true);
        filterOptGroupsByCompany('reportsToSelect', companyCode, true);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var initialCompany = document.getElementById('companySelect').value;
        filterOptGroupsByCompany('departmentSelect', initialCompany, false);
        filterOptGroupsByCompany('managerSelect', initialCompany, false);
        filterOptGroupsByCompany('vpSelect', initialCompany, false);
        filterOptGroupsByCompany('reportsToSelect', initialCompany, false);
    });
</script>

</body>
</html>
