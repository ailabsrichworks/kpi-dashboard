import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface LoginPageProps {
    flash: {
        error?: string | null;
        success?: string | null;
    };
    [key: string]: unknown;
}

export default function Login() {
    const { flash } = usePage<LoginPageProps>().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });
    const [showPassword, setShowPassword] = useState(false);

    const firstError = Object.values(errors)[0];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Login">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <div
                className="min-h-screen flex items-center justify-center p-6 relative overflow-hidden"
                style={{
                    fontFamily: "'Inter', sans-serif",
                    background:
                        'radial-gradient(circle at top left, rgba(196,184,150,.35), transparent 32%), radial-gradient(circle at bottom right, rgba(166,147,116,.25), transparent 38%), linear-gradient(135deg, #F1EBE0 0%, #E9E0D1 45%, #DED2BC 78%, #F1EBE0 100%)',
                }}
            >
                <div className="pointer-events-none absolute -top-16 -left-16 w-72 h-72 rounded-full bg-[#C9B896]/25 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-20 -right-10 w-80 h-80 rounded-full bg-[#C9B896]/20 blur-3xl" />

                <div className="relative w-full max-w-sm">
                    <div className="bg-white rounded-2xl overflow-hidden shadow-[0_25px_70px_rgba(0,0,0,.18)] border-t-[3px] border-t-[#C9B896]">
                        <div className="px-8 pt-8 pb-6">
                            <div className="flex flex-col items-center text-center mb-6">
                                <h1 className="text-lg font-black text-slate-900 leading-tight">
                                    RCG KPI Dashboard
                                </h1>
                                <p className="text-[10px] font-bold text-[#A6906F] uppercase tracking-[0.16em] mt-1">
                                    Performance System
                                </p>
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

                            {firstError && (
                                <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                                    {firstError}
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 mb-1.5">
                                        Email
                                    </label>
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
                                        <label className="block text-xs font-bold text-slate-700">
                                            Password
                                        </label>
                                        <a
                                            href="/forgot-password"
                                            className="text-[11px] font-bold text-[#A6906F] hover:text-[#8B7355] transition"
                                        >
                                            Forgot password?
                                        </a>
                                    </div>
                                    <div className="relative">
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="Enter your password"
                                            required
                                            className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm bg-slate-50 focus:bg-white focus:ring-2 focus:ring-[#C9B896] focus:border-[#C9B896] focus:outline-none transition pr-10"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((v) => !v)}
                                            className="absolute right-0 top-0 h-full px-3 flex items-center text-slate-400 hover:text-[#A6906F] transition"
                                            aria-label="Show password"
                                            tabIndex={-1}
                                        >
                                            {showPassword ? (
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                </svg>
                                            ) : (
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            )}
                                        </button>
                                    </div>
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
                            <p className="text-[10px] text-[#6B5D4F] font-semibold">
                                Please contact BTS if you do not have login access.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
