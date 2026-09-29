import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEventHandler, useState } from 'react';
import PasswordInput from '@/Components/PasswordInput';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, PrimaryButton } from '@/Components/Platform/ui';

const PLATFORM_PASSWORD_INPUT_CLASS = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm pr-10';

interface PlatformUser {
    id: string;
    name: string;
    email: string;
    is_super_admin: boolean;
    company_memberships: Array<{
        company_id: string;
        role: string;
        companies: { name: string; code: string };
    }>;
}

interface TelegramLinkState {
    linked: boolean;
    username: string | null;
}

interface ThemePreferences {
    theme_bg: string | null;
    theme_card: string | null;
    theme_accent: string | null;
    theme_accent2: string | null;
    theme_border: string | null;
    theme_text: string | null;
    theme_sidebar_bg: string | null;
    theme_sidebar_accent: string | null;
    theme_sidebar_text: string | null;
    theme_font_family: string | null;
    theme_font_size: string | null;
}

interface ProfilePageProps {
    me: PlatformUser;
    telegram: TelegramLinkState;
    theme: ThemePreferences;
    flash: { error?: string | null; success?: string | null };
    [key: string]: unknown;
}

const ROLE_LABEL: Record<string, string> = {
    company_admin: 'Company Admin',
    department_admin: 'Department Admin',
    department_user: 'Department User',
};

function ChangePasswordForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/profile/password', { onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-4 max-w-sm">
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">New password</label>
                <PasswordInput
                    name="password"
                    value={data.password}
                    onChange={(v) => setData('password', v)}
                    minLength={8}
                    className={PLATFORM_PASSWORD_INPUT_CLASS}
                    iconHoverClassName="hover:text-slate-700"
                />
                {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Confirm new password</label>
                <PasswordInput
                    name="password_confirmation"
                    value={data.password_confirmation}
                    onChange={(v) => setData('password_confirmation', v)}
                    minLength={8}
                    className={PLATFORM_PASSWORD_INPUT_CLASS}
                    iconHoverClassName="hover:text-slate-700"
                />
            </div>
            <PrimaryButton type="submit" disabled={processing}>
                Update password
            </PrimaryButton>
        </form>
    );
}

function TelegramSection({ telegram }: { telegram: TelegramLinkState }) {
    const [code, setCode] = useState<string | null>(null);
    const [deepLink, setDeepLink] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const generateCode = async () => {
        setLoading(true);
        try {
            const { data } = await axios.post('/platform/telegram/link-code');
            setCode(data.code);
            setDeepLink(data.bot_deep_link);
        } finally {
            setLoading(false);
        }
    };

    const disconnect = () => {
        router.post('/platform/telegram/disconnect', {}, { onSuccess: () => setCode(null) });
    };

    return (
        <Card title="Telegram" className="mb-6">
            {telegram.linked ? (
                <div>
                    <p className="text-sm text-slate-600 mb-3">
                        Connected as <span className="font-semibold text-slate-800">@{telegram.username}</span>. You'll
                        receive KPI reminders here, scoped to exactly what you're authorized to see.
                    </p>
                    <button onClick={disconnect} className="text-sm font-semibold text-red-600 hover:underline">
                        Disconnect Telegram
                    </button>
                </div>
            ) : (
                <div>
                    <p className="text-sm text-slate-500 mb-3">
                        Connect Telegram to get KPI reminders — every message is generated from your own authorized
                        data, never another company's.
                    </p>
                    {code && deepLink ? (
                        <div className="rounded-lg bg-slate-50 px-4 py-3 text-sm">
                            <p className="text-slate-600 mb-2">
                                Open Telegram and tap the link below (valid for 10 minutes):
                            </p>
                            <a href={deepLink} target="_blank" rel="noreferrer" className="font-semibold text-brand-900 hover:underline break-all">
                                {deepLink}
                            </a>
                        </div>
                    ) : (
                        <PrimaryButton onClick={generateCode} disabled={loading}>
                            {loading ? 'Generating…' : 'Connect Telegram'}
                        </PrimaryButton>
                    )}
                </div>
            )}
        </Card>
    );
}

const FONT_FAMILIES = ['Inter', 'Poppins', 'Roboto', 'Nunito', 'Merriweather', 'Fira Code'];
const FONT_SIZES: Array<{ value: string; label: string }> = [
    { value: 'sm', label: 'Small' },
    { value: 'md', label: 'Medium' },
    { value: 'lg', label: 'Large' },
];

const COLOR_FIELDS: Array<{ key: keyof ThemePreferences; label: string; fallback: string }> = [
    { key: 'theme_bg', label: 'Page background', fallback: '#F5F5F3' },
    { key: 'theme_card', label: 'Card background', fallback: '#FFFFFF' },
    { key: 'theme_accent', label: 'Accent', fallback: '#D4AF37' },
    { key: 'theme_accent2', label: 'Accent (secondary)', fallback: '#7A0019' },
    { key: 'theme_border', label: 'Borders', fallback: '#E5E7EB' },
    { key: 'theme_text', label: 'Text', fallback: '#1F2937' },
    { key: 'theme_sidebar_bg', label: 'Sidebar background', fallback: '#111111' },
    { key: 'theme_sidebar_accent', label: 'Sidebar accent', fallback: '#D4AF37' },
    { key: 'theme_sidebar_text', label: 'Sidebar text', fallback: '#FFFFFF' },
];

