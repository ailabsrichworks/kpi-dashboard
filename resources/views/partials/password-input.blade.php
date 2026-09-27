{{--
    Reusable password field with a show/hide eye toggle.
    Required: $id, $name. Optional: $placeholder, $minlength, $required (default true),
    $inputClass (full input class string), $iconHoverClass (hover color for the eye button).
--}}
@php
    $inputClass = $inputClass ?? 'w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-[12px] focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none';
    $iconHoverClass = $iconHoverClass ?? 'hover:text-[#1a3d34]';
    $isRequired = $required ?? true;
@endphp
<div class="relative">
    <input
        type="password"
        id="{{ $id }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder ?? '' }}"
        @if(!empty($minlength)) minlength="{{ $minlength }}" @endif
        @if($isRequired) required @endif
        class="{{ $inputClass }} pr-10"
    >
    <button
        type="button"
        onclick="togglePasswordVisibility('{{ $id }}')"
        class="absolute right-0 top-0 h-full px-3 flex items-center text-slate-400 {{ $iconHoverClass }} transition"
        aria-label="Show password"
        tabindex="-1"
    >
        <svg class="eye-open w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        </svg>
        <svg class="eye-closed w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
        </svg>
    </button>
</div>

@once
<script>
function togglePasswordVisibility(id) {
    var input = document.getElementById(id);
    if (!input) return;
    var btn = input.nextElementSibling;
    var open = btn.querySelector('.eye-open');
    var closed = btn.querySelector('.eye-closed');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    open.classList.toggle('hidden', show);
    closed.classList.toggle('hidden', !show);
}
</script>
@endonce
