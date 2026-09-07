import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { SettingsShell } from '@/Components/Platform/SettingsNav';
import { Card } from '@/Components/Platform/ui';

export default function Compliance() {
    return (
        <PlatformLayout
            title="Compliance"
            description="What Performix actually guarantees about tenant data today — not a certifications page."
            maxWidth="max-w-6xl"
        >
            <SettingsShell>
                <div className="space-y-5">
                    <Card title="Tenant isolation">
                        <p className="text-sm text-slate-600 leading-relaxed">
                            Every company-owned table carries its own <code className="text-slate-500">company_id</code> and is protected
                            by PostgreSQL Row-Level Security — access is enforced by the database itself, not by application code
                            remembering to filter correctly. A bug in a controller cannot cross a company boundary; the RLS policy is the
                            actual gate.
                        </p>
                        <p className="mt-3 text-sm text-slate-600 leading-relaxed">
                            This is checked continuously, not just asserted: a dedicated test suite
                            (<code className="text-slate-500">database/rls-tests/tenant_isolation.sql</code>) simulates cross-company
                            access attempts against a disposable database and asserts every one is denied, run automatically in CI on
                            every push.
                        </p>
                    </Card>

                    <Card title="Audit trail">
                        <p className="text-sm text-slate-600 leading-relaxed">
                            Admin-level actions — company lifecycle changes, role changes, KPI/target edits, imports, Telegram linking —
                            are recorded in an append-only log with actor, target, and before/after state. Company Admins can see and
                            export their own company's slice; Performix HQ can see the cross-company log.
                        </p>
                        <p className="mt-3 text-sm text-slate-600 leading-relaxed">
                            Stated plainly: there is currently no automated retention or purge policy on this log — entries are kept
                            indefinitely until a retention decision is made.
                        </p>
                    </Card>

                    <Card title="Data storage">
                        <p className="text-sm text-slate-600 leading-relaxed">
                            All application data is stored in a managed PostgreSQL database (Supabase). Backups and point-in-time
                            recovery are configured at the infrastructure level, not inside this application.
                        </p>
                    </Card>
                </div>
            </SettingsShell>
        </PlatformLayout>
    );
}
