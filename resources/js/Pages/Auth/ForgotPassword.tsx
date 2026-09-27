import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { SharedPageProps } from '../../types';

export default function ForgotPassword() {
    const { flash } = usePage<SharedPageProps>().props;
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const firstError = Object.values(errors)[0];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <>
            <Head title="Forgot Password · RichWorks KPI" />

            <div className="min-h-screen bg-slate-100 flex items-center justify-center p-6">
                <div className="w-full max-w-sm bg-white rounded-2xl shadow-lg p-7">
                    <div className="mb-6">
                        <h1 className="text-base font-bold text-slate-900">Forgot Password</h1>
                        <p className="text-xs text-slate-500">We'll email you a link to reset it</p>
                    </div>

                    {flash.error && <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{flash.error}</div>}
                    {flash.success && <div className="mb-4 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{flash.success}</div>}
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

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-xl bg-[#06142f] py-3 text-sm font-semibold text-white hover:bg-[#0b1f49] transition disabled:opacity-60"
                        >
                            Send Reset Link
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
