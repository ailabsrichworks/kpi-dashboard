import { router } from '@inertiajs/react';

/**
 * Spec §5: performance information must always say which period it's for,
 * and switching it should update the page immediately. Reads/writes plain
 * query params (`fy`, `period_type`, `period_number`) so any controller can
 * opt in just by reading `$request->query(...)` — no new route needed per
 * page. `financialYear` is the company's own current FY (from
 * PerformancePeriodService), used as the default when nothing is selected.
 */
export default function PeriodSwitcher({
    financialYear,
    periodType,
    periodNumber,
}: {
    financialYear: number;
    periodType: 'quarter' | 'month';
    periodNumber: number;
}) {
    function set(params: Record<string, string>) {
        const url = new URL(window.location.href);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true });
    }

    const years = [financialYear - 1, financialYear, financialYear + 1];

    return (
        <div className="flex items-center gap-1.5">
            <select
                value={financialYear}
                onChange={(e) => set({ fy: e.target.value })}
                className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-100 focus:border-brand-800"
            >
                {years.map((y) => (
                    <option key={y} value={y}>
                        FY{y}
                    </option>
                ))}
            </select>
            <select
                value={periodType}
                onChange={(e) => set({ period_type: e.target.value, period_number: '1' })}
                className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-100 focus:border-brand-800"
            >
                <option value="quarter">Quarterly</option>
                <option value="month">Monthly</option>
            </select>
            <select
                value={periodNumber}
                onChange={(e) => set({ period_number: e.target.value })}
                className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-100 focus:border-brand-800"
            >
                {Array.from({ length: periodType === 'quarter' ? 4 : 12 }, (_, i) => i + 1).map((n) => (
                    <option key={n} value={n}>
                        {periodType === 'quarter' ? `Q${n}` : new Date(2000, n - 1, 1).toLocaleString('en', { month: 'long' })}
                    </option>
                ))}
            </select>
        </div>
    );
}
