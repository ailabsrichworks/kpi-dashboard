<!DOCTYPE html>
<html>
<head>
    <title>Reset Password · RichWorks KPI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">

    <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-7">

        <div class="mb-6">
            <h1 class="text-base font-bold text-slate-900">
                Reset Password
            </h1>
            <p class="text-xs text-slate-500">
                Choose a new password below
            </p>
        </div>

        @if(session('error'))
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.reset.submit') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Email
                </label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-slate-800 focus:outline-none"
                    placeholder="name@richworks.com"
                    required
                    autofocus
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    New Password
                </label>
                @include('partials.password-input', [
                    'id' => 'newPassword',
                    'name' => 'password',
                    'placeholder' => 'At least 8 characters',
                    'minlength' => 8,
                    'inputClass' => 'w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-slate-800 focus:outline-none',
                    'iconHoverClass' => 'hover:text-slate-800',
                ])
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Confirm New Password
                </label>
                @include('partials.password-input', [
                    'id' => 'newPasswordConfirm',
                    'name' => 'password_confirmation',
                    'placeholder' => 'Re-enter new password',
                    'minlength' => 8,
                    'inputClass' => 'w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-slate-800 focus:outline-none',
                    'iconHoverClass' => 'hover:text-slate-800',
                ])
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-[#06142f] py-3 text-sm font-semibold text-white hover:bg-[#0b1f49] transition"
            >
                Reset Password
            </button>
        </form>

        <div class="mt-5 text-center">
            <a href="{{ route('login') }}" class="text-xs font-semibold text-[#4a7c6b] hover:text-[#2d5548]">
                ← Back to login
            </a>
        </div>

    </div>

</body>
</html>
