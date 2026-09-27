import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { SettingsShell } from '@/Components/Platform/SettingsNav';
import { Badge, Card } from '@/Components/Platform/ui';

interface Integration {
    name: string;
    configured: boolean;
    detail: string;
}

interface IntegrationsPageProps {
    integrations: Integration[];
    [key: string]: unknown;
}

export default function Integrations({ integrations }: IntegrationsPageProps) {
    return (
        <PlatformLayout
            title="Integrations"
            description="External services Performix connects to, and whether each is configured for this environment."
            maxWidth="max-w-6xl"
        >
            <SettingsShell>
                <Card>
                    <ul className="divide-y divide-slate-100">
                        {integrations.map((i) => (
                            <li key={i.name} className="flex items-center justify-between py-4">
                                <div>
                                    <p className="text-sm font-semibold text-slate-800">{i.name}</p>
                                    <p className="text-xs text-slate-400 mt-0.5">{i.detail}</p>
                                </div>
                                <Badge tone={i.configured ? 'success' : 'neutral'}>{i.configured ? 'Configured' : 'Not configured'}</Badge>
                            </li>
                        ))}
                    </ul>
                    <p className="mt-5 text-xs text-slate-400 leading-relaxed">
                        These are set via environment variables on the server, not from this page — there is no per-company integration
                        settings table yet (each of these currently applies platform-wide). To change one, update the corresponding
                        environment variable and redeploy.
                    </p>
                </Card>
            </SettingsShell>
        </PlatformLayout>
    );
}
