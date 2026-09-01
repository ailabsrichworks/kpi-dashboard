import { ReactNode } from 'react';

/**
 * Shared shell for the three pages someone sees before they're ever
 * authenticated (Login, Forgot Password, Set Password) — no sidebar makes
 * sense here (there's nothing to navigate to yet), but they should still
 * look like the same product as everything behind them, not three
 * independently-styled forms that happen to sit next to each other in the
 * codebase.
 *
 * Visual style: dark backdrop, a blue-to-violet gradient logo mark and top
 * accent strip, indigo/violet accents throughout — this Platform's own
 * identity, deliberately distinct from the legacy app's cream/gold
 * resources/views/auth/login.blade.php rather than matching it.
 */
export default function AuthCard({ title, description, children, footer }: { title?: string; description?: string; children: ReactNode; footer?: ReactNode }) {
    return (
        <div className="min-h-screen flex items-center justify-center p-6 bg-[#0B1120]">
            <div className="relative w-full max-w-sm">
                <div className="bg-white rounded-2xl overflow-hidden shadow-[0_25px_70px_rgba(0,0,0,.45)]">
                    <div className="h-1.5 w-full bg-gradient-to-r from-blue-500 via-indigo-500 to-violet-500" />

                    <div className="px-8 pt-8 pb-6">
                        <div className="flex flex-col items-center text-center mb-6">
                            <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 text-2xl font-black text-white shadow-lg shadow-indigo-500/30 mb-3">
                                P
                            </span>
                            <h1 className="text-2xl font-black text-slate-900 leading-tight">Performix</h1>
                            <p className="text-[10px] font-bold text-indigo-600 uppercase tracking-[0.2em] mt-1">Performance System</p>
                        </div>

                        {title && (
                            <div className="mb-6 text-center">
                                <h2 className="text-base font-bold text-slate-900">{title}</h2>
                                {description && <p className="text-xs text-slate-500 mt-1">{description}</p>}
                            </div>
                        )}

                        {children}
                    </div>

                    {footer && (
                        <div className="bg-slate-50 px-8 py-3.5 text-center text-xs font-semibold text-slate-500">
                            {footer}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
