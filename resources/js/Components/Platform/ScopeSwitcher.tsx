import { router } from '@inertiajs/react';

export interface ScopeOption {
    id: string;
    name: string;
    unit_type: string;
    parent_department_id: string | null;
}

/**
 * Flattens the department hierarchy into one indented list so a single
 * <select> can represent "Entire Company > Business Unit > Branch >
 * Department > Team" without a bespoke tree widget — spec §4's scope
 * switcher, without inventing department "sub-accounts": this only ever
 * narrows which existing departments' data is shown, on the same page.
 */
function flatten(departments: ScopeOption[], parentId: string | null = null, depth = 0): Array<ScopeOption & { depth: number }> {
    return departments
        .filter((d) => d.parent_department_id === parentId)
        .flatMap((d) => [{ ...d, depth }, ...flatten(departments, d.id, depth + 1)]);
}

export default function ScopeSwitcher({
    departments,
    value,
    paramName = 'scope',
}: {
    departments: ScopeOption[];
    value: string | null;
    paramName?: string;
}) {
    const rows = flatten(departments);

    function onChange(next: string) {
        const url = new URL(window.location.href);
        if (next) {
            url.searchParams.set(paramName, next);
        } else {
            url.searchParams.delete(paramName);
        }
        router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true });
    }

    return (
        <select
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-100 focus:border-brand-800"
        >
            <option value="">Entire Company</option>
            {rows.map((d) => (
                <option key={d.id} value={d.id}>
                    {'  '.repeat(d.depth)}
                    {d.depth > 0 ? '↳ ' : ''}
                    {d.name}
                </option>
            ))}
        </select>
    );
}
