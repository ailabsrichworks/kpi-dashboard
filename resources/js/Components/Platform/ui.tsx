import { ReactNode, useState } from 'react';
import { InfoIcon } from './Icons';

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
