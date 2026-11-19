<!DOCTYPE html>
<html>
<head>
    <title>Service Unavailable</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="/vendor/alpinejs/alpinejs-3.14.9.min.js"></script>
</head>
<body class="min-h-screen bg-[#f4f7fb] flex items-center justify-center p-6">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-xl p-10 max-w-md w-full text-center">
        <div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-6">⚠️</div>
        <h1 class="text-2xl font-black text-slate-900">Service Temporarily Unavailable</h1>
        <p class="text-sm text-slate-500 mt-3 leading-relaxed">
            {{ $message ?? 'We are having trouble connecting to the database. Please wait a moment and try again.' }}
        </p>
        <a href="{{ url()->current() }}"
           class="mt-6 inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-slate-900 text-white font-black text-sm hover:bg-slate-700 transition-all">
            🔄 Try Again
        </a>
        <a href="/dashboard"
           class="mt-3 inline-flex items-center gap-2 px-6 py-3 rounded-2xl border border-slate-200 text-slate-600 font-black text-sm hover:bg-slate-50 transition-all ml-2">
            ← Dashboard
        </a>
    </div>
</body>
</html>
