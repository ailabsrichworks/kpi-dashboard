import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, InfoTooltip, PrimaryButton } from '@/Components/Platform/ui';

interface Company {
    id: string;
    name: string;
    code: string;
    display_name: string | null;
    primary_color: string | null;
    secondary_color: string | null;
}

interface SettingsPageProps {
    company: Company;
    [key: string]: unknown;
}

const DEFAULT_PRIMARY = '#0F172A';
const DEFAULT_SECONDARY = '#6B9080';

export default function CompanySettings({ company }: SettingsPageProps) {
    const { data, setData, post, processing } = useForm({
        display_name: company.display_name ?? '',
        primary_color: company.primary_color ?? DEFAULT_PRIMARY,
        secondary_color: company.secondary_color ?? DEFAULT_SECONDARY,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${company.id}/branding`);
    };

    return (
        <PlatformLayout
            title="Settings"
            description={`Branding for ${company.name} — the display name and colors shown on this company's own pages.`}
            company={company}
        >
            <Card className="max-w-xl">
                <form onSubmit={submit} className="space-y-5">
                    <div>
                        <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                            Display name
                            <InfoTooltip text="Shown in place of the legal company name once branded pages use it. Leave blank to keep using the legal name everywhere." />
                        </label>
                        <input
                            value={data.display_name}
                            onChange={(e) => setData('display_name', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            placeholder={company.name}
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">Primary color</label>
                            <div className="flex items-center gap-2">
                                <input
                                    type="color"
                                    value={data.primary_color}
                                    onChange={(e) => setData('primary_color', e.target.value)}
                                    className="h-9 w-9 rounded border border-slate-300 p-0.5"
                                />
                                <input
                                    value={data.primary_color}
                                    onChange={(e) => setData('primary_color', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"
                                />
                            </div>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">Secondary color</label>
                            <div className="flex items-center gap-2">
                                <input
                                    type="color"
                                    value={data.secondary_color}
                                    onChange={(e) => setData('secondary_color', e.target.value)}
                                    className="h-9 w-9 rounded border border-slate-300 p-0.5"
                                />
                                <input
                                    value={data.secondary_color}
                                    onChange={(e) => setData('secondary_color', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-100 p-4 flex items-center gap-3">
                        <div
                            className="h-10 w-10 rounded-lg flex-none flex items-center justify-center text-sm font-bold text-white"
                            style={{ background: data.primary_color }}
                        >
                            {(data.display_name || company.name).charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-bold text-slate-800 truncate">{data.display_name || company.name}</p>
                            <p className="text-xs" style={{ color: data.secondary_color }}>
                                Preview of your brand tile
                            </p>
                        </div>
                    </div>

                    <PrimaryButton type="submit" disabled={processing}>
                        Save settings
                    </PrimaryButton>
                </form>
            </Card>
        </PlatformLayout>
    );
}
