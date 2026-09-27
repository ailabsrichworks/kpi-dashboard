import { formatLinkageValue, LinkageUnit } from '../../lib/linkageFormat';

export interface LinkageEntry {
    id: string;
    category: string;
    sub_category: string;
    assigned_target: number;
    unit: LinkageUnit;
    covered: number;
    gap: number;
    pct: number;
    met: boolean;
    assigner_name?: string | null;
    assignee_name?: string | null;
}

interface LinkageCardProps {
    linkage: LinkageEntry;
    variant: 'incoming' | 'outgoing';
    onDelete?: () => void;
}

export default function LinkageCard({ linkage, variant, onDelete }: LinkageCardProps) {
    const met = linkage.met;

    return (
        <div
            className={`p-2.5 rounded-xl border group ${
                variant === 'incoming' ? (met ? 'border-emerald-200 bg-emerald-50' : 'border-[#E5E7EB] bg-[#D4AF37]/5') : 'border-[#E5E7EB] bg-[#D4AF37]/5'
            }`}
        >
            <div className="flex items-center justify-between mb-1.5">
                <div className="min-w-0">
                    {variant === 'incoming' ? (
                        <>
                            <span className="text-xs font-black text-slate-800">{linkage.sub_category}</span>
                            <span className="ml-1.5 text-[9px] text-slate-400">
                                {linkage.category} · from {linkage.assigner_name ?? '-'}
                            </span>
                        </>
                    ) : (
                        <>
                            <span className="text-xs font-black text-slate-800">{linkage.assignee_name ?? '-'}</span>
                            <span className="ml-1.5 text-[9px] text-slate-400">
                                {linkage.sub_category} · {linkage.category}
                            </span>
                        </>
                    )}
                </div>

                {variant === 'incoming' ? (
                    !met ? (
                        <span className="shrink-0 ml-2 text-[9px] font-black px-1.5 py-0.5 rounded-full border bg-[#D4AF37]/10 text-[#B8860B] border-[#E5E7EB]">Gap</span>
                    ) : (
                        <span className="shrink-0 ml-2 text-[9px] font-black px-1.5 py-0.5 rounded-full border bg-emerald-100 text-emerald-700 border-emerald-200">Met ✓</span>
                    )
                ) : (
                    <div className="shrink-0 ml-2 flex items-center gap-1.5">
                        {!met ? (
                            <span className="text-[9px] font-black bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded border border-amber-200">Gap</span>
                        ) : (
                            <span className="text-[9px] font-black bg-emerald-50 text-emerald-600 px-1.5 py-0.5 rounded border border-emerald-200">Met ✓</span>
                        )}
                        {onDelete && (
                            <button
                                type="button"
                                onClick={onDelete}
                                className="text-[9px] text-red-400 hover:text-red-600 font-black opacity-0 group-hover:opacity-100 transition"
                            >
                                ✕
                            </button>
                        )}
                    </div>
                )}
            </div>

            <div className="flex items-center gap-2 mb-1.5">
                <div className="flex-1 h-1.5 rounded-full overflow-hidden bg-slate-100">
                    <div className={`h-1.5 rounded-full ${met ? 'bg-emerald-400' : 'bg-[#D4AF37]'}`} style={{ width: `${linkage.pct}%` }} />
                </div>
                <span className="text-[9px] font-black text-slate-600 w-7 text-right shrink-0">{linkage.pct}%</span>
            </div>

            <div className="flex justify-between text-[9px] text-slate-400">
                <span>
                    Target: <span className="font-black text-slate-700">{formatLinkageValue(linkage.assigned_target, linkage.unit)}</span>
                </span>
                <span>
                    Covered: <span className="font-black text-slate-700">{formatLinkageValue(linkage.covered, linkage.unit)}</span>
                </span>
                {!met && <span className="text-[#B8860B] font-black">Gap: {formatLinkageValue(linkage.gap, linkage.unit)}</span>}
            </div>
        </div>
    );
}