/**
 * Ports legacy's Account Settings appearance theme — see the migration's own
 * docblock (2026_09_29_100000_add_theme_preferences_to_users.php). Purely
 * cosmetic self-service, same field set as legacy exactly. Applying these
 * live across every Platform page (not just previewing them here) is future
 * work — this closes the "there's nowhere to set it" gap the sidebar/nav
 * parity audit found; PlatformLayout consuming these values is a separate,
 * later change.
 */
function ThemeForm({ theme }: { theme: ThemePreferences }) {
    const { data, setData, post, processing } = useForm({
        theme_bg: theme.theme_bg ?? '',
        theme_card: theme.theme_card ?? '',
        theme_accent: theme.theme_accent ?? '',
        theme_accent2: theme.theme_accent2 ?? '',
        theme_border: theme.theme_border ?? '',
        theme_text: theme.theme_text ?? '',
        theme_sidebar_bg: theme.theme_sidebar_bg ?? '',
        theme_sidebar_accent: theme.theme_sidebar_accent ?? '',
        theme_sidebar_text: theme.theme_sidebar_text ?? '',
        theme_font_family: theme.theme_font_family ?? '',
        theme_font_size: theme.theme_font_size ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/profile/theme', { preserveScroll: true });
    };

    const reset = () => {
        setData({
            theme_bg: '', theme_card: '', theme_accent: '', theme_accent2: '', theme_border: '', theme_text: '',
            theme_sidebar_bg: '', theme_sidebar_accent: '', theme_sidebar_text: '', theme_font_family: '', theme_font_size: '',
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                {COLOR_FIELDS.map(({ key, label, fallback }) => (
                    <div key={key}>
                        <label className="block text-xs font-medium text-slate-600 mb-1">{label}</label>
                        <div className="flex items-center gap-2">
                            <input
                                type="color"
                                value={(data[key] as string) || fallback}
                                onChange={(e) => setData(key, e.target.value)}
                                className="w-9 h-9 rounded-lg border border-slate-300 cursor-pointer"
                            />
                            <span className="text-xs text-slate-400">{(data[key] as string) || 'Default'}</span>
                        </div>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-2 gap-4 max-w-sm">
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Font family</label>
                    <select
                        value={data.theme_font_family}
                        onChange={(e) => setData('theme_font_family', e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="">Default</option>
                        {FONT_FAMILIES.map((f) => (
                            <option key={f} value={f}>{f}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Font size</label>
                    <select
                        value={data.theme_font_size}
                        onChange={(e) => setData('theme_font_size', e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >
                        <option value="">Default</option>
                        {FONT_SIZES.map((f) => (
                            <option key={f.value} value={f.value}>{f.label}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="flex items-center gap-3">
                <PrimaryButton type="submit" disabled={processing}>Save appearance</PrimaryButton>
                <button type="button" onClick={reset} className="text-xs font-semibold text-slate-400 hover:text-slate-600">
                    Reset to default
                </button>
            </div>
        </form>
    );
}

export default function Profile({ me, telegram, theme }: ProfilePageProps) {
    return (
        <PlatformLayout title="My Profile" description="Your account, your access level, and your notification preferences.">
            <Card title="Account" className="mb-6">
                <dl className="grid grid-cols-2 gap-y-2 text-sm max-w-sm">
                    <dt className="text-slate-400">Name</dt>
                    <dd className="text-slate-800 font-medium">{me.name}</dd>
                    <dt className="text-slate-400">Email</dt>
                    <dd className="text-slate-800 font-medium">{me.email}</dd>
                    <dt className="text-slate-400">Access level</dt>
                    <dd>
                        <Badge tone={me.is_super_admin ? 'brand' : 'neutral'}>
                            {me.is_super_admin ? 'Richworks Super Admin' : 'Company user'}
                        </Badge>
                    </dd>
                </dl>
            </Card>

            <TelegramSection telegram={telegram} />

            {!me.is_super_admin && (
                <Card title="Company memberships" className="mb-6">
                    {me.company_memberships.length === 0 ? (
                        <p className="text-sm text-slate-400">No company memberships.</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {me.company_memberships.map((m) => (
                                <li key={m.company_id} className="py-2 flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-800">{m.companies.name}</p>
                                        <p className="text-xs text-slate-400">{m.companies.code}</p>
                                    </div>
                                    <Badge>{ROLE_LABEL[m.role] ?? m.role}</Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            )}

            <Card title="Change password" className="mb-6">
                <ChangePasswordForm />
            </Card>

            <Card title="Appearance" description="Personalize your own colors and font — only visible to you.">
                <ThemeForm theme={theme} />
            </Card>
        </PlatformLayout>
    );
}
