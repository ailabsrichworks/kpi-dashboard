import { ReactNode, useState } from 'react';
import { InfoIcon, SparklesIcon } from './Icons';
import { PERFORMANCE_STATUS_LABEL, PerformanceStatus } from '@/lib/performanceStatus';

/**
 * Shared visual vocabulary for every Platform page — extracted after finding
 * the same card/badge/empty-state markup hand-copied into all 19 page files
 * with small, meaningless variations (rounded-xl vs rounded-2xl, shadow-sm on
 * some, not others). One definition per concept means a KPI card looks like
 * a department card looks like a company card, which is a large share of
 * what makes a multi-page system read as "one product" to someone who isn't
 * thinking about the code behind it at all.
 */

export function Card({
    title,
    description,
    actions,
    children,
    className = '',
}: {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={`bg-white rounded-2xl shadow-sm border border-slate-200 p-6 ${className}`}>
            {(title || actions) && (
                <div className="flex items-start justify-between gap-4 mb-4">
                    <div>
                        {title && <h2 className="text-sm font-bold text-slate-800">{title}</h2>}
                        {description && <p className="text-xs text-slate-400 mt-0.5">{description}</p>}
                    </div>
                    {actions && <div className="flex-none flex items-center gap-3">{actions}</div>}
                </div>
            )}
            {children}
        </div>
    );
}

const STAT_TONE: Record<string, string> = {
    default: 'text-slate-800',
    success: 'text-emerald-600',
    warning: 'text-amber-600',
    danger: 'text-red-600',
};

export function StatCard({
    label,
    value,
    hint,
    tone = 'default',
    icon,
}: {
    label: string;
    value: string | number;
    hint?: string;
    tone?: 'default' | 'success' | 'warning' | 'danger';
    icon?: ReactNode;
}) {
    return (
        <div className="rounded-xl bg-slate-50 px-4 py-3.5">
            <div className="flex items-center gap-1.5 mb-1">
                {icon && <span className="text-slate-400">{icon}</span>}
                <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">{label}</p>
            </div>
            <p className={`text-2xl font-bold tabular-nums ${STAT_TONE[tone]}`}>{value}</p>
            {hint && <p className="text-[11px] text-slate-400 mt-0.5">{hint}</p>}
        </div>
    );
}

const BADGE_TONE: Record<string, string> = {
    success: 'bg-emerald-100 text-emerald-700',
    warning: 'bg-amber-100 text-amber-700',
    danger: 'bg-red-100 text-red-700',
    neutral: 'bg-slate-100 text-slate-600',
    info: 'bg-sky-100 text-sky-700',
    brand: 'bg-brand-100 text-brand-900',
};

export function Badge({ tone = 'neutral', children }: { tone?: keyof typeof BADGE_TONE; children: ReactNode }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${BADGE_TONE[tone]}`}>
            {children}
        </span>
    );
}

/** Maps the six real company lifecycle statuses to a consistent badge tone everywhere they're shown. */
export function StatusBadge({ status }: { status: string }) {
    const tone: Record<string, keyof typeof BADGE_TONE> = {
        active: 'success',
        draft: 'neutral',
        onboarding: 'info',
        configuring: 'info',
        suspended: 'danger',
        archived: 'neutral',
    };
    return <Badge tone={tone[status] ?? 'neutral'}>{status}</Badge>;
}

export function EmptyState({
    icon,
    title,
    description,
    action,
}: {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center text-center py-10 px-4">
            {icon && <div className="mb-3 text-slate-300">{icon}</div>}
            <p className="text-sm font-semibold text-slate-600">{title}</p>
            {description && <p className="text-xs text-slate-400 mt-1 max-w-sm">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

/**
 * A small "?" affordance next to a technical-sounding label (Achievement %,
 * Visibility, Onboarding status) that reveals one or two plain-language
 * sentences on hover/focus — the concrete mechanism behind "make non
 * technical [users] understand what's going on" without cluttering every
 * page with permanent explanatory paragraphs.
 */
export function InfoTooltip({ text }: { text: string }) {
    const [open, setOpen] = useState(false);

    return (
        <span className="relative inline-flex">
            <button
                type="button"
                onMouseEnter={() => setOpen(true)}
                onMouseLeave={() => setOpen(false)}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                className="text-slate-300 hover:text-slate-500 focus:outline-none"
                aria-label="More information"
            >
                <InfoIcon className="w-3.5 h-3.5" />
            </button>
            {open && (
                <span className="absolute z-20 bottom-full left-1/2 -translate-x-1/2 mb-1.5 w-56 rounded-lg bg-slate-800 text-white text-[11px] leading-snug px-3 py-2 shadow-lg">
                    {text}
                </span>
            )}
        </span>
    );
}

export function PrimaryButton({
    children,
    className = '',
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            className={`rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}

export function SecondaryButton({
    children,
    className = '',
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            className={`rounded-lg bg-white border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors ${className}`}
            {...props}
        >
            {children}
        </button>
    );
}

const PERFORMANCE_STATUS_TONE: Record<PerformanceStatus, keyof typeof BADGE_TONE> = {
    on_track: 'success',
    at_risk: 'warning',
    critical: 'danger',
    no_data: 'neutral',
};

