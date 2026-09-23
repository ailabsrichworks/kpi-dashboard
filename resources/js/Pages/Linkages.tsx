import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import LinkageCard, { LinkageEntry } from './Linkages/LinkageCard';
import { LINKAGE_CATEGORIES, LINKAGE_SUBCATEGORIES } from '../config/linkageCategories';
import { SharedPageProps } from '../types';

interface DirectReport {
    id: string;
    short_name?: string;
    role?: string;
}

interface LinkagesPageProps {
    fy: string;
    directReports: DirectReport[];
    myLinkageMap: LinkageEntry[];
    outgoingWithCoverage: LinkageEntry[];
    hasAnyLinkage: boolean;
    canAssignTarget: boolean;
}

export default function Linkages({ fy, directReports, myLinkageMap, outgoingWithCoverage, hasAnyLinkage, canAssignTarget }: LinkagesPageProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        assignee_id: '',
        category: LINKAGE_CATEGORIES[0],
        sub_category: LINKAGE_SUBCATEGORIES[LINKAGE_CATEGORIES[0]][0],
        unit: 'number' as 'number' | 'currency' | 'percentage',
        assigned_target: '',
    });

    function handleCategoryChange(category: string) {
        setData((prev) => ({ ...prev, category, sub_category: LINKAGE_SUBCATEGORIES[category]?.[0] ?? '' }));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/linkages', {
            onSuccess: () => {
                reset();
                setShowForm(false);
            },
        });
    }

    function handleDelete(id: string) {
        if (window.confirm('Remove this linkage?')) {
            router.delete(`/linkages/${id}`);
        }
    }

    return (
        <AppLayout pageTitle="Target Linkages" pageSubtitle={`Cascading targets · ${fy}`}>
            <Head title="Target Linkages" />

            <div className="px-4 pt-4 pb-4 space-y-3">
                {flash.success && <div className="bg-emerald-50 text-emerald-700 px-3 py-2 rounded-xl text-xs border border-emerald-200">{flash.success}</div>}
                {flash.error && <div className="bg-red-50 text-red-700 px-3 py-2 rounded-xl text-xs border border-red-200">{flash.error}</div>}

                <div className="bg-white rounded-2xl border border-[#E5E7EB] border-l-[4px] border-l-[#D4AF37] overflow-hidden">
                    <div className="theme-header-banner flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#1A0A0A] to-[#7A0019]">
                        <div>
                            <h2 className="text-sm font-black text-white">KPI Target Linkages</h2>
                            <p className="text-[10px] text-white/70 mt-0.5">Cascading targets · {fy}</p>
                        </div>
                        {canAssignTarget && (
                            <button
                                type="button"
                                onClick={() => setShowForm((v) => !v)}
                                className="px-3 py-1.5 bg-white/15 hover:bg-white/25 text-white rounded-xl text-xs font-black transition border border-white/20"
                            >
                                + Assign Target
                            </button>
                        )}
                    </div>

                    {canAssignTarget && showForm && (
                        <div className="border-b border-[#E5E7EB] bg-slate-50 px-4 py-3">
                            <form onSubmit={submit}>
                                <p className="text-[9px] font-black text-[#B8860B] uppercase mb-2">New Cascading Target</p>
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 items-end">
                                    <div>
                                        <label className="text-[9px] font-black text-slate-400 uppercase block mb-1">Person</label>
                                        <select
                                            required
                                            value={data.assignee_id}
                                            onChange={(e) => setData('assignee_id', e.target.value)}
                                            className="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none"
                                        >
                                            <option value="">Select...</option>
                                            {directReports.map((dr) => (
                                                <option key={dr.id} value={dr.id}>
                                                    {dr.short_name} ({dr.role})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-[9px] font-black text-slate-400 uppercase block mb-1">Category</label>
                                        <select
                                            required
                                            value={data.category}
                                            onChange={(e) => handleCategoryChange(e.target.value)}
                                            className="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none"
                                        >
                                            {LINKAGE_CATEGORIES.map((cat) => (
                                                <option key={cat} value={cat}>
                                                    {cat}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-[9px] font-black text-slate-400 uppercase block mb-1">Sub Category</label>
                                        <select
                                            required
                                            value={data.sub_category}
                                            onChange={(e) => setData('sub_category', e.target.value)}
                                            className="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none"
                                        >
                                            {(LINKAGE_SUBCATEGORIES[data.category] ?? []).map((sub) => (
                                                <option key={sub} value={sub}>
                                                    {sub}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-[9px] font-black text-slate-400 uppercase block mb-1">Unit</label>
                                        <select
                                            required
                                            value={data.unit}
                                            onChange={(e) => setData('unit', e.target.value as typeof data.unit)}
                                            className="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none"
                                        >
                                            <option value="number">Number</option>
                                            <option value="currency">Currency (RM)</option>
                                            <option value="percentage">Percentage (%)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-[9px] font-black text-slate-400 uppercase block mb-1">Annual Target</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            placeholder="0"
                                            value={data.assigned_target}
                                            onChange={(e) => setData('assigned_target', e.target.value)}
                                            className="w-full rounded-xl border border-[#E5E7EB] bg-white px-2 py-2 text-xs font-bold text-slate-700 focus:border-[#D4AF37] focus:outline-none"
                                        />
                                    </div>
                                    <div className="flex gap-1.5">
                                        <button type="submit" disabled={processing} className="flex-1 px-3 py-2 bg-[#D4AF37] text-white rounded-xl text-xs font-black transition disabled:opacity-60">
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShowForm(false)}
                                            className="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-600 rounded-xl text-xs font-black transition"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    )}

                    <div className="p-4 bg-white">
                        {!hasAnyLinkage ? (
                            <p className="text-xs text-slate-400 text-center py-2">No linkage targets yet. Use "+ Assign Target" to assign a cascading target to your team.</p>
                        ) : (
                            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                {myLinkageMap.length > 0 && (
                                    <div>
                                        <p className="text-[9px] font-black text-[#B8860B] uppercase tracking-wider mb-2">Targets Assigned to Me</p>
                                        <div className="space-y-2">
                                            {myLinkageMap.map((lnk) => (
                                                <LinkageCard key={lnk.id} linkage={lnk} variant="incoming" />
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {outgoingWithCoverage.length > 0 && (
                                    <div>
                                        <p className="text-[9px] font-black text-[#B8860B] uppercase tracking-wider mb-2">Targets I Assigned</p>
                                        <div className="space-y-2">
                                            {outgoingWithCoverage.map((lnk) => (
                                                <LinkageCard key={lnk.id} linkage={lnk} variant="outgoing" onDelete={() => handleDelete(lnk.id)} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
