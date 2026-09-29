import { ReactNode } from 'react';

const LOGIN_BG_STYLE = {
    background:
        'radial-gradient(circle at top left, rgba(196,184,150,.35), transparent 32%), ' +
        'radial-gradient(circle at bottom right, rgba(166,147,116,.25), transparent 38%), ' +
        'linear-gradient(135deg, #F1EBE0 0%, #E9E0D1 45%, #DED2BC 78%, #F1EBE0 100%)',
};

/**
 * Shared shell for the three pages someone sees before they're ever
 * authenticated (Login, Forgot Password, Set Password) — no sidebar makes
 * sense here (there's nothing to navigate to yet), but they should still
 * look like the same product as everything behind them, not three
 * independently-styled forms that happen to sit next to each other in the
 * codebase.
 *
 * Visual style (cream/gold gradient, blurred corner accents, gold-topped
 * card) intentionally follows the legacy app's own resources/views/auth/
 * login.blade.php — same product family, one consistent pre-auth look.
 * The brand identity stays "Performix" (this is the Platform, a genuinely
 * different, current system from that legacy page) — only the styling was
 * asked to match, not the legacy app's own name/copy.
 */
export default function AuthCard({ title, description, children, footer }: { title: string; description?: string; children: ReactNode; footer?: ReactNode }) {
    return (
        <div className="min-h-screen flex items-center justify-center p-6 relative overflow-hidden" style={LOGIN_BG_STYLE}>
            <div className="pointer-events-none absolute -top-16 -left-16 w-72 h-72 rounded-full bg-[#C9B896]/25 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-20 -right-10 w-80 h-80 rounded-full bg-[#C9B896]/20 blur-3xl" />

            <div className="relative w-full max-w-sm">
                <div className="flex items-center justify-center gap-2.5 mb-6">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#3A3128] text-base font-bold text-[#C9B896]">P</span>
                    <span className="text-slate-900 font-bold text-lg">Performix</span>
                </div>

                <div className="bg-white rounded-2xl overflow-hidden shadow-[0_25px_70px_rgba(0,0,0,.18)] border-t-[3px] border-t-[#C9B896] p-7">
                    <div className="mb-6">
                        <h1 className="text-base font-bold text-slate-900">{title}</h1>
                        {description && <p className="text-xs text-slate-500 mt-1">{description}</p>}
                    </div>
                    {children}
                </div>

                {footer && <div className="mt-5 text-center text-xs text-[#6B5D4F]">{footer}</div>}
            </div>
        </div>
    );
}
