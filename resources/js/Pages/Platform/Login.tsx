import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import PasswordInput from '@/Components/PasswordInput';
import AuthCard from '@/Components/Platform/AuthCard';

const PLATFORM_PASSWORD_INPUT_CLASS =
    'w-full rounded-xl border border-transparent px-4 py-3 text-sm bg-slate-100 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition pr-10';

interface PlatformLoginPageProps {
    flash: {
        error?: string | null;
        success?: string | null;
    };
    [key: string]: unknown;
}

/**
 * Restored to Performix branding via the shared AuthCard shell (matching
 * ForgotPassword.tsx) -- a prior request had made this an exact copy of the
 * legacy login.blade.php's own "RCG KPI Dashboard" branding, but the
 * Platform is a genuinely separate, current system from that legacy page
 * (see AuthCard's own docblock) and / and /login now default here, so this
 * is the first thing most users see. No title/description is passed to
 * AuthCard -- the Performix logo block is the only heading this page shows.
 */
export default function PlatformLogin() {
    const { flash } = usePage<PlatformLoginPageProps>().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/login');
    };

    return (
        <>
            <Head title="Sign in" />

            <AuthCard footer="Contact your administrator if you do not have login access.">
                {flash.error && (
                    <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}
                {flash.success && (
                    <div className="mb-4 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                        {flash.success}
                    </div>
                )}
                {errors.email && (
                    <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {errors.email}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">Email</label>
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="w-full rounded-xl border border-transparent px-4 py-3 text-sm bg-slate-100 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition"
                            placeholder="name@richworks.com"
                            required
                            autoFocus
                        />
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-1.5">
                            <label className="block text-xs font-bold text-slate-700">Password</label>
                            <Link href="/platform/forgot-password" className="text-[11px] font-bold text-indigo-600 hover:text-indigo-700 transition">
                                Forgot password?
                            </Link>
                        </div>
                        <PasswordInput
                            name="password"
                            value={data.password}
                            onChange={(v) => setData('password', v)}
                            placeholder="Enter your password"
                            className={PLATFORM_PASSWORD_INPUT_CLASS}
                            iconHoverClassName="hover:text-indigo-600"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 py-3 text-sm font-black text-white transition shadow-md shadow-indigo-500/30 hover:-translate-y-0.5 disabled:opacity-60 disabled:hover:translate-y-0"
                    >
                        Login
                    </button>
                </form>
            </AuthCard>
        </>
    );
}