/** On Track / At Risk / Critical — the one performance-tier badge every role view shares (see lib/performanceStatus.ts). */
export function PerformanceStatusBadge({ status }: { status: PerformanceStatus }) {
    return <Badge tone={PERFORMANCE_STATUS_TONE[status]}>{PERFORMANCE_STATUS_LABEL[status]}</Badge>;
}

/**
 * The one shape every "problem" surfaces in across this app (spec: status +
 * reason + owner + period + next action, never a bare percentage). Used by
 * the CEO's Needs Attention list, HR's compliance alerts, and the HQ client
 * health list alike.
 */
export function NeedsAttentionCard({
    title,
    pct,
    status,
    reason,
    owner,
    period,
    actions,
}: {
    title: ReactNode;
    pct?: number | null;
    status: PerformanceStatus;
    reason?: ReactNode;
    owner?: string | null;
    period?: string | null;
    actions?: ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4 py-3.5 border-b border-slate-100 last:border-0">
            <div className="min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                    <p className="text-sm font-semibold text-slate-800">{title}</p>
                    {pct !== undefined && pct !== null && (
                        <span className="text-sm font-bold tabular-nums text-slate-700">{pct.toFixed(0)}%</span>
                    )}
                    <PerformanceStatusBadge status={status} />
                </div>
                {reason && <p className="text-xs text-slate-500 mt-1">{reason}</p>}
                {(owner || period) && (
                    <p className="text-[11px] text-slate-400 mt-1">
                        {owner && (
                            <>
                                Owner: <span className="font-medium text-slate-500">{owner}</span>
                            </>
                        )}
                        {owner && period && ' · '}
                        {period}
                    </p>
                )}
            </div>
            {actions && <div className="flex-none flex items-center gap-2 pt-0.5">{actions}</div>}
        </div>
    );
}

/** A company goal with its progress bar and contributing-KPI count — the cascade view's top rung. */
export function GoalProgressCard({
    title,
    description,
    progress,
    status,
    owner,
    contributingCount,
    onClick,
}: {
    title: ReactNode;
    description?: ReactNode;
    progress?: number | null;
    status: string;
    owner?: string | null;
    contributingCount?: number;
    onClick?: () => void;
}) {
    const pct = Math.max(0, Math.min(100, progress ?? 0));
    const barTone = status === 'at_risk' ? 'bg-amber-500' : status === 'cancelled' || status === 'archived' ? 'bg-slate-300' : 'bg-brand-800';

    return (
        <div
            className={`py-3.5 border-b border-slate-100 last:border-0 ${onClick ? 'cursor-pointer hover:bg-slate-50 -mx-2 px-2 rounded-lg' : ''}`}
            onClick={onClick}
        >
            <div className="flex items-center justify-between gap-3 mb-1.5">
                <p className="text-sm font-semibold text-slate-800">{title}</p>
                <StatusBadge status={status} />
            </div>
            {description && <p className="text-xs text-slate-500 mb-2">{description}</p>}
            {progress !== undefined && progress !== null && (
                <div className="flex items-center gap-2.5">
                    <div className="flex-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div className={`h-full rounded-full ${barTone}`} style={{ width: `${pct}%` }} />
                    </div>
                    <span className="text-xs font-bold tabular-nums text-slate-600 w-9 text-right">{pct.toFixed(0)}%</span>
                </div>
            )}
            <p className="text-[11px] text-slate-400 mt-1.5">
                {owner && (
                    <>
                        Owner: <span className="font-medium text-slate-500">{owner}</span>
                        {contributingCount !== undefined && ' · '}
                    </>
                )}
                {contributingCount !== undefined && `${contributingCount} contributing KPI${contributingCount === 1 ? '' : 's'}`}
            </p>
        </div>
    );
}

/** ANIRA's insight framing (spec §33): a plain-language observation plus a recommended focus, never a bare chat bubble. */
export function InsightCard({
    title = 'ANIRA Insight',
    children,
    actions,
}: {
    title?: ReactNode;
    children: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div className="rounded-2xl border border-brand-100 bg-brand-50/60 p-5">
            <div className="flex items-center gap-2 mb-2.5">
                <SparklesIcon className="w-4 h-4 text-brand-800" />
                <p className="text-sm font-bold text-brand-900">{title}</p>
            </div>
            <div className="text-sm text-slate-700 leading-relaxed space-y-2">{children}</div>
            {actions && <div className="mt-3.5 flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export interface FilterOption {
    value: string;
    label: string;
}

export interface FilterDef {
    key: string;
    label: string;
    options: FilterOption[];
    value: string;
}

/** A row of dropdown filters shared by every filterable list (HR's employee list, Company Admin's employee list). */
export function FilterBar({ filters, onChange }: { filters: FilterDef[]; onChange: (key: string, value: string) => void }) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            {filters.map((f) => (
                <select
                    key={f.key}
                    value={f.value}
                    onChange={(e) => onChange(f.key, e.target.value)}
                    className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 focus:outline-none focus:ring-2 focus:ring-brand-100 focus:border-brand-800"
                >
                    <option value="">{f.label}: All</option>
                    {f.options.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
            ))}
        </div>
    );
}
