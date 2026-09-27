import { router } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState } from '@/Components/Platform/ui';
import { PuzzlePieceIcon } from '@/Components/Platform/Icons';

interface Module {
    id: string;
    key: string;
    name: string;
    description: string | null;
    is_active: boolean;
}

interface CompanyRef {
    id: string;
    name: string;
    code: string;
}

interface ModulesPageProps {
    modules: Module[];
    companies: CompanyRef[];
    grants: Record<string, Record<string, boolean>>;
    [key: string]: unknown;
}

/** No grant row for a (company, module) pair means "not yet decided" — shown as off, matching a fresh company having nothing explicitly enabled. */
export default function ModulesIndex({ modules, companies, grants }: ModulesPageProps) {
    function toggle(companyId: string, moduleId: string, enabled: boolean) {
        router.post(`/platform/hq/companies/${companyId}/modules/${moduleId}`, { enabled: !enabled }, { preserveScroll: true });
    }

    return (
        <PlatformLayout title="Modules" description="Which Performix features are enabled for each company." maxWidth="max-w-6xl">
            <Card title="Module catalog" className="mb-5">
                <ul className="divide-y divide-slate-100">
                    {modules.map((m) => (
                        <li key={m.id} className="py-3">
                            <p className="text-sm font-semibold text-slate-800">{m.name}</p>
                            {m.description && <p className="text-xs text-slate-500 mt-0.5">{m.description}</p>}
                        </li>
                    ))}
                </ul>
            </Card>

            <Card title="Per-company enablement">
                {companies.length === 0 || modules.length === 0 ? (
                    <EmptyState icon={<PuzzlePieceIcon className="w-10 h-10" />} title="Nothing to configure yet" />
                ) : (
                    <div className="overflow-x-auto -mx-6">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                    <th className="px-6 py-2 font-semibold">Company</th>
                                    {modules.map((m) => (
                                        <th key={m.id} className="px-3 py-2 font-semibold text-center">
                                            {m.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {companies.map((c) => (
                                    <tr key={c.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-6 py-2.5 font-medium text-slate-800">{c.name}</td>
                                        {modules.map((m) => {
                                            const enabled = grants[c.id]?.[m.id] ?? false;
                                            return (
                                                <td key={m.id} className="px-3 py-2.5 text-center">
                                                    <button
                                                        onClick={() => toggle(c.id, m.id, enabled)}
                                                        className={`inline-flex h-5 w-9 items-center rounded-full transition-colors ${enabled ? 'bg-emerald-500' : 'bg-slate-200'}`}
                                                        aria-label={`${enabled ? 'Disable' : 'Enable'} ${m.name} for ${c.name}`}
                                                    >
                                                        <span className={`h-4 w-4 rounded-full bg-white shadow transform transition-transform ${enabled ? 'translate-x-4' : 'translate-x-0.5'}`} />
                                                    </button>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </PlatformLayout>
    );
}
