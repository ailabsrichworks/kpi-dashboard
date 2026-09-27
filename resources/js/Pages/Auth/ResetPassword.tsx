import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import PasswordInput from '../../Components/PasswordInput';
import { SharedPageProps } from '../../types';

interface ResetPasswordPageProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordPageProps) {
    const { flash } = usePage<SharedPageProps>().props;
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const firstError = Object.values(errors)[0];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/reset-password');
    };

    return (
        <>
            <Head title="Reset Password · RichWorks KPI" />

            <div className="min-h-screen bg-slate-100 flex items-center justify-center p-6">
                <div className="w-full max-w-sm bg-white rounded-2xl shadow-lg p-7">
                    <div className="mb-6">
                        <h1 className="text-base font-bold text-slate-900">Reset Password</h1>
                        <p className="text-xs text-slate-500">Choose a new password below</p>
                    </div>

                    {flash.error && <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{flash.error}</div>}
                    {firstError && <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{firstError}</div>}

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:ring-2 focus:ring-slate-800 focus:outline-none"
                                placeholder="name@richworks.com"
                                required
                                autoFocus
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                            <PasswordInput name="password" value={data.password} onChange={(v) => setData('password', v)} placeholder="At least 8 characters" minLength={8} />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
                            <PasswordInput
                                name="password_confirmation"
                                value={data.password_confirmation}
                                onChange={(v) => setData('password_confirmation', v)}
                                placeholder="Re-enter new password"
                                minLength={8}
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-xl bg-[#06142f] py-3 text-sm font-semibold text-white hover:bg-[#0b1f49] transition disabled:opacity-60"
                        >
                            Reset Password
                        </button>
                    </form>

                    <div className="mt-5 text-center">
                        <Link href="/login" className="text-xs font-semibold text-[#4a7c6b] hover:text-[#2d5548]">
                            ← Back to login
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
