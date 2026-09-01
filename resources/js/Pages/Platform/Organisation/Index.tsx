import { useState } from 'react';
import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, SecondaryButton } from '@/Components/Platform/ui';
import { BuildingIcon, ChevronRightIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Department {
    id: string;
    name: string;
    unit_type: 'business_unit' | 'branch' | 'department' | 'team';
    parent_department_id: string | null;
    status: string;
    member_count: number;
}

interface OrganisationPageProps {
    company: Company;
    departments: Department[];
    [key: string]: unknown;
}

const UNIT_LABEL: Record<Department['unit_type'], string> = {
    business_unit: 'Business Unit',
    branch: 'Branch',
    department: 'Department',
    team: 'Team',
};

function TreeNode({ node, all, depth }: { node: Department; all: Department[]; depth: number }) {
    const children = all.filter((d) => d.parent_department_id === node.id);

    return (
        <div>
            <div className="flex items-center justify-between gap-3 py-2" style={{ paddingLeft: depth * 22 }}>
                <div className="flex items-center gap-2 min-w-0">
                    {depth > 0 && <ChevronRightIcon className="w-3.5 h-3.5 text-slate-300 flex-none" />}
                    <span className="text-sm font-medium text-slate-800 truncate">{node.name}</span>
                    <Badge tone="neutral">{UNIT_LABEL[node.unit_type]}</Badge>
                    {node.status !== 'active' && <Badge tone="danger">{node.status}</Badge>}
                </div>
                <span className="flex-none text-xs text-slate-400">{node.member_count} member{node.member_count === 1 ? '' : 's'}</span>
            </div>
            {children.map((c) => (
                <TreeNode key={c.id} node={c} all={all} depth={depth + 1} />
            ))}
        </div>
    );
}

export default function OrganisationIndex({ company, departments }: OrganisationPageProps) {
    const [view, setView] = useState<'tree' | 'list'>('tree');
    const roots = departments.filter((d) => !d.parent_department_id || !departments.some((p) => p.id === d.parent_department_id));

    return (
        <PlatformLayout
            title="Organisation"
            description="The company's structure — business units, branches, departments, and teams."
            company={company}
            actions={
                <div className="inline-flex rounded-lg border border-slate-200 overflow-hidden text-xs font-semibold">
                    <button
                        onClick={() => setView('tree')}
                        className={`px-3 py-1.5 ${view === 'tree' ? 'bg-brand-900 text-white' : 'bg-white text-slate-600'}`}
                    >
                        Tree
                    </button>
                    <button
                        onClick={() => setView('list')}
                        className={`px-3 py-1.5 ${view === 'list' ? 'bg-brand-900 text-white' : 'bg-white text-slate-600'}`}
                    >
                        List
                    </button>
                </div>
            }
        >
            <Card
                actions={
                    <Link href={`/platform/companies/${company.id}/departments`}>
                        <SecondaryButton type="button">Add / edit units</SecondaryButton>
                    </Link>
                }
            >
                {departments.length === 0 ? (
                    <EmptyState icon={<BuildingIcon className="w-10 h-10" />} title="No organisation units yet" />
                ) : view === 'tree' ? (
                    roots.map((r) => <TreeNode key={r.id} node={r} all={departments} depth={0} />)
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {departments.map((d) => (
                            <li key={d.id} className="flex items-center justify-between py-2.5">
                                <span className="text-sm font-medium text-slate-800">{d.name}</span>
                                <div className="flex items-center gap-2">
                                    <Badge tone="neutral">{UNIT_LABEL[d.unit_type]}</Badge>
                                    <span className="text-xs text-slate-400">{d.member_count} member{d.member_count === 1 ? '' : 's'}</span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
