import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { SettingsShell } from '@/Components/Platform/SettingsNav';
import { Badge, Card } from '@/Components/Platform/ui';

export default function Billing() {
    return (
        <PlatformLayout title="Billing" description="Invoicing and payment processing for subscribed companies." maxWidth="max-w-6xl">
            <SettingsShell>
                <Card>
                    <div className="mb-3">
                        <Badge tone="warning">Not built yet</Badge>
                    </div>
                    <p className="text-sm text-slate-600 leading-relaxed">
                        There is no billing or invoicing system in Performix today — no invoices, no payment processor connection, no
                        record of what a company has actually paid. The subscription <em>plan catalog</em> (pricing tiers, seat/department
                        limits) is real and lives at{' '}
                        <Link href="/platform/subscription-plans" className="font-semibold text-brand-800 hover:underline">
                            Subscription Plans
                        </Link>
                        , and a company's assigned plan is set from its own Companies page — but nothing charges anyone or tracks payment
                        status yet.
                    </p>
                    <p className="mt-4 text-xs text-slate-400">
                        Building real billing means picking a payment processor and deciding how it should interact with company
                        suspension — a product decision, not a page this hub can scaffold ahead of one.
                    </p>
                </Card>
            </SettingsShell>
        </PlatformLayout>
    );
}
