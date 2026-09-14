export interface NavItem {
    label: string;
    href: string;
    match: string | string[];
    icon: string;
    badge?: 'unreadNotifications';
    sltOnly?: boolean;
    titanOnly?: boolean;
    btsOnly?: boolean;
    /**
     * True when this route's controller still returns a plain Blade `view()`
     * response rather than `Inertia::render()`. Inertia's own <Link> globally
     * intercepts same-origin clicks (see @inertiajs/core's shouldIntercept);
     * when the resulting response has no X-Inertia header, Inertia treats it
     * as a "non-Inertia response" and renders it inside a sandboxed
     * `<iframe sandbox="allow-scripts">` (no allow-same-origin) instead of
     * navigating -- which breaks localStorage and same-origin fetch() inside
     * that page entirely, and looks like the destination page rendering on
     * top of the current one. Items flagged here must render as a plain
     * `<a>` in Sidebar.tsx (and anywhere else they're linked to) so the
     * browser does a real, full navigation instead.
     */
    legacy?: boolean;
}

export interface NavSection {
    title: string;
    items: NavItem[];
    hrOnly?: boolean;
    btsOnly?: boolean;
    /**
     * Mirrors partials/sidebar.blade.php's `quarter_control_only`: lets this
     * bts_only section still open for the named-individual "Quarter Control"
     * grant (Controller::QUARTER_CONTROL_EXTRA_ACCESS_IDS), while items
     * inside that are themselves flagged btsOnly (e.g. View As) stay hidden.
     */
    quarterControlOnly?: boolean;
}

// Ported 1:1 from partials/sidebar.blade.php's $navSections array. Routes are
// hardcoded static paths (no Ziggy yet, consistent with the Phase 3 decision)
// rather than route() calls.
export const navSections: NavSection[] = [
    {
        title: 'Overview',
        items: [
            { label: 'Main Dashboard', href: '/dashboard', match: 'dashboard*', icon: 'dashboard' },
            { label: 'Performix', href: '/mini-app', match: 'mini-app*', icon: 'task', legacy: true },
            { label: 'Notifications', href: '/notifications', match: 'notifications*', icon: 'bell', badge: 'unreadNotifications' },
            { label: 'Job Description', href: '/job-description', match: 'job-description*', icon: 'jobdesc', legacy: true },
            { label: 'SLT Dashboard', href: '/slt-dashboard', match: 'slt-dashboard*', icon: 'analytics', sltOnly: true },
        ],
    },
    {
        title: 'KPI Work',
        items: [
            { label: 'Create New KPI', href: '/kpi/create', match: ['kpi/create'], icon: 'plus', legacy: true },
            { label: 'View My KPI', href: '/kpi', match: ['kpi', 'kpi/*/edit'], icon: 'list', legacy: true },
            { label: 'Manage Weightage', href: '/weightage', match: ['weightage', 'weightage/*'], icon: 'weightage', legacy: true },
            { label: 'My Department KPI', href: '/my-department-kpi', match: 'my-department-kpi*', icon: 'department', legacy: true },
            { label: 'Target Linkages', href: '/linkages', match: 'linkages*', icon: 'linkage' },
            { label: 'Titan KPI', href: '/titan-kpi', match: 'titan-kpi*', icon: 'report', titanOnly: true, legacy: true },
        ],
    },
    {
        title: 'Monitoring',
        items: [
            { label: 'User Activity Log', href: '/activity-log', match: 'activity-log*', icon: 'activity' },
        ],
    },
    {
        title: 'Attendance',
        hrOnly: true,
        items: [
            { label: 'Import & Analysis', href: '/attendance', match: 'attendance*', icon: 'attendance', legacy: true },
        ],
    },
    {
        title: 'Performance Evaluation',
        items: [
            { label: 'Q1 Evaluation', href: '/performance/report/q1', match: 'performance/report/q1*', icon: 'report', legacy: true },
            { label: 'Q2 Evaluation', href: '/performance/report/q2', match: 'performance/report/q2*', icon: 'report', legacy: true },
            { label: 'Q3 Evaluation', href: '/performance/report/q3', match: 'performance/report/q3*', icon: 'report', legacy: true },
            { label: 'Q4 Evaluation', href: '/performance/report/q4', match: 'performance/report/q4*', icon: 'report', legacy: true },
        ],
    },
    {
        title: 'Admin Setup',
        btsOnly: true,
        quarterControlOnly: true,
        items: [
            { label: 'View As (Employee KPI)', href: '/admin/view-as', match: 'admin/view-as*', icon: 'users', btsOnly: true },
            { label: 'Quarter Control', href: '/admin/quarter-control', match: 'admin/quarter-control*', icon: 'calendar', legacy: true },
        ],
    },
];

function escapeRegExp(value: string): string {
    return value.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Mirrors Laravel's Request::is() wildcard semantics: '*' matches any run of
 * characters, matched against the path with no leading slash and no
 * query/hash.
 */
export function matchesRoute(pattern: string, currentUrl: string): boolean {
    const path = currentUrl.replace(/^\//, '').split(/[?#]/)[0];
    const regex = new RegExp('^' + pattern.split('*').map(escapeRegExp).join('.*') + '$');
    return regex.test(path);
}

export function isNavItemActive(item: NavItem, currentUrl: string): boolean {
    const patterns = Array.isArray(item.match) ? item.match : [item.match];
    return patterns.some((pattern) => matchesRoute(pattern, currentUrl));
}
