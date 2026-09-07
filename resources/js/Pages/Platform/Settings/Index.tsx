import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { SettingsShell } from '@/Components/Platform/SettingsNav';
import { Badge, Card, StatCard } from '@/Components/Platform/ui';
import { AdjustmentsIcon, BuildingIcon, CreditCardIcon, DocumentDuplicateIcon } from '@/Components/Platform/Icons';

interface Integration {
    name: string;
    configured: boolean;
    detail: string;
}

interface SettingsIndexProps {
    stats: {
        companies: number;
        platform_admins: number;
        kpi_templates: number;
        subscription_plans: number;
    };
    integrations: Integration[];
    [key: string]: unknown;
}

export default function SettingsIndex({ stats, integrations }: SettingsIndexProps) {
    return (
        <PlatformLayout
            title="Settings"
            description="Platform-wide configuration for Performix HQ — company-level settings live on each company's own pages."
            maxWidth="max-w-6xl"
        >
            <SettingsShell>
                <div className="space-y-6">
                    <Card title="Platform at a glance">
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <StatCard label="Companies" value={stats.companies} icon={<BuildingIcon className="w-4 h-4" />} />
                            <StatCard label="Platform Admins" value={stats.platform_admins} icon={<AdjustmentsIcon className="w-4 h-4" />} />
                            <StatCard label="KPI Templates" value={stats.kpi_templates} icon={<DocumentDuplicateIcon className="w-4 h-4" />} />
                            <StatCard label="Subscription Plans" value={stats.subscription_plans} icon={<CreditCardIcon className="w-4 h-4" />} />
                        </div>
                    </Card>

                    <Card
                        title="Integration status"
                        description="Configured via environment variables — see Integrations for detail."
                        actions={
                            <Link href="/platform/settings/integrations" className="text-xs font-semibold text-brand-800 hover:underline">
                                View all
                            </Link>
                        }
                    >
                        <ul className="divide-y divide-slate-100">
                            {integrations.map((i) => (
                                <li key={i.name} className="flex items-center justify-between py-2.5">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-800">{i.name}</p>
                                        <p className="text-xs text-slate-400">{i.detail}</p>
                                    </div>
                                    <Badge tone={i.configured ? 'success' : 'neutral'}>{i.configured ? 'Configured' : 'Not configured'}</Badge>
                                </li>
                            ))}
                        </ul>
                    </Card>

                    <Card title="Where things live">
                        <p className="text-sm text-slate-600 leading-relaxed">
                            This hub covers platform-wide settings only. Company-specific configuration — branding, KPI structure,
                            reporting lines, review settings — is managed from each company's own Onboarding and setup pages, not here.
                        </p>
                    </Card>
                </div>
            </SettingsShell>
        </PlatformLayout>
    );
}
