import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { SettingsShell } from '@/Components/Platform/SettingsNav';
import { Card } from '@/Components/Platform/ui';

interface ApiKeyRow {
    name: string;
    purpose: string;
    value: string;
}

interface ApiKeysPageProps {
    keys: ApiKeyRow[];
    [key: string]: unknown;
}

export default function ApiKeys({ keys }: ApiKeysPageProps) {
    return (
        <PlatformLayout
            title="API Keys"
            description="Secrets this environment relies on. Values are masked and cannot be edited here."
            maxWidth="max-w-6xl"
        >
            <SettingsShell>
                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                    <th className="pb-2 pr-4">Key</th>
                                    <th className="pb-2 pr-4">Used for</th>
                                    <th className="pb-2">Value</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {keys.map((k) => (
                                    <tr key={k.name}>
                                        <td className="py-3 pr-4 font-mono text-xs text-slate-700">{k.name}</td>
                                        <td className="py-3 pr-4 text-xs text-slate-500">{k.purpose}</td>
                                        <td className="py-3 font-mono text-xs text-slate-600">{k.value}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="mt-5 text-xs text-slate-400 leading-relaxed">
                        These are read directly from the server's environment — there is no in-app secrets store to rotate them from.
                        To change a value, update it in your hosting provider's environment settings and redeploy; run{' '}
                        <code className="text-slate-500">php artisan config:clear</code> afterward if config is cached.
                    </p>
                </Card>
            </SettingsShell>
        </PlatformLayout>
    );
}
