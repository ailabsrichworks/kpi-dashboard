<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-card { box-shadow: 0 8px 30px rgba(15,23,42,.07); }
        .settings-nav-btn {
            width: 100%; display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;
            color: #475569; text-align: left; transition: background .15s, color .15s;
        }
        .settings-nav-btn:hover { background: #f8fafc; }
        .settings-nav-btn.active-tab { background: #eef4f1; color: #1a3d34; }
        .settings-nav-btn.active-tab svg { color: #1a3d34; }
        .palette-cat-btn.active-tab { background: #1a3d34; color: #fff; border-color: #1a3d34; }

        /* ── Appearance: palette strips (horizontal scroll) ──────────────── */
        .palette-strip { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: thin; }
        .palette-strip::-webkit-scrollbar { height: 6px; }
        .palette-strip::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 999px; }
        .palette-chip { flex-shrink: 0; width: 104px; border: 2px solid #e2e8f0; border-radius: 12px; overflow: hidden; cursor: pointer; transition: border-color .12s, transform .12s; background: #fff; }
        .palette-chip:hover { border-color: #6B9080; transform: translateY(-1px); }
        .palette-chip .swatch-row { display: flex; height: 44px; }
        .palette-chip .swatch-row span { flex: 1; }
        .palette-chip p { font-size: 11px; font-weight: 700; color: #475569; padding: 5px 6px; line-height: 1.2; }

        /* ── Appearance: Sidebar / Dashboard group switcher ───────────────── */
        .theme-group-tab { padding: 9px 18px; border-radius: 11px; font-size: 12.5px; font-weight: 800; color: #64748b; transition: background .15s, color .15s; }
        .theme-group-tab:hover { color: #1a3d34; }
        .theme-group-tab.active-group-tab { background: #1a3d34; color: #fff; }
        .theme-group-tab.active-group-tab:hover { color: #fff; }

        /* ── Appearance: Font ──────────────────────────────────────────────── */
        .font-chip.active-font-chip { border-color: #1a3d34; background: #eef4f1; }
        .font-size-chip { padding: 9px 18px; border-radius: 11px; font-size: 12.5px; font-weight: 800; color: #64748b; transition: background .15s, color .15s; }
        .font-size-chip:hover { color: #1a3d34; }
        .font-size-chip.active-font-size-chip { background: #1a3d34; color: #fff; }
        .font-size-chip.active-font-size-chip:hover { color: #fff; }

        /* ── Appearance: full-app preview mockup ─────────────────────────── */
        .tv-tag { position: absolute; top: -8px; left: 8px; z-index: 5; font-size: 7.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; padding: 2px 6px; border-radius: 999px; color: #fff; white-space: nowrap; }
        .tv-tag-ok { background: #1E7A5F; }
        .tv-tag-bug { background: #B5540A; }
        .tv-legend-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 4px; }

        .tv-demo { display: flex; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; }
        .tv-demo-side { width: 150px; flex-shrink: 0; padding: 12px 9px; position: relative; color: #fff; background: #111111; transition: background .15s; }
        .tv-demo-brand { display: flex; gap: 7px; align-items: flex-start; margin: 6px 2px 14px; }
        .tv-demo-brand-tile { width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0; }
        .tv-demo-brand-name { font-size: 7.5px; font-weight: 900; line-height: 1.2; }
        .tv-demo-brand-sub { font-size: 6px; color: rgba(255,255,255,.35); letter-spacing: .08em; margin-top: 2px; }
        .tv-eyebrow { font-size: 7px; font-weight: 800; letter-spacing: .1em; margin: 10px 2px 4px; }
        .tv-accent-line-el { height: 1px; margin-bottom: 7px; }
        .tv-nav-item { display: flex; align-items: center; gap: 6px; padding: 5px 7px; border-radius: 7px; font-size: 8.5px; font-weight: 700; margin-bottom: 2px; color: rgba(255,255,255,.8); }
        .tv-nav-item.active { background: linear-gradient(90deg,#C8102E,#7A0019); border-left: 2.5px solid #D4AF37; color: #fff; padding-left: 5px; }
        .tv-nav-dot { width: 4px; height: 4px; border-radius: 50%; background: currentColor; opacity: .5; flex-shrink: 0; }

        .tv-demo-main { flex: 1; min-width: 0; }
        .tv-demo-header { padding: 10px 12px 5px; }
        .tv-greet { position: relative; overflow: hidden; border-radius: 11px; background: linear-gradient(135deg,#1A0A0A,#7A0019); color: #fff; padding: 11px 13px; }
        .tv-greet-bar { position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg,#D4AF37,#D4AF37,transparent); }
        .tv-greet h4 { margin: 0; font-size: 12px; font-weight: 800; }
        .tv-greet h4 span { color: #D4AF37; }
        .tv-greet-btns { display: flex; gap: 5px; margin-top: 7px; }
        .tv-greet-btns button { font-size: 8px; font-weight: 800; padding: 4px 8px; border-radius: 7px; border: none; }
        .tv-greet-btns .b1 { background: #fff; color: #1a1a1a; }
        .tv-greet-btns .b2 { background: #D4AF37; color: #1a1a1a; }

        .tv-demo-body { padding: 8px 12px 12px; }
        .tv-my-perf { display: flex; border-radius: 12px; overflow: hidden; border: 1px solid #E5E7EB; border-top: 3px solid #D4AF37; }
        .tv-perf-score { color: #fff; padding: 10px; width: 120px; flex-shrink: 0; }
        .tv-perf-score .who { font-size: 9px; font-weight: 800; }
        .tv-perf-score .sub { font-size: 6.5px; color: rgba(255,255,255,.55); margin: 2px 0 7px; }
        .tv-perf-score .box { background: #fff; border-radius: 8px; padding: 7px; }
        .tv-perf-score .box .n { font-size: 16px; font-weight: 900; color: #0f172a; }

        .tv-perf-right { flex: 1; background: #fff; padding: 10px; min-width: 0; }
        .tv-stat-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 8px; }
        .tv-stat { border-radius: 9px; padding: 6px; text-align: center; border: 1px solid; }
        .tv-stat.grey { background: #f8fafc; border-color: #f1f5f9; }
        .tv-stat.green { background: #ecfdf5; border-color: #d1fae5; }
        .tv-stat .n { font-size: 13px; font-weight: 900; }
        .tv-stat.grey .n { color: #0f172a; } .tv-stat.green .n { color: #059669; }
        .tv-stat .l { font-size: 6px; text-transform: uppercase; color: #94a3b8; letter-spacing: .04em; }
        .tv-stat.green .l { color: #34d399; }

        .tv-qtr-label { font-size: 6.5px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
        .tv-qtr-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 5px; }
        .tv-qtr { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 7px; padding: 5px 6px; }
        .tv-qtr .top { display: flex; justify-content: space-between; font-size: 7px; font-weight: 800; color: #334155; margin-bottom: 3px; }
        .tv-qtr .bar { height: 3px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
        .tv-qtr .bar i { display: block; height: 100%; background: #cbd5e1; }

        .tv-bar-row { display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1px solid #E5E7EB; border-left: 3px solid #D4AF37; border-radius: 10px; padding: 8px 10px; margin-top: 8px; font-size: 8.5px; font-weight: 800; color: #334155; }
        .tv-bar-row .hint { font-size: 7px; font-weight: 700; color: #D4AF37; background: rgba(212,175,55,.12); padding: 2px 5px; border-radius: 999px; }

        .tv-linkages { margin-top: 8px; border: 1px solid #E5E7EB; border-left: 3px solid #D4AF37; border-radius: 10px; overflow: hidden; }
        .tv-linkages .head { color: #fff; padding: 7px 10px; font-size: 9px; font-weight: 800; }
        .tv-linkages .head .s { font-size: 6.5px; color: rgba(255,255,255,.55); font-weight: 600; margin-top: 1px; }
        .tv-linkages .empty { background: #fff; padding: 10px; font-size: 7.5px; color: #94a3b8; text-align: center; }

        .tv-doc-label { font-size: 7.5px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin: 10px 0 4px; }
        .tv-doc { border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; }
        .tv-doc-bar { font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: 7px 10px; }
        .tv-doc-body { padding: 10px; font-size: 7.5px; color: #94a3b8; }
    </style>
</head>
<body class="bg-[#f0f2f7] min-h-screen text-slate-900">

@include('partials.sidebar')

<main id="mainContent" class="ml-[230px] min-h-screen transition-all duration-300">
<div class="p-4 space-y-4">

    <div class="flex items-center justify-between">
        <a href="{{ route('profile') }}" class="text-[10px] text-slate-500 hover:text-slate-800">← Profile</a>
    </div>

    <div>
        <h1 class="text-lg font-black text-slate-900">Account Settings</h1>
        <p class="text-[12px] text-slate-500 mt-0.5">Notifications, email, password, and appearance — pick a section on the left.</p>
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[12px] font-semibold text-emerald-700">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">
        {{ $errors->first() }}
    </div>
    @endif

    @php
        $themeBg     = $user['theme_bg']     ?? '#F5F5F3';
        $themeCard   = $user['theme_card']   ?? '#FFFFFF';
        $themeAccent = $user['theme_accent'] ?? '#D4AF37';
        $themeBorder = $user['theme_border'] ?? '#6B9080';
        $themeText   = $user['theme_text']   ?? '#0F172A';
        // Chart accent (2nd colour) also drives My Performance card's pastel
        // fill directly (see partials/sidebar.blade.php) — one swatch, not two.
        $themeAccent2 = $user['theme_accent2'] ?? '#6B9080';
        $hasCustomTheme = !empty($user['theme_bg']) || !empty($user['theme_card']) || !empty($user['theme_accent']) || !empty($user['theme_border']) || !empty($user['theme_text']) || !empty($user['theme_accent2']);

        // Sidebar theme is independent from the main theme above, but falls back to
        // the legacy theme_accent so existing custom accents keep showing in the
        // sidebar until the user sets a sidebar theme of their own.
        $sidebarBg     = $user['theme_sidebar_bg']     ?? '#111111';
        $sidebarAccent = $user['theme_sidebar_accent'] ?? $themeAccent;
        $sidebarText   = $user['theme_sidebar_text']   ?? '#FFFFFF';
        $hasCustomSidebarTheme = !empty($user['theme_sidebar_bg']) || !empty($user['theme_sidebar_accent']) || !empty($user['theme_sidebar_text']);
        $hasAnyCustomTheme = $hasCustomTheme || $hasCustomSidebarTheme;

        $sidebarPalettes = [
            ['name' => 'Midnight Gold',  'bg' => '#111111', 'accent' => '#D4AF37', 'text' => '#FFFFFF'],
            ['name' => 'Espresso Bronze','bg' => '#2A1D16', 'accent' => '#D4AF37', 'text' => '#F5EFE6'],
            ['name' => 'Navy Ice',       'bg' => '#0F1B2D', 'accent' => '#38BDF8', 'text' => '#E8F2FA'],
            ['name' => 'Forest Night',   'bg' => '#0F231A', 'accent' => '#34D399', 'text' => '#E7F5EE'],
            ['name' => 'Plum Dusk',      'bg' => '#1F1329', 'accent' => '#C4B5FD', 'text' => '#F1EAFB'],
            ['name' => 'Charcoal Steel', 'bg' => '#1E2530', 'accent' => '#94A3B8', 'text' => '#F1F5F9'],
            ['name' => 'Wine Noir',      'bg' => '#1A0A10', 'accent' => '#E4572E', 'text' => '#F7E9E5'],
            ['name' => 'Frost Light',    'bg' => '#F5F5F3', 'accent' => '#1A3D34', 'text' => '#0F172A'],
        ];

        $palettes = [
            // Popular — evergreen, safe defaults
            ['name' => 'Classic Gold',   'category' => 'Popular', 'bg' => '#F5F5F3', 'card' => '#FFFFFF', 'accent' => '#D4AF37', 'border' => '#6B9080'],
            ['name' => 'Ocean Blue',     'category' => 'Popular', 'bg' => '#EFF6FB', 'card' => '#FFFFFF', 'accent' => '#2563EB', 'border' => '#93C5FD'],
            ['name' => 'Forest Green',   'category' => 'Popular', 'bg' => '#F3F7F1', 'card' => '#FFFFFF', 'accent' => '#15803D', 'border' => '#86A98F'],
            ['name' => 'Slate Charcoal', 'category' => 'Popular', 'bg' => '#F2F3F5', 'card' => '#FFFFFF', 'accent' => '#334155', 'border' => '#94A3B8'],

            // Vibrant — bold, high-energy accents
            ['name' => 'Sunset Coral',   'category' => 'Vibrant', 'bg' => '#FDF3F0', 'card' => '#FFFFFF', 'accent' => '#E4572E', 'border' => '#F2A488'],
            ['name' => 'Berry Rose',     'category' => 'Vibrant', 'bg' => '#FDF2F6', 'card' => '#FFFFFF', 'accent' => '#BE185D', 'border' => '#F0A8C4'],
            ['name' => 'Royal Purple',   'category' => 'Vibrant', 'bg' => '#F5F3FA', 'card' => '#FFFFFF', 'accent' => '#6D28D9', 'border' => '#C4B5FD'],
            ['name' => 'Electric Teal',  'category' => 'Vibrant', 'bg' => '#EAFBFA', 'card' => '#FFFFFF', 'accent' => '#0D9488', 'border' => '#5EEAD4'],
            ['name' => 'Coral Punch',    'category' => 'Vibrant', 'bg' => '#FFF4F1', 'card' => '#FFFFFF', 'accent' => '#FB5607', 'border' => '#FFB4A2'],
            ['name' => 'Neon Lime',      'category' => 'Vibrant', 'bg' => '#F7FCEF', 'card' => '#FFFFFF', 'accent' => '#65A30D', 'border' => '#BEF264'],

            // Muted — soft, low-contrast pastels
            ['name' => 'Warm Sand',      'category' => 'Muted', 'bg' => '#FBF6EE', 'card' => '#FFFFFF', 'accent' => '#B45309', 'border' => '#D8B989'],
            ['name' => 'Dusty Lilac',     'category' => 'Muted', 'bg' => '#F8F5FB', 'card' => '#FFFFFF', 'accent' => '#9D8DF1', 'border' => '#D8CFF5'],
            ['name' => 'Sage Mist',       'category' => 'Muted', 'bg' => '#F4F7F4', 'card' => '#FFFFFF', 'accent' => '#7C9A82', 'border' => '#C8D5C9'],
            ['name' => 'Powder Blue',     'category' => 'Muted', 'bg' => '#F2F8FB', 'card' => '#FFFFFF', 'accent' => '#7CA9C9', 'border' => '#C7E0ED'],
            ['name' => 'Blush Sand',      'category' => 'Muted', 'bg' => '#FBF3EF', 'card' => '#FFFFFF', 'accent' => '#D6A184', 'border' => '#EAC9B4'],

            // Moody — dark, confident accents on a light surface
            ['name' => 'Espresso Brown', 'category' => 'Moody', 'bg' => '#F7F3F0', 'card' => '#FFFFFF', 'accent' => '#4B2E1E', 'border' => '#C9A88A'],
            ['name' => 'Charcoal Ink',   'category' => 'Moody', 'bg' => '#F1F2F4', 'card' => '#FFFFFF', 'accent' => '#1E293B', 'border' => '#94A3B8'],
            ['name' => 'Onyx Steel',     'category' => 'Moody', 'bg' => '#F0F1F3', 'card' => '#FFFFFF', 'accent' => '#27272A', 'border' => '#A1A1AA'],

            // Earthy — warm, organic tones
            ['name' => 'Terracotta Clay','category' => 'Earthy', 'bg' => '#FBF2EC', 'card' => '#FFFFFF', 'accent' => '#C1662F', 'border' => '#E3B28C'],
            ['name' => 'Olive Grove',     'category' => 'Earthy', 'bg' => '#F5F6EE', 'card' => '#FFFFFF', 'accent' => '#6B7A3A', 'border' => '#C3CBA0'],
            ['name' => 'Clay & Sage',     'category' => 'Earthy', 'bg' => '#F6F3EE', 'card' => '#FFFFFF', 'accent' => '#A9764C', 'border' => '#9CAF88'],

            // Cool — crisp, professional blues
            ['name' => 'Arctic Ice',     'category' => 'Cool', 'bg' => '#F0F7FA', 'card' => '#FFFFFF', 'accent' => '#38BDF8', 'border' => '#BAE6FD'],
            ['name' => 'Denim Wash',      'category' => 'Cool', 'bg' => '#EFF3F8', 'card' => '#FFFFFF', 'accent' => '#3B5B80', 'border' => '#A8C0D8'],
            ['name' => 'Steel Blue',      'category' => 'Cool', 'bg' => '#EEF2F6', 'card' => '#FFFFFF', 'accent' => '#4A6FA5', 'border' => '#9FB8D4'],
        ];

        $paletteCategories = ['All', 'Popular', 'Vibrant', 'Muted', 'Moody', 'Earthy', 'Cool'];

        // Font — applies to every page (body text + form fields), independent of colour.
        $themeFontFamily = $user['theme_font_family'] ?? 'Inter';
        $themeFontSize   = $user['theme_font_size']   ?? 'md';
        $hasCustomFont   = !empty($user['theme_font_family']) || !empty($user['theme_font_size']);

        $fontFamilies = [
            ['key' => 'Inter',       'label' => 'Inter',       'fallback' => 'sans-serif'],
            ['key' => 'Poppins',     'label' => 'Poppins',     'fallback' => 'sans-serif'],
            ['key' => 'Roboto',      'label' => 'Roboto',      'fallback' => 'sans-serif'],
            ['key' => 'Nunito',      'label' => 'Nunito',      'fallback' => 'sans-serif'],
            ['key' => 'Merriweather','label' => 'Merriweather (Serif)', 'fallback' => 'serif'],
            ['key' => 'Fira Code',   'label' => 'Fira Code (Mono)',     'fallback' => 'monospace'],
        ];

        // Most of the app sets text in fixed pixels (text-[9px] etc.), not rem, so a
        // plain font-size override on <html> would silently miss almost everything.
        // `zoom` scales the whole rendered page proportionally instead — layout included.
        $fontSizes = [
            ['key' => 'sm', 'label' => 'Compact',     'zoom' => 0.9,  'hint' => 'Fits more on screen — tighter text and spacing everywhere.'],
            ['key' => 'md', 'label' => 'Default',     'zoom' => 1,    'hint' => 'The size every page ships with today.'],
            ['key' => 'lg', 'label' => 'Comfortable', 'zoom' => 1.15, 'hint' => 'Larger text and spacing everywhere — easier to read.'],
        ];
    @endphp

    {{-- ═══ TWO-PANE SETTINGS LAYOUT: left = section nav, right = selected section ═══ --}}
    <div class="flex flex-col md:flex-row gap-4 items-start">

        {{-- LEFT: settings nav --}}
        <nav class="w-full md:w-56 shrink-0 bg-white rounded-2xl soft-card border border-slate-200 p-2 flex md:flex-col gap-1 overflow-x-auto md:overflow-visible">
            <button type="button" data-tab="profile" onclick="selectSettingsTab('profile')" class="settings-nav-btn">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                <span class="truncate">Profile</span>
            </button>

            <p class="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Notifications</p>

            <button type="button" data-tab="telegram" onclick="selectSettingsTab('telegram')" class="settings-nav-btn">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="#229ED9"><path d="M21.94 4.53a1.6 1.6 0 0 0-1.63-.27L2.98 10.98a1.53 1.53 0 0 0 .1 2.88l4.54 1.42 1.76 5.5c.14.44.5.72.94.72.03 0 .06 0 .1-.01.34-.03.63-.24.77-.55l2.15-3.9 4.5 3.3c.24.18.53.27.82.27.14 0 .29-.02.43-.07a1.5 1.5 0 0 0 1-1.1l3.03-13.7a1.6 1.6 0 0 0-.62-1.74Zm-3.35 2.68-8.03 7.28-.31 3.35-1.35-4.22 8.6-6.9c.2-.16.42.1.24.28l-6.9 6.24a.5.5 0 0 0-.15.3l-.2 2.13 8.6-9.7c.2-.23.5.03.33.24Z"/></svg>
                <span class="truncate">Telegram</span>
            </button>

            <p class="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Account Security</p>

            <button type="button" data-tab="email" onclick="selectSettingsTab('email')" class="settings-nav-btn">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>
                <span class="truncate">Change Email</span>
            </button>
            <button type="button" data-tab="password" onclick="selectSettingsTab('password')" class="settings-nav-btn">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="9" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <span class="truncate">Change Password</span>
            </button>

            <p class="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Personalisation</p>

            <button type="button" data-tab="appearance" onclick="selectSettingsTab('appearance')" class="settings-nav-btn">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2h2c2.2 0 4-1.8 4-4 0-4.4-4-8-9-8Z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="10.5" r="1"/></svg>
                <span class="truncate">Appearance</span>
                @if($hasAnyCustomTheme)
                    <span class="ml-auto text-[8px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 shrink-0">Custom</span>
                @endif
            </button>

            @if(strtoupper(trim($user['department_code'] ?? '')) === 'BTS')
            <p class="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Admin</p>
            <a href="{{ route('admin.view-as') }}" class="settings-nav-btn">
                <svg class="w-4 h-4 shrink-0 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7Z"/></svg>
                <span class="truncate">View As</span>
            </a>
            @endif
        </nav>

        {{-- RIGHT: selected section content --}}
        <div class="flex-1 min-w-0 w-full">

            {{-- PROFILE --}}
            <div id="tab-profile" class="settings-panel bg-white rounded-2xl soft-card border border-slate-200 p-5">
                <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-3">Profile</p>
                <p class="text-[13px] font-black text-slate-900 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-[#6B9080]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                    Display Title
                </p>
                <p class="text-[11px] text-slate-500 mt-0.5 mb-4">Shown before your name in the top bar and sidebar — e.g. "{{ $user['salutation'] ?? 'Mr.' }} {{ $user['short_name'] ?? $user['full_name'] ?? 'Name' }}".</p>
                <form method="POST" action="{{ route('settings.salutation.update') }}" class="space-y-2.5 max-w-sm">
                    @csrf
                    <select
                        name="salutation"
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-[12px] focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none"
                    >
                        <option value="" {{ empty($user['salutation']) ? 'selected' : '' }}>None</option>
                        @foreach(['Mr.', 'Mrs.', 'Ms.', 'Dr.'] as $title)
                            <option value="{{ $title }}" {{ ($user['salutation'] ?? '') === $title ? 'selected' : '' }}>{{ $title }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full text-[11px] font-black px-3 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                        Save
                    </button>
                </form>
            </div>

            {{-- TELEGRAM --}}
            <div id="tab-telegram" class="settings-panel hidden bg-white rounded-2xl soft-card border border-slate-200 p-5">
                <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-3">Notifications</p>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-[#229ED9]/10 flex items-center justify-center shrink-0">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="#229ED9"><path d="M21.94 4.53a1.6 1.6 0 0 0-1.63-.27L2.98 10.98a1.53 1.53 0 0 0 .1 2.88l4.54 1.42 1.76 5.5c.14.44.5.72.94.72.03 0 .06 0 .1-.01.34-.03.63-.24.77-.55l2.15-3.9 4.5 3.3c.24.18.53.27.82.27.14 0 .29-.02.43-.07a1.5 1.5 0 0 0 1-1.1l3.03-13.7a1.6 1.6 0 0 0-.62-1.74Zm-3.35 2.68-8.03 7.28-.31 3.35-1.35-4.22 8.6-6.9c.2-.16.42.1.24.28l-6.9 6.24a.5.5 0 0 0-.15.3l-.2 2.13 8.6-9.7c.2-.23.5.03.33.24Z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[13px] font-black text-slate-900">Telegram Notifications</p>
                            <p id="tg-status-text" class="text-[11px] text-slate-500 mt-0.5">Checking status…</p>
                        </div>
                    </div>
                    <button
                        id="tg-connect-btn"
                        type="button"
                        onclick="connectTelegram()"
                        class="text-[11px] font-black px-3 py-2 rounded-xl bg-[#6B9080] text-white hover:bg-[#5a7a6d] transition shrink-0"
                    >
                        Connect Telegram
                    </button>
                </div>
                <p class="text-[11px] text-slate-500 mt-3">Link your Telegram account to receive daily KPI reminders and approval alerts.</p>
            </div>

            {{-- CHANGE EMAIL --}}
            <div id="tab-email" class="settings-panel hidden bg-white rounded-2xl soft-card border border-slate-200 p-5">
                <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-3">Account Security</p>
                <p class="text-[13px] font-black text-slate-900 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-[#6B9080]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z"/></svg>
                    Change Email
                </p>
                <p class="text-[11px] text-slate-500 mt-0.5 mb-4">Current: {{ $user['email'] ?? '—' }}</p>
                <form method="POST" action="{{ route('settings.email.update') }}" class="space-y-2.5 max-w-sm">
                    @csrf
                    <input
                        type="email"
                        name="email"
                        placeholder="New email address"
                        required
                        class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-[12px] focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none"
                    >
                    @include('partials.password-input', ['id' => 'curPwdForEmail', 'name' => 'current_password', 'placeholder' => 'Current password (to confirm)'])
                    <button type="submit" class="w-full text-[11px] font-black px-3 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                        Update Email
                    </button>
                </form>
            </div>

            {{-- CHANGE PASSWORD --}}
            <div id="tab-password" class="settings-panel hidden bg-white rounded-2xl soft-card border border-slate-200 p-5">
                <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-3">Account Security</p>
                <p class="text-[13px] font-black text-slate-900 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-[#6B9080]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="9" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    Change Password
                </p>
                <p class="text-[11px] text-slate-500 mt-0.5 mb-4">Keep your account secure.</p>
                <form method="POST" action="{{ route('settings.password.update') }}" class="space-y-2.5 max-w-sm">
                    @csrf
                    @include('partials.password-input', ['id' => 'curPwdForChange', 'name' => 'current_password', 'placeholder' => 'Current password'])
                    @include('partials.password-input', ['id' => 'newPwd', 'name' => 'password', 'placeholder' => 'New password (min 8 characters)', 'minlength' => 8])
                    @include('partials.password-input', ['id' => 'newPwdConfirm', 'name' => 'password_confirmation', 'placeholder' => 'Confirm new password', 'minlength' => 8])
                    <button type="submit" class="w-full text-[11px] font-black px-3 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                        Update Password
                    </button>
                </form>
                <p class="text-[10px] text-slate-400 mt-3">
                    Forgot your current password instead? <a href="{{ route('password.forgot') }}" class="font-semibold text-[#4a7c6b] hover:text-[#2d5548]">Reset it via email →</a>
                </p>
            </div>

            {{-- APPEARANCE --}}
            <div id="tab-appearance" class="settings-panel hidden bg-white rounded-2xl soft-card border border-slate-200 p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-1">Personalisation</p>
                        <h2 class="text-[13px] font-black text-slate-900">Pick your own colours — sidebar and dashboard are independent</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5 max-w-lg">
                            Each group below applies on its own — give the sidebar its own palette without touching the dashboard, or vice versa. Never changes the red/amber/green status colours.
                        </p>
                    </div>
                    @if($hasAnyCustomTheme)
                        <span class="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 shrink-0">Custom theme active</span>
                    @else
                        <span class="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-slate-100 text-slate-500 shrink-0">Default theme</span>
                    @endif
                </div>

                {{-- Two independent theme groups — one at a time, full width, bigger swatches --}}
                <div class="inline-flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-3">
                    <button type="button" data-group-tab="sidebar" onclick="selectThemeGroup('sidebar')" class="theme-group-tab flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#111111] shrink-0"></span> Sidebar
                        @if($hasCustomSidebarTheme)<span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Custom</span>@endif
                    </button>
                    <button type="button" data-group-tab="main" onclick="selectThemeGroup('main')" class="theme-group-tab flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#D4AF37] shrink-0"></span> Dashboard &amp; Pages
                        @if($hasCustomTheme)<span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Custom</span>@endif
                    </button>
                </div>

                <div class="border border-slate-200 rounded-xl p-4">

                    {{-- SIDEBAR THEME --}}
                    <div id="theme-group-sidebar">
                        <div class="palette-strip mb-3">
                            @foreach($sidebarPalettes as $i => $p)
                            <button type="button" onclick="applySidebarPalette({{ $i }})" class="palette-chip" title="{{ $p['name'] }}">
                                <div class="swatch-row">
                                    <span style="background:{{ $p['bg'] }};"></span>
                                    <span style="background:{{ $p['accent'] }};"></span>
                                    <span style="background:{{ $p['text'] }};"></span>
                                </div>
                                <p class="truncate">{{ $p['name'] }}</p>
                            </button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-5 flex-wrap pt-3 border-t border-slate-100">
                            @foreach([
                                ['key' => 'bg',     'label' => 'Background', 'value' => $sidebarBg],
                                ['key' => 'accent', 'label' => 'Accent',     'value' => $sidebarAccent],
                                ['key' => 'text',   'label' => 'Text',       'value' => $sidebarText],
                            ] as $slot)
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <span class="relative w-12 h-12 rounded-full border-[3px] border-slate-200 overflow-hidden shadow-[inset_0_2px_4px_rgba(255,255,255,.5),inset_0_-3px_6px_rgba(0,0,0,.25),0_3px_8px_rgba(0,0,0,.15)] shrink-0" style="background:linear-gradient(135deg, color-mix(in srgb, {{ $slot['value'] }} 85%, white) 0%, {{ $slot['value'] }} 55%, color-mix(in srgb, {{ $slot['value'] }} 75%, black) 100%);">
                                    <input type="color" id="theme-sidebar-{{ $slot['key'] }}" value="{{ $slot['value'] }}" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" oninput="onThemeColorChange('sidebar', '{{ $slot['key'] }}', this.value)">
                                </span>
                                <span class="leading-tight">
                                    <span class="block text-[12px] font-black text-slate-700">{{ $slot['label'] }}</span>
                                    <span id="theme-sidebar-{{ $slot['key'] }}-hex" class="block text-[10px] font-mono text-slate-400 uppercase">{{ $slot['value'] }}</span>
                                </span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- DASHBOARD & PAGES THEME --}}
                    <div id="theme-group-main" class="hidden">
                        <div class="flex items-center gap-1.5 flex-wrap mb-3">
                            @foreach($paletteCategories as $cat)
                            <button type="button" data-cat="{{ $cat }}" onclick="filterPalettes('{{ $cat }}')" class="palette-cat-btn text-[10.5px] font-bold px-2.5 py-1 rounded-full border border-slate-200 text-slate-500 hover:border-[#6B9080] transition shrink-0">{{ $cat }}</button>
                            @endforeach
                        </div>

                        <div class="palette-strip mb-3">
                            @foreach($palettes as $i => $p)
                            <button type="button" data-group="main" data-category="{{ $p['category'] }}" onclick="applyPalette({{ $i }})" class="palette-chip" title="{{ $p['name'] }} — {{ $p['category'] }}">
                                <div class="swatch-row">
                                    <span style="background:{{ $p['bg'] }};"></span>
                                    <span style="background:{{ $p['card'] }};"></span>
                                    <span style="background:{{ $p['border'] }};"></span>
                                </div>
                                <p class="truncate">{{ $p['name'] }}</p>
                            </button>
                            @endforeach
                        </div>

                        @php
                            $swatchGroups = [
                                [
                                    'title' => 'Base look',
                                    'hint'  => 'The page itself — background, cards and text.',
                                    'slots' => [
                                        ['key' => 'bg',     'label' => 'Page Background', 'hint' => 'The colour behind everything, all pages.',            'value' => $themeBg],
                                        ['key' => 'card',   'label' => 'Card Background',  'hint' => 'Fill colour of every white card.',                     'value' => $themeCard],
                                        ['key' => 'border', 'label' => 'Card Border',      'hint' => 'Outline colour around cards.',                          'value' => $themeBorder],
                                        ['key' => 'text',   'label' => 'Heading Text',     'hint' => 'Colour of card titles/headings.',                       'value' => $themeText],
                                    ],
                                ],
                                [
                                    'title' => 'Accent combination',
                                    'hint'  => 'The pair to mix and match — used for buttons, badges and charts.',
                                    'slots' => [
                                        ['key' => 'accent',  'label' => '1st Accent', 'hint' => 'Buttons, badges, highlights — the main brand colour.',              'value' => $themeAccent],
                                        ['key' => 'accent2', 'label' => '2nd Accent', 'hint' => 'Chart bars and the My Performance card — pairs with 1st Accent.',  'value' => $themeAccent2],
                                    ],
                                ],
                            ];
                        @endphp
                        <div class="space-y-4 pt-3 border-t border-slate-100">
                            @foreach($swatchGroups as $group)
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">{{ $group['title'] }}</p>
                                <p class="text-[10px] text-slate-400 mb-2.5">{{ $group['hint'] }}</p>
                                <div class="flex items-center gap-5 flex-wrap">
                                    @foreach($group['slots'] as $slot)
                                    <label class="flex items-center gap-2.5 cursor-pointer" title="{{ $slot['hint'] }}">
                                        <span class="relative w-12 h-12 rounded-full border-[3px] border-slate-200 overflow-hidden shadow-[inset_0_2px_4px_rgba(255,255,255,.5),inset_0_-3px_6px_rgba(0,0,0,.25),0_3px_8px_rgba(0,0,0,.15)] shrink-0" style="background:linear-gradient(135deg, color-mix(in srgb, {{ $slot['value'] }} 85%, white) 0%, {{ $slot['value'] }} 55%, color-mix(in srgb, {{ $slot['value'] }} 75%, black) 100%);">
                                            <input type="color" id="theme-{{ $slot['key'] }}" value="{{ $slot['value'] }}" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" oninput="onThemeColorChange('main', '{{ $slot['key'] }}', this.value)">
                                        </span>
                                        <span class="leading-tight">
                                            <span class="block text-[12px] font-black text-slate-700">{{ $slot['label'] }}</span>
                                            <span id="theme-{{ $slot['key'] }}-hex" class="block text-[10px] font-mono text-slate-400 uppercase">{{ $slot['value'] }}</span>
                                        </span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Live visualize preview — a faithful recreation of the real dashboard, so you know exactly what changes --}}
                <div class="mt-4">
                    <div class="flex items-center justify-between gap-2 mb-1.5 flex-wrap">
                        <p class="text-[9px] uppercase tracking-widest font-black text-slate-400">Visualize — what actually changes</p>
                        <div class="flex items-center gap-1.5 text-[8.5px] font-bold text-emerald-700">
                            <span class="tv-legend-dot" style="background:#1E7A5F;"></span>Everything below is customizable
                        </div>
                    </div>

                    <div class="tv-demo">
                        {{-- Sidebar mockup — driven by the Sidebar group above --}}
                        <div class="tv-demo-side tv-side-bg" id="tv-side" style="background:{{ $sidebarBg }};">
                            <div class="tv-side-accent-bg" id="tv-side-accent-bar" style="position:absolute;top:0;left:0;right:0;height:2px;background:{{ $sidebarAccent }};"></div>
                            <div class="relative">
                                <span class="tv-tag tv-tag-ok">customizable — background + accent</span>
                                <div class="tv-demo-brand">
                                    <div class="tv-demo-brand-tile" id="tv-brand-tile" style="background:{{ $sidebarBg }};border:1.5px solid {{ $sidebarAccent }};"></div>
                                    <div>
                                        <div class="tv-demo-brand-name">RICHWORKS<br>CONSULTING GROUP</div>
                                        <div class="tv-demo-brand-sub">PERFORMANCE SYSTEM</div>
                                    </div>
                                </div>
                            </div>
                            <div class="relative">
                                <span class="tv-tag tv-tag-ok">customizable</span>
                                <p class="tv-eyebrow tv-side-accent-text" id="tv-side-eyebrow" style="color:{{ $sidebarAccent }};">OVERVIEW</p>
                                <div class="tv-accent-line-el tv-side-accent-gradient" id="tv-side-line" style="background:linear-gradient(90deg, {{ $sidebarAccent }}, transparent);"></div>
                                <div class="tv-nav-item active tv-side-active" id="tv-side-active-item" style="background:linear-gradient(135deg, {{ $sidebarAccent }}, color-mix(in srgb, {{ $sidebarAccent }} 35%, black));border-left:2.5px solid {{ $sidebarAccent }};"><span class="tv-nav-dot"></span>Main Dashboard</div>
                                <div class="tv-nav-item tv-side-text" style="color:{{ $sidebarText }};"><span class="tv-nav-dot"></span>Mini App</div>
                                <div class="tv-nav-item tv-side-text" style="color:{{ $sidebarText }};"><span class="tv-nav-dot"></span>Notifications</div>
                            </div>
                        </div>

                        {{-- Main content mockup — driven by the Dashboard & Pages group above --}}
                        <div class="tv-demo-main">
                            <div class="tv-demo-header" id="tv-header" style="background:#F5F5F3;">
                                <div class="relative">
                                    <span class="tv-tag tv-tag-ok">customizable</span>
                                    <div class="tv-greet" id="tv-greet" style="background:linear-gradient(135deg, {{ $themeAccent }}, color-mix(in srgb, {{ $themeAccent }} 35%, black));">
                                        <div class="tv-greet-bar" id="tv-greet-bar" style="background:linear-gradient(90deg, {{ $themeAccent }}, {{ $themeAccent }}, transparent);"></div>
                                        <h4><span id="tv-greet-prefix" style="color:{{ $themeText }};">Hi, Good Afternoon</span> <span id="tv-greet-name" style="color:{{ $themeText }};">ARINA</span> 👋</h4>
                                        <div class="tv-greet-btns">
                                            <button type="button" class="b1" id="tv-greet-btn1" style="color:color-mix(in srgb, {{ $themeAccent }} 70%, black);">+ Create KPI</button>
                                            <button type="button" class="b2" id="tv-greet-btn2" style="background:{{ $themeAccent }};">My KPIs</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tv-demo-body tv-main-bg" id="tv-main-body" style="background:{{ $themeBg }};">
                                <div class="relative">
                                    <span class="tv-tag tv-tag-ok">customizable — page background</span>
                                </div>
                                <div class="relative mt-2">
                                    <span class="tv-tag tv-tag-ok">customizable border + Chart accent fill</span>
                                    <div class="tv-my-perf tv-main-card-border" id="tv-my-perf" style="border-color:{{ $themeBorder }};">
                                        <div class="tv-perf-score" id="tv-perf-score" style="background:color-mix(in srgb, {{ $themeAccent2 }} 18%, white);">
                                            <p class="who" id="tv-perf-who" style="color:#1e293b;">ARINA</p>
                                            <p class="sub" id="tv-perf-sub" style="color:#64748b;">TESTER · BTS</p>
                                            <div class="box"><span class="n">82.4</span><span style="font-size:8px;color:#94a3b8;">%</span></div>
                                        </div>
                                        <div class="tv-perf-right">
                                            <div class="tv-stat-row">
                                                <div class="tv-stat grey"><div class="n">12</div><div class="l">Total KPIs</div></div>
                                                <div class="tv-stat green"><div class="n">9</div><div class="l">On Track</div></div>
                                                <div class="tv-stat grey"><div class="n">0</div><div class="l">At Risk</div></div>
                                            </div>
                                            <div class="tv-qtr-label">My Quarterly Progress</div>
                                            <div class="tv-qtr-row">
                                                <div class="tv-qtr"><div class="top"><span>Q1</span><span>78%</span></div><div class="bar"><i style="width:78%"></i></div></div>
                                                <div class="tv-qtr"><div class="top"><span>Q2</span><span>85%</span></div><div class="bar"><i style="width:85%"></i></div></div>
                                                <div class="tv-qtr"><div class="top"><span>Q3</span><span>82%</span></div><div class="bar"><i style="width:82%"></i></div></div>
                                                <div class="tv-qtr"><div class="top"><span>Q4</span><span>0%</span></div><div class="bar"><i style="width:0%"></i></div></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="relative">
                                    <span class="tv-tag tv-tag-ok">customizable fill / border / text</span>
                                    <div class="tv-bar-row tv-main-card-bg tv-main-border-left" id="tv-bar-row" style="background:{{ $themeCard }};border-left-color:{{ $themeBorder }};">
                                        <span id="tv-bar-row-text" style="color:{{ $themeText }};">Company Overview — department ranking, quarterly trends</span>
                                        <span class="hint">Show ›</span>
                                    </div>
                                </div>

                                <div class="relative">
                                    <span class="tv-tag tv-tag-ok">border + accent header customizable</span>
                                    <div class="tv-linkages tv-main-border-left" id="tv-linkages" style="border-left-color:{{ $themeBorder }};">
                                        <div class="relative">
                                            <div class="head" id="tv-linkages-head" style="background:linear-gradient(90deg, {{ $themeAccent }}, color-mix(in srgb, {{ $themeAccent }} 35%, black));">
                                                <span id="tv-linkages-title" style="color:{{ $themeText }};">KPI Target Linkages</span>
                                                <div class="s" id="tv-linkages-sub" style="color:color-mix(in srgb, {{ $themeText }} 65%, transparent);">Cascading targets · FY2026</div>
                                            </div>
                                        </div>
                                        <div class="empty tv-main-card-bg" id="tv-linkages-empty" style="background:{{ $themeCard }};">No linkage targets yet. Use "+ Assign Target" to assign a cascading target to your team.</div>
                                    </div>
                                </div>

                                <p class="tv-doc-label">Document pages — Job Description, Performance Reports</p>
                                <div class="relative">
                                    <span class="tv-tag tv-tag-ok">title bar + shadow customizable</span>
                                    <div class="tv-doc" id="tv-doc" style="box-shadow: 0 10px 24px color-mix(in srgb, {{ $themeAccent }} 22%, transparent);">
                                        <div class="tv-doc-bar" id="tv-doc-bar" style="background:linear-gradient(135deg, {{ $themeAccent }}, color-mix(in srgb, {{ $themeAccent }} 35%, black));color:{{ $themeText }};">Job Description</div>
                                        <div class="tv-doc-body tv-main-card-bg" id="tv-doc-body" style="background:{{ $themeCard }};">Position · Department · Reporting To …</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-4">
                    <button type="button" onclick="saveTheme()" id="theme-save-btn" class="text-[11px] font-black px-4 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                        Save Theme
                    </button>
                    <button type="button" onclick="resetTheme()" class="text-[11px] font-black px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                        Reset to Default
                    </button>
                    <span id="theme-save-msg" class="text-[11px] font-semibold ml-1"></span>
                </div>
            </div>

            {{-- FONT --}}
            <div class="mt-5 pt-5 border-t border-slate-100">
                <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
                    <div>
                        <h2 class="text-[13px] font-black text-slate-900">Font — applies everywhere, not just here</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5 max-w-lg">Typeface changes every page's text. Size scales every page's layout up or down (buttons, cards, spacing included) since most text here is set in fixed pixels, not just the words.</p>
                    </div>
                    @if($hasCustomFont)
                        <span class="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 shrink-0">Custom font active</span>
                    @else
                        <span class="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-slate-100 text-slate-500 shrink-0">Default font</span>
                    @endif
                </div>

                <div class="border border-slate-200 rounded-xl p-4">
                    <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-2">Typeface</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
                        @foreach($fontFamilies as $f)
                        <button type="button" data-font-family="{{ $f['key'] }}" onclick="selectFontFamily('{{ $f['key'] }}')"
                            class="font-chip text-left px-3 py-2.5 rounded-xl border-2 border-slate-200 hover:border-[#6B9080] transition {{ $themeFontFamily === $f['key'] ? 'active-font-chip' : '' }}">
                            <span class="block text-[15px] leading-tight text-slate-800" style="font-family: '{{ $f['key'] }}', {{ $f['fallback'] }};">Aa — {{ $f['label'] }}</span>
                            <span class="block text-[9px] text-slate-400 mt-0.5" style="font-family: '{{ $f['key'] }}', {{ $f['fallback'] }};">The quick brown fox jumps</span>
                        </button>
                        @endforeach
                    </div>

                    <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-2">Size</p>
                    <div class="inline-flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-2">
                        @foreach($fontSizes as $s)
                        <button type="button" data-font-size="{{ $s['key'] }}" onclick="selectFontSize('{{ $s['key'] }}')"
                            class="font-size-chip {{ $themeFontSize === $s['key'] ? 'active-font-size-chip' : '' }}">{{ $s['label'] }}</button>
                        @endforeach
                    </div>
                    <p class="text-[10px] text-slate-400 mb-3">{{ $fontSizes[array_search($themeFontSize, array_column($fontSizes, 'key'))]['hint'] ?? '' }}</p>

                    <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
                        <p class="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-2">Preview</p>
                        <div id="font-preview" style="font-family: '{{ $themeFontFamily }}', sans-serif; zoom: {{ $fontSizes[array_search($themeFontSize, array_column($fontSizes, 'key'))]['zoom'] ?? 1 }};">
                            <h3 class="text-lg font-black text-slate-900">KPI Score — 82.4%</h3>
                            <p class="text-xs text-slate-500 mt-1">This is how body text and numbers will look across every page.</p>
                            <button type="button" class="mt-2 text-xs font-bold px-3 py-1.5 rounded-lg bg-[#1a3d34] text-white">+ Create KPI</button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-4">
                    <button type="button" onclick="saveTheme('font-save-btn', 'font-save-msg')" id="font-save-btn" class="text-[11px] font-black px-4 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition">
                        Save Font
                    </button>
                    <span id="font-save-msg" class="text-[11px] font-semibold ml-1"></span>
                </div>
            </div>

            {{-- LIVE PAGE PREVIEW --}}
            <div class="mt-5 pt-5 border-t border-slate-100">
                <h2 class="text-[13px] font-black text-slate-900 mb-0.5">Preview any page live</h2>
                <p class="text-[11px] text-slate-500 mb-3 max-w-lg">Opens the real page in a new tab, already wearing your saved theme — not a mockup. Save your changes above first.</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach([
                        ['label' => 'Dashboard',           'href' => route('dashboard')],
                        ['label' => 'KPI List',            'href' => route('kpi.index')],
                        ['label' => 'Job Description',     'href' => route('job-description')],
                        ['label' => 'Performance Report',   'href' => '/performance/report/q1'],
                        ['label' => 'Attendance',          'href' => route('attendance.index')],
                        ['label' => 'Titan KPI',           'href' => route('kpi.my-department-kpi')],
                        ['label' => 'Approval',            'href' => route('approval.index')],
                        ['label' => 'Notifications',        'href' => route('notifications')],
                        ['label' => 'Profile',             'href' => route('profile')],
                    ] as $pv)
                    <a href="{{ $pv['href'] }}" target="_blank" rel="noopener" class="text-[11px] font-bold px-3 py-2 rounded-xl border border-slate-200 text-slate-600 hover:border-[#6B9080] hover:text-[#1a3d34] transition">{{ $pv['label'] }} ↗</a>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

</div>
</main>

<script>
    // ── Settings tabs ─────────────────────────────────────────────────────────
    function selectSettingsTab(key) {
        document.querySelectorAll('.settings-panel').forEach(p => p.classList.add('hidden'));
        const panel = document.getElementById('tab-' + key);
        if (panel) panel.classList.remove('hidden');

        document.querySelectorAll('.settings-nav-btn[data-tab]').forEach(b => b.classList.toggle('active-tab', b.dataset.tab === key));

        localStorage.setItem('settingsActiveTab', key);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const saved = localStorage.getItem('settingsActiveTab');
        const valid = ['profile', 'telegram', 'email', 'password', 'appearance'];
        selectSettingsTab(valid.includes(saved) ? saved : 'profile');
        filterPalettes('All');
        selectThemeGroup(localStorage.getItem('themeGroupTab') === 'main' ? 'main' : 'sidebar');
    });

    // ── Sidebar vs Dashboard & Pages group switcher ─────────────────────────
    function selectThemeGroup(group) {
        document.getElementById('theme-group-sidebar').classList.toggle('hidden', group !== 'sidebar');
        document.getElementById('theme-group-main').classList.toggle('hidden', group !== 'main');
        document.querySelectorAll('.theme-group-tab').forEach(b => b.classList.toggle('active-group-tab', b.dataset.groupTab === group));
        localStorage.setItem('themeGroupTab', group);
    }

    // ── Appearance theme ─────────────────────────────────────────────────────
    // Two independent groups: "main" (Background/Card/Border/Accent — applies
    // to body + .soft-card/.doc-card site-wide) and "sidebar" (Background/
    // Accent/Text — applies only inside #sidebar). Each has its own palette
    // list, its own swatches, and both feed the same live preview below.
    const DEFAULT_MAIN_THEME    = { bg: '#F5F5F3', card: '#FFFFFF', border: '#6B9080', accent: '#D4AF37', text: '#0F172A', accent2: '#6B9080' };
    const DEFAULT_SIDEBAR_THEME = { bg: '#111111', accent: '#D4AF37', text: '#FFFFFF' };
    const PALETTES         = {!! json_encode($palettes) !!};
    const SIDEBAR_PALETTES = {!! json_encode($sidebarPalettes) !!};

    let _mainTheme = {
        bg:     '{{ $themeBg }}',
        card:   '{{ $themeCard }}',
        border: '{{ $themeBorder }}',
        accent: '{{ $themeAccent }}',
        text:   '{{ $themeText }}',
        accent2: '{{ $themeAccent2 }}',
    };
    let _sidebarTheme = {
        bg:     '{{ $sidebarBg }}',
        accent: '{{ $sidebarAccent }}',
        text:   '{{ $sidebarText }}',
    };
    const DEFAULT_FONT_THEME = { family: 'Inter', size: 'md' };
    const FONT_ZOOM = {!! json_encode(array_column($fontSizes, 'zoom', 'key')) !!};
    let _fontTheme = {
        family: '{{ $themeFontFamily }}',
        size:   '{{ $themeFontSize }}',
    };

    function applyFontPreview() {
        const preview = document.getElementById('font-preview');
        if (preview) {
            preview.style.fontFamily = "'" + _fontTheme.family + "', sans-serif";
            preview.style.zoom = FONT_ZOOM[_fontTheme.size] ?? 1;
        }
    }

    function selectFontFamily(family) {
        _fontTheme.family = family;
        document.querySelectorAll('.font-chip').forEach(b => b.classList.toggle('active-font-chip', b.dataset.fontFamily === family));
        applyFontPreview();
    }

    function selectFontSize(size) {
        _fontTheme.size = size;
        document.querySelectorAll('.font-size-chip').forEach(b => b.classList.toggle('active-font-size-chip', b.dataset.fontSize === size));
        applyFontPreview();
    }

    // Same glossy-orb formula as the server-rendered swatches, so a live pick
    // never looks flatter than the initial page load.
    function swatchGradient(hex) {
        return 'linear-gradient(135deg, color-mix(in srgb, ' + hex + ' 85%, white) 0%, ' + hex + ' 55%, color-mix(in srgb, ' + hex + ' 75%, black) 100%)';
    }

    function onThemeColorChange(group, key, value) {
        const theme  = group === 'sidebar' ? _sidebarTheme : _mainTheme;
        const prefix = group === 'sidebar' ? 'theme-sidebar-' : 'theme-';
        theme[key] = value;
        document.getElementById(prefix + key + '-hex').textContent = value.toUpperCase();
        document.getElementById(prefix + key).parentElement.style.background = swatchGradient(value);
        applyPreview();
    }

    function applyPalette(index) {
        const p = PALETTES[index];
        // Palettes don't define a text colour — merge so any custom Text stays put.
        _mainTheme = { ..._mainTheme, bg: p.bg, card: p.card, border: p.border, accent: p.accent };
        ['bg', 'card', 'border', 'accent'].forEach(key => {
            document.getElementById('theme-' + key).value = _mainTheme[key];
            document.getElementById('theme-' + key).parentElement.style.background = swatchGradient(_mainTheme[key]);
            document.getElementById('theme-' + key + '-hex').textContent = _mainTheme[key].toUpperCase();
        });
        applyPreview();
    }

    function applySidebarPalette(index) {
        const p = SIDEBAR_PALETTES[index];
        _sidebarTheme = { bg: p.bg, accent: p.accent, text: p.text };
        Object.keys(_sidebarTheme).forEach(key => {
            document.getElementById('theme-sidebar-' + key).value = _sidebarTheme[key];
            document.getElementById('theme-sidebar-' + key).parentElement.style.background = swatchGradient(_sidebarTheme[key]);
            document.getElementById('theme-sidebar-' + key + '-hex').textContent = _sidebarTheme[key].toUpperCase();
        });
        applyPreview();
    }

    function applyPreview() {
        // Dashboard & Pages group
        if (document.getElementById('tv-main-body')) document.getElementById('tv-main-body').style.background = _mainTheme.bg;
        document.querySelectorAll('.tv-main-card-bg').forEach(el => el.style.background = _mainTheme.card);
        document.querySelectorAll('.tv-main-card-border').forEach(el => el.style.borderColor = _mainTheme.border);
        document.querySelectorAll('.tv-main-border-left').forEach(el => el.style.borderLeftColor = _mainTheme.border);

        const greet = document.getElementById('tv-greet');
        if (greet) greet.style.background = 'linear-gradient(135deg, ' + _mainTheme.accent + ', color-mix(in srgb, ' + _mainTheme.accent + ' 35%, black))';
        const greetBar = document.getElementById('tv-greet-bar');
        if (greetBar) greetBar.style.background = 'linear-gradient(90deg, ' + _mainTheme.accent + ', ' + _mainTheme.accent + ', transparent)';
        const greetName = document.getElementById('tv-greet-name');
        if (greetName) greetName.style.color = _mainTheme.text;
        const greetBtn1 = document.getElementById('tv-greet-btn1');
        if (greetBtn1) greetBtn1.style.color = 'color-mix(in srgb, ' + _mainTheme.accent + ' 70%, black)';
        const greetBtn2 = document.getElementById('tv-greet-btn2');
        if (greetBtn2) greetBtn2.style.background = _mainTheme.accent;
        const perfScore = document.getElementById('tv-perf-score');
        if (perfScore) perfScore.style.background = 'color-mix(in srgb, ' + _mainTheme.accent2 + ' 18%, white)';
        const linkagesHead = document.getElementById('tv-linkages-head');
        if (linkagesHead) linkagesHead.style.background = 'linear-gradient(90deg, ' + _mainTheme.accent + ', color-mix(in srgb, ' + _mainTheme.accent + ' 35%, black))';
        const doc = document.getElementById('tv-doc');
        if (doc) doc.style.boxShadow = '0 10px 24px color-mix(in srgb, ' + _mainTheme.accent + ' 22%, transparent)';
        const docBar = document.getElementById('tv-doc-bar');
        if (docBar) { docBar.style.background = 'linear-gradient(135deg, ' + _mainTheme.accent + ', color-mix(in srgb, ' + _mainTheme.accent + ' 35%, black))'; docBar.style.color = _mainTheme.text; }

        // Text — card headings on light backgrounds AND the white text on the dark banners.
        // tv-perf-who/sub are excluded: My Performance card is a light pastel fill now
        // (see perfScore above), so its text stays a fixed dark slate, not the Text swatch.
        const textTargets = ['tv-greet-prefix', 'tv-linkages-title', 'tv-bar-row-text'];
        textTargets.forEach(id => { const el = document.getElementById(id); if (el) el.style.color = _mainTheme.text; });
        const mutedTargets = ['tv-linkages-sub'];
        mutedTargets.forEach(id => { const el = document.getElementById(id); if (el) el.style.color = 'color-mix(in srgb, ' + _mainTheme.text + ' 65%, transparent)'; });

        // Sidebar group
        const side = document.getElementById('tv-side');
        if (side) side.style.background = _sidebarTheme.bg;
        const brandTile = document.getElementById('tv-brand-tile');
        if (brandTile) { brandTile.style.background = _sidebarTheme.bg; brandTile.style.borderColor = _sidebarTheme.accent; }
        const sideBar = document.getElementById('tv-side-accent-bar');
        if (sideBar) sideBar.style.background = _sidebarTheme.accent;
        const eyebrow = document.getElementById('tv-side-eyebrow');
        if (eyebrow) eyebrow.style.color = _sidebarTheme.accent;
        const line = document.getElementById('tv-side-line');
        if (line) line.style.background = 'linear-gradient(90deg, ' + _sidebarTheme.accent + ', transparent)';
        const activeItem = document.getElementById('tv-side-active-item');
        if (activeItem) {
            activeItem.style.background = 'linear-gradient(135deg, ' + _sidebarTheme.accent + ', color-mix(in srgb, ' + _sidebarTheme.accent + ' 35%, black))';
            activeItem.style.borderLeftColor = _sidebarTheme.accent;
        }
        document.querySelectorAll('.tv-side-text').forEach(el => el.style.color = _sidebarTheme.text);
    }

    // ── Trending palette category filter (Dashboard & Pages group only) ────
    function filterPalettes(cat) {
        document.querySelectorAll('.palette-chip[data-group="main"]').forEach(btn => {
            btn.classList.toggle('hidden', cat !== 'All' && btn.dataset.category !== cat);
        });
        document.querySelectorAll('.palette-cat-btn').forEach(b => {
            b.classList.toggle('active-tab', b.dataset.cat === cat);
        });
    }

    async function saveTheme(btnId = 'theme-save-btn', msgId = 'theme-save-msg') {
        const btn = document.getElementById(btnId);
        const msg = document.getElementById(msgId);
        btn.disabled = true;
        try {
            const res = await fetch('{{ route("settings.theme.update") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TG_CSRF },
                body: JSON.stringify({
                    theme_bg: _mainTheme.bg, theme_card: _mainTheme.card,
                    theme_border: _mainTheme.border, theme_accent: _mainTheme.accent,
                    theme_text: _mainTheme.text, theme_accent2: _mainTheme.accent2,
                    theme_sidebar_bg: _sidebarTheme.bg, theme_sidebar_accent: _sidebarTheme.accent,
                    theme_sidebar_text: _sidebarTheme.text,
                    theme_font_family: _fontTheme.family, theme_font_size: _fontTheme.size,
                }),
            });
            const data = await res.json();
            if (data.success) {
                msg.textContent = 'Saved ✓ Applying…';
                msg.className = 'text-[11px] font-semibold ml-1 text-emerald-600';
                setTimeout(() => location.reload(), 800);
            } else {
                msg.textContent = data.message || 'Could not save.';
                msg.className = 'text-[11px] font-semibold ml-1 text-red-600';
            }
        } catch (e) {
            msg.textContent = 'Network error.';
            msg.className = 'text-[11px] font-semibold ml-1 text-red-600';
        } finally {
            btn.disabled = false;
        }
    }

    async function resetTheme() {
        _mainTheme = { ...DEFAULT_MAIN_THEME };
        _sidebarTheme = { ...DEFAULT_SIDEBAR_THEME };
        Object.keys(_mainTheme).forEach(key => {
            document.getElementById('theme-' + key).value = _mainTheme[key];
            document.getElementById('theme-' + key).parentElement.style.background = swatchGradient(_mainTheme[key]);
            document.getElementById('theme-' + key + '-hex').textContent = _mainTheme[key];
        });
        Object.keys(_sidebarTheme).forEach(key => {
            document.getElementById('theme-sidebar-' + key).value = _sidebarTheme[key];
            document.getElementById('theme-sidebar-' + key).parentElement.style.background = swatchGradient(_sidebarTheme[key]);
            document.getElementById('theme-sidebar-' + key + '-hex').textContent = _sidebarTheme[key];
        });
        applyPreview();
        await saveThemeValues({
            theme_bg: null, theme_card: null, theme_border: null, theme_accent: null, theme_text: null, theme_accent2: null,
            theme_sidebar_bg: null, theme_sidebar_accent: null, theme_sidebar_text: null,
        });
    }

    async function saveThemeValues(payload) {
        const msg = document.getElementById('theme-save-msg');
        try {
            const res = await fetch('{{ route("settings.theme.update") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TG_CSRF },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) {
                msg.textContent = 'Reset ✓';
                msg.className = 'text-[11px] font-semibold ml-1 text-emerald-600';
                setTimeout(() => location.reload(), 600);
            } else {
                msg.textContent = data.message || 'Could not reset.';
                msg.className = 'text-[11px] font-semibold ml-1 text-red-600';
            }
        } catch (e) {
            msg.textContent = 'Network error.';
            msg.className = 'text-[11px] font-semibold ml-1 text-red-600';
        }
    }

    const TG_CSRF = '{{ csrf_token() }}';
    let tgPollTimer = null;

    async function refreshTelegramStatus() {
        try {
            const res = await fetch('{{ route("settings.telegram.status") }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();

            const statusText = document.getElementById('tg-status-text');
            const connectBtn = document.getElementById('tg-connect-btn');

            if (data.linked) {
                statusText.textContent = 'Connected' + (data.username ? ' as @' + data.username : '');
                statusText.className = 'text-[11px] text-emerald-600 font-semibold mt-0.5';
                connectBtn.textContent = 'Reconnect';
                if (tgPollTimer) { clearInterval(tgPollTimer); tgPollTimer = null; }
            } else {
                statusText.textContent = 'Not connected — link your Telegram to get daily KPI reminders.';
                statusText.className = 'text-[11px] text-slate-500 mt-0.5';
                connectBtn.textContent = 'Connect Telegram';
            }
        } catch (e) {
            // silent — leave "Checking status…" as-is on transient failure
        }
    }

    async function connectTelegram() {
        const res = await fetch('{{ route("settings.telegram.connect") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': TG_CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();

        window.open(data.deep_link, '_blank');

        document.getElementById('tg-status-text').textContent = 'Waiting for confirmation in Telegram…';

        let attempts = 0;
        if (tgPollTimer) clearInterval(tgPollTimer);
        tgPollTimer = setInterval(async () => {
            attempts++;
            await refreshTelegramStatus();
            if (attempts >= 40) { clearInterval(tgPollTimer); tgPollTimer = null; } // ~2 min at 3s
        }, 3000);
    }

    document.addEventListener('DOMContentLoaded', refreshTelegramStatus);
</script>

</body>
</html>
