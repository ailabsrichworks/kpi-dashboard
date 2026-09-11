@include('partials.ai-chat-widget')

@if(session('admin_impersonating'))
<div class="no-print" style="position:fixed;top:0;left:230px;right:0;z-index:9997;background:linear-gradient(90deg,#7c3aed,#a78bfa);color:#fff;padding:8px 24px;display:flex;align-items:center;justify-content:center;gap:12px;font-size:12px;font-weight:700;box-shadow:0 2px 12px rgba(124,58,237,.35);">
    <span>👁 Viewing as <strong>{{ session('full_name') ?? session('short_name') ?? session('employee_name') }}</strong> — BTS Admin session</span>
    <form method="POST" action="{{ route('admin.view-as.stop') }}" class="inline">
        @csrf
        <button type="submit" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);padding:4px 12px;border-radius:8px;font-size:11px;font-weight:800;cursor:pointer;">
            Return to my account
        </button>
    </form>
</div>
<div style="height:36px;"></div>
@endif

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

    /* .theme-header-banner is shared by several different things (a progress-bar
       fill, a small in-card strip, a side panel, thin divider stripes) that must
       NOT be shape-forced — only its background colour is meant to be shared
       everywhere. .theme-page-banner is a second, narrower class added only to
       actual full-width page-top banners, so shape rules only ever hit those.
       No card at all now — plain text/icons directly on the page background,
       like a school-management-app top bar. The compound selector (both
       classes) outranks the plain .theme-header-banner rule further down that
       still gives OTHER elements (side panel, in-card strips) their gradient,
       so this flat style always wins here. */
    /* Every page wraps its header banner in an identical
       `sticky top-0 z-30 ... pt-4 pb-2` div. That 16px+8px of padding was
       sized for the old dark-card banner; against the new flat banner (see
       below) it just reads as dead grey space under the fixed #topBar, so
       it's tightened here rather than in 16 separate page files. */
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
    /* Every bit of text inside used to assume a dark backdrop (text-white,
       text-white/70, etc.) — remap just those to a dark slate now that there's
       no dark fill behind it, without touching intentionally-coloured text
       (links, accent labels) elsewhere in the banner. */
    .theme-page-banner[class*="text-white"],
    .theme-page-banner [class*="text-white"] { color: #1e293b !important; }
    .theme-page-banner .theme-header-hairline { display: none !important; }
    /* Small stat chips/pills inside these banners (e.g. "Staff"/"Total KPI"
       boxes) were built as a translucent white tint meant to sit on a dark
       banner — invisible now there's no fill behind them at all. Give them a
       subtle dark tint instead so they still read as a distinct chip. A plain
       opaque white button (e.g. "+ Create KPI") gets a border for the same
       reason — no contrast against the page background without one. */
    .theme-page-banner [class*="bg-white/"] {
        background-color: rgba(15, 23, 42, .05) !important;
        border: 1px solid rgba(15, 23, 42, .08) !important;
        color: #1e293b !important;
    }
    .theme-page-banner .bg-white { border: 1px solid #E5E7EB !important; }
    .theme-page-banner [class*="pointer-events-none"] { display: none !important; }

    /* Secondary "+ Add/Create KPI" buttons — a soft pastel tint of Accent
       rather than a solid saturated fill, so it never clashes no matter which
       accent colour is picked (a bold solid fill can fight a bold accent). */
    .theme-soft-btn {
        background: color-mix(in srgb, #D4AF37 15%, white);
        color: color-mix(in srgb, #D4AF37 70%, black);
        border: 1px solid color-mix(in srgb, #D4AF37 25%, white);
    }
    .theme-soft-btn:hover { background: color-mix(in srgb, #D4AF37 25%, white); }

    /* "My Performance" score panel (Dashboard) — independent from the top
       banner's Accent gradient and usually kept pastel/soft, so it gets its
       own colour rather than inheriting the bold banner gradient. Defaults to
       a pastel tint of the Chart accent (2nd colour) rather than an unrelated
       cream, so the two visibly go together — still overridable below via
       its own swatch in Settings if picked explicitly. */
    .theme-perf-card { background: color-mix(in srgb, #6B9080 18%, white); }
    .theme-perf-accent-text { color: color-mix(in srgb, #6B9080 75%, black); }
    .theme-perf-divider { border-color: rgba(15, 23, 42, .08); }
    .theme-perf-btn2 { background: rgba(15, 23, 42, .05); color: #1a3d34; }
    .theme-perf-btn2:hover { background: rgba(15, 23, 42, .09); }

    /* Reserve space at the top of every page for the fixed #topBar (the
       search/notifications/profile row), so the page's own header banner
       renders directly beneath it instead of behind it. */
    #mainContent { padding-top: 56px; }

    /* Full-perimeter Accent border on "featured" cards (the ones already
       marked with a top accent stripe) instead of a gold top edge sitting on
       otherwise-grey sides — same default-gold look, just applied to every
       side so it reads as an outlined card rather than a stripe. Matches the
       guarded override below for users who do pick a custom Accent. */
    [class*="border-t-[#D4AF37]"] { border-color: #D4AF37 !important; }

    /* Numbered step badges/bars (Create KPI form) — each of the 6 steps used
       a DIFFERENT hardcoded gradient (maroon-gold, two browns, amber-red),
       some of which are the SAME colour pairs used elsewhere for unrelated
       semantic status (e.g. "Good" score = #8B5E4A→#6B3F2A on several other
       pages) — a substring sweep on those colours would have wrongly
       recoloured that too. A dedicated class on just these 12 elements
       avoids that collision, and gives one uniform Accent-to-black gradient
       across all 6 steps instead of 4 unrelated colour schemes. */
    .step-accent-grad { background: linear-gradient(135deg, #D4AF37, #000000); }

    /* Same 6-step form's inner panels ("boxes") — each was its own unrelated
       colour (blue, indigo, emerald, cyan, sky, amber, red, purple...)
       instead of following the theme. Uses Chart accent (2nd colour) rather
       than the main Accent, so it visually pairs with My Performance card
       elsewhere on the dashboard. */
    .step-panel-box {
        border: 1px solid #6B9080;
        background: color-mix(in srgb, #6B9080 15%, white);
    }

    /* "Assign To" select (Step 1) — main Accent, matching every other
       border on this page. */
    .assign-select-accent {
        border: 1px solid #D4AF37;
    }
    .assign-select-accent:focus {
        border-color: #D4AF37;
        --tw-ring-color: color-mix(in srgb, #D4AF37 40%, transparent);
    }

    /* "MY ASSIGNED KPI" panel (Step 1) — label, count badge and each card's
       left stripe/border/hover were a fixed indigo, unrelated to the theme. */
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

    /* KPI Summary sidebar (Create KPI) — Owner/Category/Sub Category/Base
       Target/Stretch Target/Current Status/Quarter Total boxes each had a
       different fixed colour (grey, brown, purple, amber). One accent border
       across all of them instead of 4 unrelated colour schemes. */
    .accent-border { border-color: #D4AF37; }

    /* KPI Summary sidebar checkmark badge — was a fixed dark-maroon gradient. */
    .accent2-fill { background: #6B9080; }

    /* KPI Summary sidebar value text (Owner/Title/Category/Sub Category/
       Base/Stretch/Quarter Total) — dedicated class instead of relying on
       the generic text-[#7A0019] substring sweep, which some of these
       values weren't visibly picking up. */
    .accent-value-text { color: color-mix(in srgb, #6B9080 45%, black); }

    /* Every step's small uppercase "eyebrow" label (EXECUTION OWNER, KPI
       CATEGORY, KPI SUB CATEGORY, KPI TITLE, KPI DESCRIPTION, KPI UNIT,
       TARGET SETTING, KPI STATUS, KPI REMARK) was a different unrelated
       colour (brown, emerald, cyan, indigo, sky, amber, red). One Accent
       colour across all of them instead of 7 unrelated colour schemes. */
    .eyebrow-accent-text { color: #D4AF37; }

</style>

@if(session('theme_bg') || session('theme_card') || session('theme_accent') || session('theme_accent2') || session('theme_border') || session('theme_text'))
{{-- Account Settings > Appearance — only rendered once the user has actually
     saved a custom theme, so nobody who hasn't touched this feature sees any
     visual change. Covers the page background, the shared card style
     (.soft-card / .doc-card), every top banner, and — via [class*="..."]
     substring selectors — every remaining hardcoded brand colour still
     hand-coded on individual pages: gold/maroon (Dashboard, KPI, Notifications,
     Profile, Help...), teal (Titan, Attendance, Performance Report), and
     brown (Approval, KPI linkage cascade). Never touches score/status
     colours (scoreStyle, sc-*, category pills, etc). --}}
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
    /* Page-top banners stay flat/transparent regardless of theme now — no
       card fill or border to recolour any more. */
    /* Document-style pages (Job Description, etc.) — the black title/section
       bars follow Accent just like the dashboard's dark banners, so changing
       the theme visibly changes these pages too, not just the dashboard.
       Mixes Accent with black/white (light highlight -> true colour -> dark
       shade), same recipe as the Settings colour-picker orbs — mixing with
       Text instead produced a muddy, off-brand tone whenever Text was a dark
       navy/near-black, since two unrelated hues were being blended together. */
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
    /* Every page's dark top banner (Dashboard greeting, KPI List, Job Description,
       Performance Report/KPI/Attitude, Approval, Notifications, Profile, Help,
       Attendance, Titan, SLT Dashboard, Activity Log, Weightage, My Department KPI)
       shares this class, so Accent moves all of them together — not just Dashboard.
       Same light-highlight -> true colour -> dark-shade recipe as the Settings
       colour-picker orbs (mixing Accent with black/white), not with Text —
       mixing two unrelated picked hues together read as a muddy, off tone. */
    .theme-header-banner {
        background: linear-gradient(135deg, color-mix(in srgb, var(--user-theme-accent) 85%, white), var(--user-theme-accent) 45%, color-mix(in srgb, var(--user-theme-accent) 60%, black)) !important;
    }
    .theme-header-hairline {
        background: linear-gradient(90deg, var(--user-theme-accent), var(--user-theme-accent), transparent) !important;
    }
    .theme-header-accent-btn { background-color: var(--user-theme-accent) !important; border-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }
    .theme-header-dark-text { color: color-mix(in srgb, var(--user-theme-accent) 70%, black) !important; }
    .theme-header-accent-ring { --tw-ring-color: var(--user-theme-accent) !important; }

    /* ---- Site-wide colour coordination sweep ----
       Every page still hand-codes its own accent hex rather than reading a
       shared variable, across three different legacy brand families:
       gold/maroon (Dashboard, KPI, Notifications, Profile, Help, SLT...),
       teal (Titan, Attendance, Performance Report), and brown (Approval, KPI
       linkage cascade). [class*="..."] substring-matches the literal
       Tailwind arbitrary-value class regardless of which utility it's
       attached to (bg-/text-/border-/ring-/hover:), so every button, border,
       field box, and small badge repaints with the rest of the page —
       nothing is left in its old colour. Score/status colours (scoreStyle,
       sc-*, category pills) are never touched. */

    /* Neutral card border -> Border */
    [class*="border-[#E5E7EB]"],
    [class*="border-[#6B9080]"],
    [class*="border-[#D9C4A0]"],
    [class*="border-[#E3D2B0]"] { border-color: var(--user-theme-border) !important; }
    /* class~= (exact token), not class*= (substring) -- a substring match on
       "bg-[#6B9080]" also matches "hover:bg-[#6B9080]/8" (e.g. performance/
       kpi.blade.php's quarter-toggle label), forcing that hover-only fill
       permanently onto the base state instead of just on :hover. */
    [class~="bg-[#6B9080]"]     { background-color: var(--user-theme-border) !important; }
    [class*="focus:border-[#6B9080]"]:focus { border-color: var(--user-theme-border) !important; }

    /* Primary brand pop (gold family + teal's dark equivalent) -> Accent */
    [class*="text-[#D4AF37]"],
    [class*="text-[#1a3d34]"]  { color: var(--user-theme-accent) !important; }
    /* Same class~= fix as above -- "bg-[#1a3d34]" as a substring also matched
       "hover:bg-[#1a3d34]" (Titan KPI Dashboard's Collapse All/Expand All
       buttons: border-2 border-[#1a3d34] text-[#1a3d34] hover:bg-[#1a3d34]
       hover:text-white), permanently filling them solid accent-gold with
       accent-gold text -- invisible text on a solid-colour button, even
       when not hovered. Also matched "file:bg-[#6B3F2A]" (kpi/index.blade.php's
       Proof Files upload button), overriding that input's own bg-white. */
    [class~="bg-[#D4AF37]"],
    [class~="bg-[#1a3d34]"],
    [class~="bg-[#6B3F2A]"]    { background-color: var(--user-theme-accent) !important; }
    /* Light-tint icon chips/badges (bg-[#D4AF37]/5, /10) — declared after the
       solid-fill rule above so these win the tie. Without this, the /5 and /10
       opacity suffixes were ignored and these went fully solid, and since the
       icon/text sitting on them (text-[#B8860B]) was never mapped to the
       theme, both ended up the same colour — the icon "sinking" into its own
       badge with zero contrast. */
    [class*="bg-[#D4AF37]/10"] { background-color: color-mix(in srgb, var(--user-theme-accent) 12%, transparent) !important; }
    [class*="bg-[#D4AF37]/5"]  { background-color: color-mix(in srgb, var(--user-theme-accent) 6%, transparent) !important; }
    [class*="text-[#B8860B]"]  { color: color-mix(in srgb, var(--user-theme-accent) 80%, black) !important; }

    /* Mini App (/mini-app) shell — #6B3F2A is also reused elsewhere as a fixed
       status/semantic colour (e.g. the "on_track"/"completed" dots), so it's
       deliberately NOT swept as a bare class here — that would recolour status
       meaning every time someone picks a new accent. #topbar is a specific,
       unique ID only this shell uses, so it's themed by ID instead. */
    #topbar { background: linear-gradient(135deg, var(--user-theme-accent), color-mix(in srgb, var(--user-theme-accent) 65%, black)) !important; }
    [class*="bg-[#F5EEDC]"] { background-color: var(--user-theme-card) !important; }
    .nav-btn.active { color: color-mix(in srgb, var(--user-theme-accent) 75%, black) !important; }

    /* Full-perimeter Accent border (not just the top edge) — a plainer, more
       geometric "3D" cue than a soft shadow: an outlined card reads as
       raised/defined without adding another visual layer on top of colour +
       spacing that's already changing per theme. */
    [class*="border-t-[#D4AF37]"] { border-color: var(--user-theme-accent) !important; }
    [class*="border-l-[#D4AF37]"] { border-left-color: var(--user-theme-accent) !important; }
    /* Numbered step badges/bars (Create KPI form) — Accent fading to black. */
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

    /* Dark text-on-white role (maroon/brown "heading" shades) -> darkened Accent */
    [class*="text-[#7A0019]"],
    [class*="text-[#6B3F2A]"],
    [class*="text-[#8B5E4A]"] { color: color-mix(in srgb, var(--user-theme-accent) 70%, black) !important; }
    [class*="border-[#6B3F2A]"] { border-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }
    [class*="ring-[#6B3F2A]"]   { --tw-ring-color: color-mix(in srgb, var(--user-theme-accent) 40%, transparent) !important; }

    /* Muted label sitting on a dark banner -> Text (matches theme-header-text-muted) */
    [class*="text-[#A4C3B2]"] { color: color-mix(in srgb, var(--user-theme-text) 65%, transparent) !important; }

    /* Light tint highlight boxes -> tinted Accent */
    [class*="bg-[#CCE3DE]"] { background-color: color-mix(in srgb, var(--user-theme-accent) 18%, white) !important; }
    /* class~=, not class*= -- see the bg-[#1a3d34]/bg-[#6B9080] note above;
       "hover:bg-[#FBF5EF]" (kpi/approval.blade.php, kpi/index.blade.php)
       was matching this too, applying the hover-only tint permanently
       instead of leaving it to the dedicated ":hover" rule below. */
    [class~="bg-[#FBF5EF]"] { background-color: color-mix(in srgb, var(--user-theme-accent) 8%, white) !important; }
    [class*="bg-[#F5EAE0]"] { background-color: color-mix(in srgb, var(--user-theme-accent) 15%, white) !important; }

    /* Progress-track backgrounds (Performix stat cards/task rows) -> tinted
       Accent 2, matching that swatch's existing "charts" role elsewhere. */
    [class*="bg-[#EFE3C7]"] { background-color: color-mix(in srgb, var(--user-theme-accent2) 35%, white) !important; }

    /* Every text/select/textarea field box, site-wide */
    input:focus, textarea:focus, select:focus {
        border-color: var(--user-theme-accent) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--user-theme-accent) 12%, transparent) !important;
    }

    /* Hover / focus shades of the above families, so buttons and links still
       give feedback on hover instead of going flat. */
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
@endif

@if(session('theme_font_family') || session('theme_font_size'))
{{-- Account Settings > Appearance > Font — independent of the colour theme
     above, since a font choice and a colour choice are unrelated settings and
     either one may be set without the other. Typeface applies to every page's
     text and form fields. Size uses CSS `zoom` rather than a root font-size,
     because most of this app's text is set in fixed pixels (text-[9px], etc.)
     rather than rem — a font-size override alone would silently miss almost
     everything, while zoom scales the whole rendered page proportionally. --}}
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
    @if($fontZoom != 1)
    #mainContent { zoom: {{ $fontZoom }}; }
    #sidebar { zoom: {{ $fontZoom }}; }
    @endif
</style>
@endif

@php
    // Independent sidebar theme — falls back to the legacy theme_accent
    // value so anyone who customised the old shared "Accent" swatch keeps
    // seeing it in the sidebar exactly as before, until they set a sidebar
    // theme of their own below (which then takes precedence).
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

{{-- Global top bar — shown on every authenticated page, fixed above the
     page's own header banner (which now sits directly beneath it, forming
     a two-row header like a typical admin dashboard: this slim utility row
     on top, the page's own greeting/title banner right below). Its `left`
     offset is kept in sync with the sidebar's collapsed/expanded width by
     setSidebarState() further down. Drops below the impersonation banner
     when BTS admin is viewing-as someone else, instead of being hidden
     behind it. --}}
<div
    id="topBar"
    style="position: fixed; top: {{ session('admin_impersonating') ? '36px' : '0' }}; left: 230px; right: 0; z-index: 45;"
    class="h-14 bg-white border-b border-slate-200 px-5 grid grid-cols-3 items-center gap-3 transition-all duration-300"
>
    <div class="leading-tight min-w-0 justify-self-start">
        <p id="topBarTitle" class="text-sm font-black text-slate-800 truncate">Dashboard</p>
        <p class="text-[10px] text-slate-400 truncate">
            {{ session('company_code') }} @if(session('department_code')) &middot; {{ session('department_code') }} @endif &middot; {{ now()->timezone('Asia/Kuala_Lumpur')->format('l, F j') }}
        </p>
    </div>

    <div class="w-full max-w-md justify-self-center">
        <div class="relative">
            <svg class="w-3.5 h-3.5 text-slate-300 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                id="topBarSearch"
                type="text"
                placeholder="Search KPIs..."
                onkeydown="if (event.key === 'Enter') { handleTopBarSearch(this.value); }"
                class="w-full bg-slate-50 border border-slate-200 rounded-full pl-9 pr-4 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40"
            >
        </div>
    </div>

    <div class="flex items-center gap-1.5 justify-self-end">
        <a
            href="{{ route('notifications') }}"
            class="relative w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-400 transition"
            aria-label="Notifications"
        >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a6 6 0 00-6 6c0 3.5-1.5 4.5-1.5 5.5S3.5 15 5 15h10c1.5 0 2.5-.5 2.5-1.5S16 11.5 16 8a6 6 0 00-6-6zM10 18a2 2 0 002-2H8a2 2 0 002 2z"/>
            </svg>
            @if(($unreadNotificationCount ?? 0) > 0)
                <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-[#D4AF37] text-[#1a1a1a] text-[9px] font-black flex items-center justify-center">
                    {{ min(9, $unreadNotificationCount) }}{{ $unreadNotificationCount > 9 ? '+' : '' }}
                </span>
            @endif
        </a>

        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button
                type="button"
                @click.stop="open = !open"
                class="flex items-center gap-2 bg-white border border-slate-200 rounded-full pl-1.5 pr-2.5 py-1 shadow-sm hover:shadow-md transition"
            >
                <div class="w-7 h-7 rounded-full overflow-hidden shrink-0 ring-2 ring-[#D4AF37]/60">
                    <img
                        src="https://ui-avatars.com/api/?name={{ urlencode(session('short_name') ?: session('full_name') ?: session('employee_name') ?: 'User') }}&background=D4AF37&color=1a1a1a&size=36"
                        class="w-full h-full object-cover"
                        alt="Profile"
                    />
                </div>
                <div class="leading-tight text-left hidden sm:block">
                    <p class="text-[12px] font-bold text-slate-800 truncate max-w-[140px]">
                        {{ session('salutation') ? session('salutation') . ' ' : '' }}{{ session('short_name') ?: session('full_name') ?: session('employee_name') ?: 'User' }}
                    </p>
                    <p class="text-[9px] text-slate-400 truncate max-w-[140px]">
                        {{ session('position') ?: 'My Profile' }}
                    </p>
                </div>
                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>

            <div x-show="open" x-transition class="absolute right-0 mt-2 w-44 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden py-1">
                <a href="{{ route('profile') }}" class="flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                    My Profile
                </a>
                <a href="{{ route('settings') }}" class="flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Settings
                </a>
                <div class="border-t border-slate-100 my-1"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        onclick="return confirm('You are about to logout. Continue?')"
                        class="w-full text-left flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-red-600 hover:bg-red-50 transition"
                    >
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<aside
    id="sidebar"
    x-data
    x-init="$watch('$store.sidebar.collapsed', v => setSidebarState(v)); setSidebarState($store.sidebar.collapsed)"
    class="fixed left-0 top-0 z-40 h-screen bg-[#111111] text-white
    border-r border-white/10 shadow-[4px_0_24px_rgba(0,0,0,0.30)]
    w-[230px] min-w-[230px] max-w-[230px]
    px-3 py-4 flex flex-col overflow-visible shrink-0 transition-all duration-300"
>

    <div class="sidebar-accent-bar absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10"></div>

    <button
        id="sidebarCloseBtn"
        type="button"
        @click.stop="$store.sidebar.collapsed = !$store.sidebar.collapsed"
        class="absolute top-4 right-3 z-[9999] w-7 h-7 flex items-center justify-center
        text-[#A4C3B2] bg-white/10 border border-white/20 rounded-full
        hover:bg-white/20 hover:text-white transition text-sm"
        aria-label="Close Sidebar"
    >
        ×
    </button>

    <!-- COMPANY AREA -->
    <button
        type="button"
        @click="if ($store.sidebar.collapsed) $store.sidebar.collapsed = false"
        class="group w-full flex items-center gap-2 mb-3 shrink-0 pr-8 text-left
        hover:bg-white/10 rounded-xl p-1.5 transition relative"
        aria-label="Open Sidebar"
    >
        @php
            // The tile's own background — either this user's custom sidebar
            // theme, or the hardcoded default it falls back to when unset
            // (see the sidebar-bg conditional style block above) — decides
            // which logo variant will actually be visible against it.
            $brandTileBg  = $sidebarBg ?: '#C8102E';
            $brandLogoUrl = \App\Services\CompanyLogoService::resolve(session('company_code'), session('company_logo_url'), $brandTileBg);
        @endphp
        <div class="sidebar-brand-tile w-10 h-10 rounded-xl bg-[#C8102E] border-2 border-[#D4AF37] flex items-center justify-center shrink-0 overflow-hidden p-1">
            @if($brandLogoUrl)
            <img src="{{ $brandLogoUrl }}" alt="{{ session('company_display_name') ?: 'Company logo' }}" class="sidebar-logo w-full h-full object-contain">
            @else
            <span class="sidebar-logo w-full h-full text-white font-bold text-base flex items-center justify-center">
                {{ strtoupper(substr(session('company_code') ?: 'R', 0, 1)) }}
            </span>
            @endif
            <span class="sidebar-icon-only hidden text-white font-bold text-lg">
                ☰
            </span>
        </div>

        <div class="sidebar-text leading-tight text-left min-w-0">
            <h1 class="text-[12px] font-bold tracking-wide text-white leading-tight break-words">
                {!! nl2br(e(session('company_display_name') ?: 'RICHWORKS KPI')) !!}
            </h1>

            <p class="sidebar-accent-text text-[9px] text-[#D4AF37] uppercase tracking-[0.14em] mt-1 font-semibold">
                Performance System
            </p>
        </div>

        <div class="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2
            bg-black text-white text-[10px] px-2 py-1 rounded-md
            opacity-0 group-hover:opacity-100 pointer-events-none transition
            whitespace-nowrap z-[9999] shadow-lg">
            Open Sidebar
        </div>
    </button>

    <div class="sidebar-accent-line h-px w-full shrink-0 mb-3 bg-gradient-to-r from-[#D4AF37] to-transparent"></div>

    @php
        $navSections = [
            [
                'title' => 'Overview',
                'items' => [
                    [
                        'label' => 'Main Dashboard',
                        'href' => '/dashboard',
                        'match' => 'dashboard*',
                        'icon' => 'dashboard',
                    ],
                    [
                        'label' => 'Performix',
                        'href'  => route('mini-app'),
                        'match' => 'mini-app*',
                        'icon'  => 'task',
                    ],
                    [
                        'label' => 'Notifications',
                        'href'  => route('notifications'),
                        'match' => 'notifications*',
                        'icon'  => 'bell',
                        'badge' => $unreadNotificationCount ?? 0,
                    ],
                    [
                        'label' => 'Job Description',
                        'href' => route('job-description'),
                        'match' => 'job-description*',
                        'icon' => 'jobdesc',
                    ],
                    [
                        'label'    => 'SLT Dashboard',
                        'href'     => route('slt-dashboard'),
                        'match'    => 'slt-dashboard*',
                        'icon'     => 'analytics',
                        'slt_only' => true,
                    ],
                ],
            ],
            [
                'title' => 'KPI Work',
                'items' => [
                    [
                        'label' => 'Create New KPI',
                        'href' => '/kpi/create',
                        'match' => [
                            'kpi/create'
                        ],
                        'icon' => 'plus',
                    ],
                    [
                        'label' => 'View My KPI',
                        'href' => '/kpi',
                        'match' => [
                            'kpi',
                            'kpi/*/edit'
                        ],
                        'icon' => 'list',
                    ],
                    [
                        'label' => 'Manage Weightage',
                        'href' => route('weightage'),
                        'match' => [
                            'weightage',
                            'weightage/*'
                        ],
                        'icon' => 'weightage',
                    ],
                    [
                        'label' => 'My Department KPI',
                        'href' => route('kpi.my-department-kpi'),
                        'match' => 'my-department-kpi*',
                        'icon' => 'department',
                    ],
                    [
                        'label' => 'Target Linkages',
                        'href' => route('linkages'),
                        'match' => 'linkages*',
                        'icon' => 'linkage',
                    ],
                    [
                        'label'     => 'Titan KPI',
                        'href'      => route('titan-kpi.index'),
                        'match'     => 'titan-kpi*',
                        'icon'      => 'report',
                        'titan_only' => true,
                    ],
                ],
            ],
            [
                'title' => 'Monitoring',
                'items' => [

                    [
                        'label' => 'User Activity Log',
                        'href' => '/activity-log',
                        'match' => 'activity-log*',
                        'icon' => 'activity',
                    ],
                ],
            ],
            [
                'title'   => 'Attendance',
                'hr_only' => true,
                'items'   => [
                    [
                        'label' => 'Import & Analysis',
                        'href'  => '/attendance',
                        'match' => 'attendance*',
                        'icon'  => 'attendance',
                    ],
                ],
            ],
            [
                'title' => 'Performance Evaluation',
                'items' => [
                    ['label' => 'Q1 Evaluation', 'href' => '/performance/report/q1', 'match' => 'performance/report/q1*', 'icon' => 'report'],
                    ['label' => 'Q2 Evaluation', 'href' => '/performance/report/q2', 'match' => 'performance/report/q2*', 'icon' => 'report'],
                    ['label' => 'Q3 Evaluation', 'href' => '/performance/report/q3', 'match' => 'performance/report/q3*', 'icon' => 'report'],
                    ['label' => 'Q4 Evaluation', 'href' => '/performance/report/q4', 'match' => 'performance/report/q4*', 'icon' => 'report'],
                ],
            ],
            [
                // 'View As' stays BTS-only via its own item-level flag below;
                // 'Quarter Control' additionally opens for the named-individual
                // grant in Controller::isQuarterControlAuthorized() (Suley, RCG
                // COO / RGHB Group COO, 2026-08-21) -- letting the whole section
                // through on quarter_control_only so she sees SOMETHING here,
                // while the still-bts_only item inside stays hidden from her.
                'title'                 => 'Admin Setup',
                'bts_only'              => true,
                'quarter_control_only'  => true,
                'items'    => [
                    ['label' => 'View As (Employee KPI)', 'href' => route('admin.view-as'), 'match' => 'admin/view-as*', 'icon' => 'users', 'bts_only' => true],
                    ['label' => 'Quarter Control', 'href' => route('admin.quarter-control'), 'match' => 'admin/quarter-control*', 'icon' => 'calendar'],
                ],
            ],
        ];
    @endphp

    <!-- NAVIGATION -->
    <div class="relative flex-1 min-h-0 flex flex-col">
    <nav class="flex-1 overflow-y-auto text-[12px] space-y-5 pr-1 min-h-0 custom-scroll">
        @php
            $isBts = session('department_code') === 'BTS';
            $isSltDept = in_array(strtoupper(trim(session('department_code') ?? '')), ['SLT OFFICE', 'BTS']);
            $isQuarterControlAuthorized = $isBts || in_array(session('employee_uuid'), [
                '8e322638-49a5-4560-b800-8d846c53e7c8', // Suley -- RCG
                '700c2198-4871-45c6-a63f-e2e1ad80e657', // Suley -- RGHB
            ], true);
        @endphp
        @foreach($navSections as $section)
            @if(($section['hr_only'] ?? false) && !session('hr_access'))
                @continue
            @endif
            @if(($section['bts_only'] ?? false) && !$isBts && !(($section['quarter_control_only'] ?? false) && $isQuarterControlAuthorized))
                @continue
            @endif
            <div>
                <div class="sidebar-text flex items-center gap-2 mb-1 px-2">
                    <p class="sidebar-accent-text text-[9px] text-[#D4AF37] font-semibold uppercase tracking-widest shrink-0">
                        {{ $section['title'] }}
                    </p>
                    <div class="sidebar-accent-line h-px flex-1 bg-gradient-to-r from-[#D4AF37] to-transparent"></div>
                </div>

                <div class="space-y-1">
                    @foreach($section['items'] as $item)
                        @php
                            $hasTitanAccess = (session('role') !== 'VP' && session('company_code') === 'RCG' && session('department_code') === 'TITAN')
                                || session('department_code') === 'BTS';
                        @endphp
                        @if(($item['slt_only'] ?? false) && !$isSltDept)
                            @continue
                        @endif
                        @if(($item['bts_only'] ?? false) && !$isBts)
                            @continue
                        @endif
                        @if(($item['titan_only'] ?? false) && !$hasTitanAccess)
                            @continue
                        @endif
                        @if(($item['manager_vp_only'] ?? false) && !session('has_subordinates') && session('department_code') !== 'BTS')
                            @continue
                        @endif
                        @php
                            $isActive = false;

                            if(is_array($item['match'])){

                                foreach($item['match'] as $pattern){

                                    if(request()->is($pattern)){

                                        $isActive = true;
                                        break;
                                    }
                                }

                            }else{

                                $isActive = request()->is($item['match']);
                            }
                        @endphp

                        <a
                            href="{{ $item['href'] }}"
                            class="group relative flex items-center gap-3 px-3 py-2 rounded-xl transition
                            {{ $isActive
                                ? 'sidebar-active-item bg-gradient-to-r from-[#C8102E] to-[#7A0019] border-l-[3px] border-[#D4AF37] text-white font-black shadow-md'
                                : 'text-white/85 font-medium hover:bg-white/10 hover:text-white'
                            }}"
                        >
                            <span class="w-5 h-5 flex items-center justify-center shrink-0">
                                @include('partials.sidebar-icons', ['icon' => $item['icon']])
                            </span>

                            <div class="flex items-center justify-between w-full min-w-0 gap-2">

                                <span class="sidebar-text truncate">
                                    {{ $item['label'] }}
                                </span>

                                @if(($item['badge'] ?? 0) > 0)

                                    <span class="sidebar-text min-w-[20px] h-[20px]
                                        rounded-full bg-red-500 text-white text-[10px]
                                        font-black flex items-center justify-center
                                        px-1 shadow-lg shadow-red-500/30">

                                        {{ $item['badge'] }}

                                    </span>

                                @endif

                            </div>

                            <div class="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2
                                bg-black text-white text-[10px] px-2 py-1 rounded-md
                                opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150
                                whitespace-nowrap z-[9999] shadow-lg">
                                {{ $item['label'] }}
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        <!-- ACCOUNT SETTINGS — smaller than regular nav items, sits right above Help & Guide -->
        <a
            href="{{ route('settings') }}"
            class="group relative flex items-center gap-2 px-3 py-1.5 rounded-lg transition mt-1
            {{ request()->is('settings*')
                ? 'sidebar-active-item bg-gradient-to-r from-[#C8102E] to-[#7A0019] border-l-[3px] border-[#D4AF37] text-white font-bold shadow-md'
                : 'text-white/60 font-medium hover:bg-white/10 hover:text-white'
            }}"
        >
            <span class="w-4 h-4 flex items-center justify-center shrink-0">
                @include('partials.sidebar-icons', ['icon' => 'settings'])
            </span>

            <span class="sidebar-text truncate text-[11px]">
                Account Settings
            </span>

            <div class="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2
                bg-black text-white text-[10px] px-2 py-1 rounded-md
                opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150
                whitespace-nowrap z-[9999] shadow-lg">
                Account Settings
            </div>
        </a>

        <!-- HELP & GUIDE — last item in the scrollable nav, smaller than regular items -->
        <a
            href="{{ route('help') }}"
            class="group relative flex items-center gap-2 px-3 py-1.5 rounded-lg transition mt-1
            {{ request()->is('help*')
                ? 'sidebar-active-item bg-gradient-to-r from-[#C8102E] to-[#7A0019] border-l-[3px] border-[#D4AF37] text-white font-bold shadow-md'
                : 'text-white/60 font-medium hover:bg-white/10 hover:text-white'
            }}"
        >
            <span class="w-4 h-4 flex items-center justify-center shrink-0">
                @include('partials.sidebar-icons', ['icon' => 'help'])
            </span>

            <span class="sidebar-text truncate text-[11px]">
                Help &amp; Guide
            </span>

            <div class="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2
                bg-black text-white text-[10px] px-2 py-1 rounded-md
                opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150
                whitespace-nowrap z-[9999] shadow-lg">
                Help & Guide
            </div>
        </a>
    </nav>
    <div class="sidebar-fade pointer-events-none absolute bottom-0 left-0 right-0 h-6 bg-gradient-to-t from-[#111111] to-transparent"></div>
    </div>

    <!-- SYSTEM ZONE -->
    <div class="sidebar-system mt-3 pt-3 border-t border-white/10 shrink-0">

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                onclick="return confirm('You are about to logout. Continue?')"
                class="group relative w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[11px] font-semibold
                bg-red-600 text-white border border-red-500
                hover:bg-red-700 hover:border-red-600 transition shadow-lg shadow-red-900/40"
            >
                <span class="w-5 h-5 flex items-center justify-center shrink-0">
                    @include('partials.sidebar-icons', ['icon' => 'logout'])
                </span>

                <span class="sidebar-text">
                    Logout
                </span>

                <div class="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2
                    bg-black text-white text-[10px] px-2 py-1 rounded-md
                    opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150
                    whitespace-nowrap z-[9999] shadow-lg">
                    Logout
                </div>
            </button>
        </form>
    </div>

</aside>

<script>
    // Every page's AJAX calls hardcode `{{ csrf_token() }}` at render time --
    // if the page stays open long enough for the session's CSRF token to
    // change server-side (idle timeout, session file recycled, etc.), every
    // one of those baked-in tokens goes stale and the next POST/DELETE fails
    // with a 419 "CSRF token mismatch", no matter which page or button.
    // Patching fetch() once here (included on every authenticated page) means
    // no individual call site needs to know about token refresh: on a 419
    // that was actually carrying an X-CSRF-TOKEN header, fetch a live token
    // from /csrf-token and silently retry the exact same request once. If
    // that still fails because the session itself is gone (not just the
    // token), the retry redirects through to /login and we follow it instead
    // of leaving the caller to choke on an HTML body it expected to be JSON.
    (function () {
        if (window.__csrfSafeFetchInstalled) return;
        window.__csrfSafeFetchInstalled = true;
        const originalFetch = window.fetch.bind(window);

        function hasCsrfHeader(init) {
            const headers = init && init.headers;
            if (!headers) return false;
            if (headers instanceof Headers) return headers.has('X-CSRF-TOKEN');
            return Object.keys(headers).some((key) => key.toLowerCase() === 'x-csrf-token');
        }

        function withToken(init, token) {
            const next = Object.assign({}, init);
            if (init.headers instanceof Headers) {
                const headers = new Headers(init.headers);
                headers.set('X-CSRF-TOKEN', token);
                next.headers = headers;
            } else {
                next.headers = Object.assign({}, init.headers, { 'X-CSRF-TOKEN': token });
            }
            return next;
        }

        window.fetch = async function (input, init) {
            let response = await originalFetch(input, init);

            if (response.status === 419 && hasCsrfHeader(init)) {
                try {
                    const tokenResponse = await originalFetch('/csrf-token', { headers: { 'Accept': 'application/json' } });
                    if (tokenResponse.ok) {
                        const data = await tokenResponse.json();
                        if (data && data.token) {
                            response = await originalFetch(input, withToken(init, data.token));
                        }
                    }
                } catch (e) {
                    // Refresh failed -- fall through with the original 419 response
                    // so the caller's own error handling still runs.
                }
            }

            if (response.redirected && response.url.indexOf('/login') !== -1) {
                window.location.href = '/login';
                return new Promise(() => {});
            }

            return response;
        };
    })();

    document.addEventListener('alpine:init', () => {
        Alpine.store('sidebar', {
            collapsed: localStorage.getItem('sidebarCollapsed') === 'true',
        });
    });

    function setSidebarState(collapsed) {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (!sidebar) return;

        const texts = sidebar.querySelectorAll('.sidebar-text');
        const iconOnly = sidebar.querySelectorAll('.sidebar-icon-only');
        const closeBtn = document.getElementById('sidebarCloseBtn');
        const logo = sidebar.querySelector('.sidebar-logo');
        const systemZone = sidebar.querySelector('.sidebar-system');

        sidebar.classList.toggle('collapsed', collapsed);

        sidebar.classList.toggle('w-[230px]', !collapsed);
        sidebar.classList.toggle('min-w-[230px]', !collapsed);
        sidebar.classList.toggle('max-w-[230px]', !collapsed);

        sidebar.classList.toggle('w-[64px]', collapsed);
        sidebar.classList.toggle('min-w-[64px]', collapsed);
        sidebar.classList.toggle('max-w-[64px]', collapsed);

        texts.forEach(item => {
            item.classList.toggle('hidden', collapsed);
        });

        iconOnly.forEach(item => {
            item.classList.toggle('hidden', !collapsed);
        });

        if (logo) {
            logo.classList.toggle('hidden', collapsed);
        }

        if (closeBtn) {
            closeBtn.classList.toggle('hidden', collapsed);
        }

        if (systemZone) {
            systemZone.classList.toggle('border-t', !collapsed);
            systemZone.classList.toggle('pt-3', !collapsed);
            systemZone.classList.toggle('mt-3', !collapsed);
        }

        if (mainContent) {
            mainContent.classList.toggle('ml-[230px]', !collapsed);
            mainContent.classList.toggle('ml-[64px]', collapsed);
        }

        const topBar = document.getElementById('topBar');
        if (topBar) {
            topBar.style.left = collapsed ? '64px' : '230px';
        }

        localStorage.setItem('sidebarCollapsed', collapsed ? 'true' : 'false');
    }

    // #topBar's title mirrors the page's own <title> (already set per page),
    // trimmed to the first segment so "KPI List · FY2026" just reads "KPI List".
    document.addEventListener('DOMContentLoaded', function () {
        const titleEl = document.getElementById('topBarTitle');
        if (!titleEl) return;
        const first = (document.title || 'Dashboard').split(/[·|—]/)[0].trim();
        titleEl.textContent = first || 'Dashboard';
    });

    // #topBar's search box: on the KPI List page, drive its existing
    // search/filter directly; from anywhere else, hand off to KPI List
    // via ?q= so search feels continuous instead of just resetting there.
    function handleTopBarSearch(value) {
        const value_ = (value || '').trim();
        const localSearchInput = document.getElementById('searchInput');

        if (localSearchInput) {
            localSearchInput.value = value_;
            localSearchInput.dispatchEvent(new Event('input'));
            localSearchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        window.location.href = '{{ route('kpi.index') }}' + (value_ ? ('?q=' + encodeURIComponent(value_)) : '');
    }
</script>
