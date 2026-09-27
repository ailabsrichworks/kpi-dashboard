import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import PasswordInput from '@/Components/PasswordInput';

const LOGIN_BG_STYLE = {
    background:
        'radial-gradient(circle at top left, rgba(196,184,150,.35), transparent 32%), ' +
        'radial-gradient(circle at bottom right, rgba(166,147,116,.25), transparent 38%), ' +
        'linear-gradient(135deg, #F1EBE0 0%, #E9E0D1 45%, #DED2BC 78%, #F1EBE0 100%)',
};

const PLATFORM_PASSWORD_INPUT_CLASS =
    'w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-[#C9B896] focus:border-[#C9B896] focus:outline-none transition pr-10';

interface PlatformLoginPageProps {
    flash: {
        error?: string | null;
        success?: string | null;
    };
    [key: string]: unknown;
}

/**
 * A deliberate, full exact copy of resources/views/auth/login.blade.php --
 * same title/tagline/footer copy, not just the same colors -- per an
 * explicit choice to have this page and /login look identical. (An earlier
 * version kept "Performix" branding and a cross-link to /login specifically
 * to prevent the two being mixed up; both were removed on request.)
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
            <Head title="Login" />

            <div className="min-h-screen flex items-center justify-center p-6 relative overflow-hidden" style={LOGIN_BG_STYLE}>
                <div className="pointer-events-none absolute -top-16 -left-16 w-72 h-72 rounded-full bg-[#C9B896]/25 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-20 -right-10 w-80 h-80 rounded-full bg-[#C9B896]/20 blur-3xl" />

                <div className="relative w-full max-w-sm">
                    <div className="bg-white rounded-2xl overflow-hidden shadow-[0_25px_70px_rgba(0,0,0,.18)] border-t-[3px] border-t-[#C9B896]">
                        <div className="px-8 pt-8 pb-6">
                            <div className="flex flex-col items-center text-center mb-6">
                                <h1 className="text-lg font-black text-slate-900 leading-tight">RCG KPI Dashboard</h1>
                                <p className="text-[10px] font-bold text-[#A6906F] uppercase tracking-[0.16em] mt-1">Performance System</p>
                            </div>

                            {flash.error && (
                                <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                                    {flash.error}
                                </div>
                            )}
                            {flash.success && (
                                <div className="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
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
                                        className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-[#C9B896] focus:border-[#C9B896] focus:outline-none transition"
                                        placeholder="name@richworks.com"
                                        required
                                        autoFocus
                                    />
                                </div>

                                <div>
                                    <div className="flex items-center justify-between mb-1.5">
                                        <label className="block text-xs font-bold text-slate-700">Password</label>
                                        <Link href="/platform/forgot-password" className="text-[11px] font-bold text-[#A6906F] hover:text-[#8B7355] transition">
                                            Forgot password?
                                        </Link>
                                    </div>
                                    <PasswordInput
                                        name="password"
                                        value={data.password}
                                        onChange={(v) => setData('password', v)}
                                        placeholder="Enter your password"
                                        className={PLATFORM_PASSWORD_INPUT_CLASS}
                                        iconHoverClassName="hover:text-[#A6906F]"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-xl bg-[#C9B896] hover:bg-[#BBA57F] py-3 text-sm font-black text-[#3A3128] transition shadow-md hover:-translate-y-0.5 disabled:opacity-60 disabled:hover:translate-y-0"
                                >
                                    Login
                                </button>
                            </form>
                        </div>

                        <div className="bg-gradient-to-r from-[#F1EBE0] to-[#DED2BC] px-8 py-3.5 text-center">
                            <p className="text-[10px] text-[#6B5D4F] font-semibold">Please contact BTS if you do not have login access.</p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
