import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import {
    AdjustmentsIcon,
    BuildingIcon,
    ClipboardCheckIcon,
    CogIcon,
    CreditCardIcon,
    DocumentDuplicateIcon,
    IdentificationIcon,
    PuzzlePieceIcon,
    RocketIcon,
    ShieldCheckIcon,
    UserCircleIcon,
} from './Icons';
import { Badge } from './ui';

/**
 * The settings hub's own second-level navigation — separate from
 * PlatformLayout's persistent sidebar, the same way it stayed separate for
 * every other multi-page area (Onboarding's checklist, People's tabs). Items
 * that already have a real, working screen elsewhere point straight at it
 * (Team & Access → /platform/admins, Modules → /platform/hq/modules, etc.)
 * rather than being rebuilt inside /platform/settings/*.
 */
interface SettingsNavItem {
    key: string;
    label: string;
    href: string;
    icon: ReactNode;
    badge?: string;
}

const NAV_ITEMS: SettingsNavItem[] = [
    { key: 'overview', label: 'Overview', href: '/platform/settings', icon: <CogIcon className="w-[17px] h-[17px]" /> },
    { key: 'profile', label: 'My Profile', href: '/platform/profile', icon: <UserCircleIcon className="w-[17px] h-[17px]" /> },
    { key: 'team', label: 'Team & Access', href: '/platform/admins', icon: <AdjustmentsIcon className="w-[17px] h-[17px]" /> },
    { key: 'companies', label: 'Companies', href: '/platform/companies', icon: <BuildingIcon className="w-[17px] h-[17px]" /> },
    { key: 'modules', label: 'Modules', href: '/platform/hq/modules', icon: <PuzzlePieceIcon className="w-[17px] h-[17px]" /> },
    { key: 'integrations', label: 'Integrations', href: '/platform/settings/integrations', icon: <RocketIcon className="w-[17px] h-[17px]" /> },
    { key: 'api-keys', label: 'API Keys', href: '/platform/settings/api-keys', icon: <IdentificationIcon className="w-[17px] h-[17px]" /> },
    { key: 'billing', label: 'Billing', href: '/platform/settings/billing', icon: <CreditCardIcon className="w-[17px] h-[17px]" />, badge: 'Soon' },
    { key: 'subscription-plans', label: 'Subscription Plans', href: '/platform/subscription-plans', icon: <CreditCardIcon className="w-[17px] h-[17px]" /> },
    { key: 'kpi-templates', label: 'KPI Templates', href: '/platform/kpi-templates', icon: <DocumentDuplicateIcon className="w-[17px] h-[17px]" /> },
    { key: 'compliance', label: 'Compliance', href: '/platform/settings/compliance', icon: <ShieldCheckIcon className="w-[17px] h-[17px]" /> },
    { key: 'audit-log', label: 'Audit Log', href: '/platform/audit-log', icon: <ClipboardCheckIcon className="w-[17px] h-[17px]" /> },
];

export function SettingsShell({ children }: { children: ReactNode }) {
    const currentUrl = usePage().url;

    return (
        <div className="lg:flex lg:items-start lg:gap-6">
            <nav className="mb-6 flex-none lg:sticky lg:top-24 lg:w-56">
                <ul className="space-y-0.5">
                    {NAV_ITEMS.map((item) => {
                        const active = currentUrl === item.href;
                        return (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                        active ? 'bg-brand-900 text-white' : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                >
                                    <span className="flex-none">{item.icon}</span>
                                    <span className="flex-1">{item.label}</span>
                                    {item.badge && (
                                        <Badge tone="warning">{item.badge}</Badge>
                                    )}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            <div className="min-w-0 flex-1">{children}</div>
        </div>
    );
}
