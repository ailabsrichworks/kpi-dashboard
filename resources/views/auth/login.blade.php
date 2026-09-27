<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCG KPI Dashboard | Login</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .login-bg { background: radial-gradient(circle at top left, rgba(196,184,150,.35), transparent 32%), radial-gradient(circle at bottom right, rgba(166,147,116,.25), transparent 38%), linear-gradient(135deg, #F1EBE0 0%, #E9E0D1 45%, #DED2BC 78%, #F1EBE0 100%); }
    </style>
</head>

<body class="login-bg min-h-screen flex items-center justify-center p-6 relative overflow-hidden">

    <div class="pointer-events-none absolute -top-16 -left-16 w-72 h-72 rounded-full bg-[#C9B896]/25 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-20 -right-10 w-80 h-80 rounded-full bg-[#C9B896]/20 blur-3xl"></div>

    <div class="relative w-full max-w-sm">

        <div class="bg-white rounded-2xl overflow-hidden shadow-[0_25px_70px_rgba(0,0,0,.18)] border-t-[3px] border-t-[#C9B896]">
            <div class="px-8 pt-8 pb-6">

                <div class="flex flex-col items-center text-center mb-6">
                    <h1 class="text-lg font-black text-slate-900 leading-tight">
                        RCG KPI Dashboard
                    </h1>
                    <p class="text-[10px] font-bold text-[#A6906F] uppercase tracking-[0.16em] mt-1">
                        Performance System
                    </p>
                </div>

                @if(session('error'))
                    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

                @if(session('success'))
                    <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">
                            Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-[#C9B896] focus:border-[#C9B896] focus:outline-none transition"
                            placeholder="name@richworks.com"
                            required
                            autofocus
                        >
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">
                                Password
                            </label>
                            <a href="{{ route('password.forgot') }}" class="text-[11px] font-bold text-[#A6906F] hover:text-[#8B7355] transition">
                                Forgot password?
                            </a>
                        </div>
                        @include('partials.password-input', [
                            'id' => 'passwordInput',
                            'name' => 'password',
                            'placeholder' => 'Enter your password',
                            'inputClass' => 'w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-[#C9B896] focus:border-[#C9B896] focus:outline-none transition',
                            'iconHoverClass' => 'hover:text-[#A6906F]',
                        ])
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-[#C9B896] hover:bg-[#BBA57F] py-3 text-sm font-black text-[#3A3128] transition shadow-md hover:-translate-y-0.5"
                    >
                        Login
                    </button>
                </form>
            </div>

            <div class="bg-gradient-to-r from-[#F1EBE0] to-[#DED2BC] px-8 py-3.5 text-center">
                <p class="text-[10px] text-[#6B5D4F] font-semibold">
                    Please contact BTS if you do not have login access.
                </p>
            </div>
        </div>

    </div>

</body>
</html>
