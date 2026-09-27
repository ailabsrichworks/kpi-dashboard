import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import PasswordInput from '@/Components/PasswordInput';
import AuthCard from '@/Components/Platform/AuthCard';

const PLATFORM_PASSWORD_INPUT_CLASS =
    'w-full rounded-xl border border-transparent px-4 py-3 text-sm bg-slate-100 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition pr-10';

interface SetPasswordPageProps {
    email: string | null;
    flash: { error?: string | null; success?: string | null };
    errors: { password?: string };
    [key: string]: unknown;
}

export default function SetPassword({ email }: SetPasswordPageProps) {
    const { flash, errors } = usePage<SetPasswordPageProps>().props;
    const { data, setData, post, processing } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/invite/set-password');
    };

    return (
        <>
            <Head title="Set Your Password" />

            <AuthCard
                title="Welcome to Performix"
                description={email ? `Set a password for ${email} to finish setting up your account.` : 'Set a password to finish setting up your account.'}
            >
                {flash.error && (
                    <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}
                {errors.password && (
                    <div className="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {errors.password}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">New password</label>
                        <PasswordInput
                            name="password"
                            value={data.password}
                            onChange={(v) => setData('password', v)}
                            minLength={8}
                            autoFocus
                            className={PLATFORM_PASSWORD_INPUT_CLASS}
                            iconHoverClassName="hover:text-indigo-600"
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">Confirm password</label>
                        <PasswordInput
                            name="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(v) => setData('password_confirmation', v)}
                            minLength={8}
                            className={PLATFORM_PASSWORD_INPUT_CLASS}
                            iconHoverClassName="hover:text-indigo-600"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 py-3 text-sm font-black text-white transition shadow-md shadow-indigo-500/30 hover:-translate-y-0.5 disabled:opacity-60 disabled:hover:translate-y-0"
                    >
                        Set password &amp; continue
                    </button>
                </form>
            </AuthCard>
        </>
    );
}
