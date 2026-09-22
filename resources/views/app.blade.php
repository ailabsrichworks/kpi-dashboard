<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'RGHB KPI') }}</title>

    {{-- The following theme <style> blocks are HOISTED verbatim from
         partials/sidebar.blade.php (lines 16-473 as of the React AppLayout
         migration) so every Inertia/React page gets identical theming to
         still-Blade pages. partials/sidebar.blade.php keeps its own copy —
         Blade pages still include it directly and need it until every page
         is converted. Both copies are deleted together once that's done. --}}
    <style>
        /* Remove text-selection cursor from all non-interactive elements */
        * { cursor: default; }
        a, button, [role="button"], label, select,
        .cursor-pointer, [onclick],
        input[type="submit"], input[type="button"],
        input[type="reset"], input[type="checkbox"],
        input[type="radio"] { cursor: pointer !important; }
        input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]),
        textarea { cursor: text !important; }

        #sidebar, #sidebar * {
            font-family: 'Inter', sans-serif;
        }

        #sidebar.collapsed .sidebar-tooltip {
            display: block;
        }

        #sidebar:not(.collapsed) .sidebar-tooltip {
            display: none;
        }

        #sidebar.collapsed nav {
            overflow: visible;
        }

        .custom-scroll::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.18);
            border-radius: 999px;
        }

        .sticky.top-0.z-30.pt-4 {
            padding-top: 4px !important;
            padding-bottom: 4px !important;
        }
        .theme-header-banner.theme-page-banner {
            padding: 8px 4px !important;
            min-height: 0 !important;
            height: auto !important;
            box-sizing: border-box !important;
            overflow: visible !important;
            display: flex !important;
            align-items: center !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        .theme-page-banner[class*="text-white"],
        .theme-page-banner [class*="text-white"] { color: #1e293b !important; }
        .theme-page-banner .theme-header-hairline { display: none !important; }
        .theme-page-banner [class*="bg-white/"] {
            background-color: rgba(15, 23, 42, .05) !important;
            border: 1px solid rgba(15, 23, 42, .08) !important;
            color: #1e293b !important;
        }
        .theme-page-banner .bg-white { border: 1px solid #E5E7EB !important; }
        .theme-page-banner [class*="pointer-events-none"] { display: none !important; }

        .theme-soft-btn {
            background: color-mix(in srgb, #D4AF37 15%, white);
            color: color-mix(in srgb, #D4AF37 70%, black);
            border: 1px solid color-mix(in srgb, #D4AF37 25%, white);
        }
        .theme-soft-btn:hover { background: color-mix(in srgb, #D4AF37 25%, white); }

        .theme-perf-card { background: color-mix(in srgb, #6B9080 18%, white); }
        .theme-perf-accent-text { color: color-mix(in srgb, #6B9080 75%, black); }
        .theme-perf-divider { border-color: rgba(15, 23, 42, .08); }
        .theme-perf-btn2 { background: rgba(15, 23, 42, .05); color: #1a3d34; }
        .theme-perf-btn2:hover { background: rgba(15, 23, 42, .09); }

        #mainContent { padding-top: 56px; }

        [class*="border-t-[#D4AF37]"] { border-color: #D4AF37 !important; }

        .step-accent-grad { background: linear-gradient(135deg, #D4AF37, #000000); }

        .step-panel-box {
            border: 1px solid #6B9080;
            background: color-mix(in srgb, #6B9080 15%, white);
        }

        .assign-select-accent {
            border: 1px solid #D4AF37;
        }
        .assign-select-accent:focus {
            border-color: #D4AF37;
            --tw-ring-color: color-mix(in srgb, #D4AF37 40%, transparent);
        }

        .assign-accent-text { color: #D4AF37; }
        .assign-accent-badge {
            background: color-mix(in srgb, #D4AF37 18%, white);
            color: color-mix(in srgb, #D4AF37 70%, black);
        }
        .assign-accent-card {
            border-left-color: #D4AF37;
            border-color: color-mix(in srgb, #D4AF37 25%, white);
        }
        .assign-accent-card:hover { background: color-mix(in srgb, #D4AF37 6%, white); }

        .accent-border { border-color: #D4AF37; }

        .accent2-fill { background: #6B9080; }

        .accent-value-text { color: color-mix(in srgb, #6B9080 45%, black); }

        .eyebrow-accent-text { color: #D4AF37; }
    </style>

    {{-- Unconditional (not gated on session('theme_*') existing): this
         block's own values are only the fallback used for the very first,
         pre-hydration paint. Layouts/AppLayout.tsx sets these same CSS
         vars fresh, from Inertia's own `layout` props, on every render --
         including a client-side SPA navigation that never re-renders this
         <head> at all. The rules below have to exist in the document
         unconditionally for that to have anything to attach to; gating
         them on the session state of whichever request happened to
         produce the very first full-document load (typically /login or
         /choose-dashboard, before an employee/theme is even selected) is
         what caused the sidebar and dashboard theme to silently never
         apply for an entire session after login. --}}
    <style>
        :root {
            --user-theme-bg:     {{ session('theme_bg')     ?: '#F5F5F3' }};
            --user-theme-card:   {{ session('theme_card')   ?: '#FFFFFF' }};
            --user-theme-border: {{ session('theme_border') ?: '#6B9080' }};
            --user-theme-accent: {{ session('theme_accent') ?: '#D4AF37' }};
            --user-theme-accent2:{{ session('theme_accent2') ?: '#6B9080' }};
            --user-theme-text:   {{ session('theme_text')   ?: '#0F172A' }};
        }
        body { background-color: var(--user-theme-bg) !important; }
        #mainContent { background-color: var(--user-theme-bg) !important; }
        .theme-soft-btn {
            background: color-mix(in srgb, var(--user-theme-accent) 15%, white) !important;
            color: color-mix(in srgb, var(--user-theme-accent) 70%, black) !important;
            border-color: color-mix(in srgb, var(--user-theme-accent) 25%, white) !important;
        }
        .theme-soft-btn:hover { background: color-mix(in srgb, var(--user-theme-accent) 25%, white) !important; }
        .theme-perf-card { background: color-mix(in srgb, var(--user-theme-accent2) 18%, white) !important; }
        .theme-perf-accent-text { color: color-mix(in srgb, var(--user-theme-accent2) 70%, black) !important; }
        .theme-perf-btn2 { background: color-mix(in srgb, var(--user-theme-accent2) 26%, black) !important; }
        .theme-perf-btn2:hover { background: color-mix(in srgb, var(--user-theme-accent2) 36%, black) !important; }
        .soft-card, .doc-card {
            background-color: var(--user-theme-card) !important;
            border-color: var(--user-theme-border) !important;
            box-shadow: none !important;
        }
        .doc-bar {
            background: linear-gradient(135deg, color-mix(in srgb, var(--user-theme-accent) 85%, white), var(--user-theme-accent) 45%, color-mix(in srgb, var(--user-theme-accent) 60%, black)) !important;
            color: var(--user-theme-text) !important;
        }
        .soft-card h1, .soft-card h2, .soft-card h3, .soft-card h4,
        .doc-card h1, .doc-card h2, .doc-card h3, .doc-card h4 {
            color: var(--user-theme-text) !important;
        }
        .theme-header-text { color: var(--user-theme-text) !important; }
        .theme-header-text-muted { color: color-mix(in srgb, var(--user-theme-text) 65%, transparent) !important; }
        .theme-header-banner {
            background: linear-gradient(135deg, color-mix(in srgb, var(--user-theme-accent) 85%, white), var(--user-theme-accent) 45%, color-mix(in srgb, var(--user-theme-accent) 60%, black)) !important;
        }
        .theme-header-hairline {
            background: linear-gradient(90deg, var(--user-theme-accent), var(--user-theme-accent), transparent) !important;
        }
        .theme-header-accent-btn { background-color: var(--user-theme-accent) !important; border-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }
        .theme-header-dark-text { color: color-mix(in srgb, var(--user-theme-accent) 70%, black) !important; }
        .theme-header-accent-ring { --tw-ring-color: var(--user-theme-accent) !important; }

        [class*="border-[#E5E7EB]"],
        [class*="border-[#6B9080]"],
        [class*="border-[#D9C4A0]"],
        [class*="border-[#E3D2B0]"] { border-color: var(--user-theme-border) !important; }
        [class*="bg-[#6B9080]"]     { background-color: var(--user-theme-border) !important; }
        [class*="focus:border-[#6B9080]"]:focus { border-color: var(--user-theme-border) !important; }

        [class*="text-[#D4AF37]"],
        [class*="text-[#1a3d34]"]  { color: var(--user-theme-accent) !important; }
        [class*="bg-[#D4AF37]"],
        [class*="bg-[#1a3d34]"],
        [class*="bg-[#6B3F2A]"]    { background-color: var(--user-theme-accent) !important; }
        [class*="bg-[#D4AF37]/10"] { background-color: color-mix(in srgb, var(--user-theme-accent) 12%, transparent) !important; }
        [class*="bg-[#D4AF37]/5"]  { background-color: color-mix(in srgb, var(--user-theme-accent) 6%, transparent) !important; }
        [class*="text-[#B8860B]"]  { color: color-mix(in srgb, var(--user-theme-accent) 80%, black) !important; }

        #topbar { background: linear-gradient(135deg, var(--user-theme-accent), color-mix(in srgb, var(--user-theme-accent) 65%, black)) !important; }
        [class*="bg-[#F5EEDC]"] { background-color: var(--user-theme-card) !important; }
        .nav-btn.active { color: color-mix(in srgb, var(--user-theme-accent) 75%, black) !important; }

        [class*="border-t-[#D4AF37]"] { border-color: var(--user-theme-accent) !important; }
        [class*="border-l-[#D4AF37]"] { border-left-color: var(--user-theme-accent) !important; }
        .step-accent-grad { background: linear-gradient(135deg, var(--user-theme-accent), #000000) !important; }
        .step-panel-box {
            border: 1px solid var(--user-theme-accent2) !important;
            background: color-mix(in srgb, var(--user-theme-accent2) 15%, white) !important;
        }
        .assign-select-accent {
            border: 1px solid var(--user-theme-accent) !important;
        }
        .assign-select-accent:focus {
            border-color: var(--user-theme-accent) !important;
            --tw-ring-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important;
        }
        .assign-accent-text { color: var(--user-theme-accent) !important; }
        .assign-accent-badge {
            background: color-mix(in srgb, var(--user-theme-accent) 18%, white) !important;
            color: color-mix(in srgb, var(--user-theme-accent) 70%, black) !important;
        }
        .assign-accent-card {
            border-left-color: var(--user-theme-accent) !important;
            border-color: color-mix(in srgb, var(--user-theme-accent) 25%, white) !important;
        }
        .assign-accent-card:hover { background: color-mix(in srgb, var(--user-theme-accent) 6%, white) !important; }
        .accent-border { border-color: var(--user-theme-accent) !important; }
        .accent2-fill { background: var(--user-theme-accent2) !important; }
        .accent-value-text { color: color-mix(in srgb, var(--user-theme-accent2) 45%, black) !important; }
        .eyebrow-accent-text { color: var(--user-theme-accent) !important; }
        [class*="border-[#D4AF37]"],
        [class*="border-[#1a3d34]"] { border-color: var(--user-theme-accent) !important; }
        [class*="ring-[#D4AF37]"]  { --tw-ring-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }
        [class*="hover:bg-[#c19c2f]"]:hover { background-color: color-mix(in srgb, var(--user-theme-accent) 82%, black) !important; }

        [class*="text-[#7A0019]"],
        [class*="text-[#6B3F2A]"],
        [class*="text-[#8B5E4A]"] { color: color-mix(in srgb, var(--user-theme-accent) 70%, black) !important; }
        [class*="border-[#6B3F2A]"] { border-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }
        [class*="ring-[#6B3F2A]"]   { --tw-ring-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }

        [class*="text-[#A4C3B2]"] { color: color-mix(in srgb, var(--user-theme-text) 65%, transparent) !important; }

        [class*="bg-[#CCE3DE]"] { background-color: color-mix(in srgb, var(--user-theme-accent) 18%, white) !important; }
        [class*="bg-[#FBF5EF]"] { background-color: color-mix(in srgb, var(--user-theme-accent) 8%, white) !important; }
        [class*="bg-[#F5EAE0]"] { background-color: color-mix(in srgb, var(--user-theme-accent) 15%, white) !important; }

        [class*="bg-[#EFE3C7]"] { background-color: color-mix(in srgb, var(--user-theme-accent2) 35%, white) !important; }

        input:focus, textarea:focus, select:focus {
            border-color: var(--user-theme-accent) !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--user-theme-accent) 12%, transparent) !important;
        }

        [class*="hover:bg-[#c19c2f]"]:hover,
        [class*="hover:bg-[#2d5548]"]:hover,
        [class*="hover:bg-[#5a7a6d]"]:hover,
        [class*="hover:bg-[#5a341f]"]:hover { background-color: color-mix(in srgb, var(--user-theme-accent) 85%, black) !important; }
        [class*="hover:text-[#2d5548]"]:hover,
        [class*="hover:text-[#1a3d34]"]:hover,
        [class*="hover:text-[#6B9080]"]:hover,
        [class*="hover:text-[#B8860B]"]:hover { color: var(--user-theme-accent) !important; }
        [class*="hover:text-[#5a3323]"]:hover,
        [class*="hover:text-[#7A0019]"]:hover { color: color-mix(in srgb, var(--user-theme-accent) 70%, black) !important; }
        [class*="hover:border-[#6B9080]"]:hover,
        [class*="hover:border-[#1a3d34]"]:hover,
        [class*="hover:border-[#8B5E4A]"]:hover { border-color: var(--user-theme-accent) !important; }
        [class*="hover:bg-[#FBF5EF]"]:hover { background-color: color-mix(in srgb, var(--user-theme-accent) 8%, white) !important; }
        [class*="focus:border-[#6B9080]"]:focus,
        [class*="focus:border-[#D4AF37]"]:focus,
        [class*="focus:border-[#6B3F2A]"]:focus { border-color: var(--user-theme-accent) !important; }
        [class*="focus:ring-[#6B9080]"]:focus,
        [class*="focus:ring-[#D4AF37]"]:focus,
        [class*="focus:ring-[#6B3F2A]"]:focus { --tw-ring-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }
    </style>

    @if(session('theme_font_family') || session('theme_font_size'))
    @php
        $fontFamily = session('theme_font_family') ?: 'Inter';
        $fontZoom   = ['sm' => 0.9, 'md' => 1, 'lg' => 1.15][session('theme_font_size') ?: 'md'];
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $fontFamily) }}:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body, input, textarea, select, button {
            font-family: '{{ $fontFamily }}', sans-serif !important;
        }
        #sidebar, #sidebar * { font-family: '{{ $fontFamily }}', sans-serif !important; }
        {{-- #sidebar is deliberately excluded from zoom, unlike #mainContent.
             #sidebar is `position: fixed; height: 100vh` (h-screen) — its 100vh
             height is computed first and zoom then scales the already-computed
             box, so it renders taller/shorter than the real viewport and can
             leave the nav list clipped and unscrollable (confirmed cause of a
             reported "sidebar cut off, can't reach Logout" bug). Keeping this
             file in sync with resources/views/partials/sidebar.blade.php's own
             fix so the sidebar is the same size on every page regardless of
             whether it renders through the legacy Blade partial or here (the
             Inertia/React root view) — only the font-family rule above still
             applies to the sidebar; the size scaling is skipped here too. --}}
        @if($fontZoom != 1)
        #mainContent { zoom: {{ $fontZoom }}; }
        @endif
    </style>
    @endif

    @php
        $sidebarBg     = session('theme_sidebar_bg');
        $sidebarAccent = session('theme_sidebar_accent') ?: session('theme_accent');
        $sidebarText   = session('theme_sidebar_text');
    @endphp
    @if($sidebarBg || $sidebarAccent || $sidebarText)
    <style>
        :root {
            --sidebar-bg:     {{ $sidebarBg     ?: '#111111' }};
            --sidebar-accent: {{ $sidebarAccent ?: '#D4AF37' }};
            --sidebar-text:   {{ $sidebarText   ?: '#FFFFFF' }};
        }
        #sidebar { background-color: var(--sidebar-bg) !important; }
        #sidebar .sidebar-brand-tile { background-color: var(--sidebar-bg) !important; border-color: var(--sidebar-accent) !important; }
        #sidebar .sidebar-fade { background: linear-gradient(to top, var(--sidebar-bg), transparent) !important; }
        #sidebar .sidebar-accent-text { color: var(--sidebar-accent) !important; }
        #sidebar .sidebar-accent-bar  { background: linear-gradient(90deg, var(--sidebar-accent), var(--sidebar-accent), transparent) !important; }
        #sidebar .sidebar-accent-line { background: linear-gradient(90deg, var(--sidebar-accent), transparent) !important; }
        #sidebar .sidebar-active-item {
            background: linear-gradient(135deg, var(--sidebar-accent), color-mix(in srgb, var(--sidebar-accent) 35%, black)) !important;
            border-left-color: var(--sidebar-accent) !important;
        }
        #sidebar nav a:not(.sidebar-active-item),
        #sidebar .sidebar-system a:not(.sidebar-active-item) {
            color: var(--sidebar-text) !important;
            opacity: .85;
        }
        #sidebar nav a:not(.sidebar-active-item):hover,
        #sidebar .sidebar-system a:not(.sidebar-active-item):hover {
            opacity: 1;
        }
    </style>
    @endif

    {{-- Unconditional base font for the Platform (multi-company) pages, which
         had no explicit font at all before this — just whatever sans-serif
         the visitor's browser/OS happened to default to. The legacy app's
         own per-tenant font customization above (session('theme_font_family'))
         still wins where it applies, via its own !important rules; this is
         only the base everyone gets before any of that. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
