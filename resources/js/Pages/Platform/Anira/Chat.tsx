import axios from 'axios';
import { FormEventHandler, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { SparklesIcon } from '@/Components/Platform/Icons';
import { PrimaryButton } from '@/Components/Platform/ui';

interface Me {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface Company {
    id: string;
    name: string;
    code: string;
    status: string;
}

interface AniraChatPageProps {
    me: Me;
    companies: Company[];
    [key: string]: unknown;
}

interface Message {
    role: 'user' | 'assistant' | 'error';
    content: string;
}

export default function AniraChat({ me, companies }: AniraChatPageProps) {
    const [companyId, setCompanyId] = useState<string>(companies.length === 1 ? companies[0].id : '');
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        if (!input.trim() || sending) return;

        const question = input.trim();
        setMessages((prev) => [...prev, { role: 'user', content: question }]);
        setInput('');
        setSending(true);

        try {
            const response = await axios.post('/platform/ai/chat', {
                message: question,
                company_id: companyId || undefined,
            });

            if (response.data.success) {
                setMessages((prev) => [...prev, { role: 'assistant', content: response.data.reply }]);
            } else {
                setMessages((prev) => [...prev, { role: 'error', content: response.data.message ?? 'Something went wrong.' }]);
            }
        } catch (err: unknown) {
            const message =
                (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'ANIRA is unavailable. Please try again.';
            setMessages((prev) => [...prev, { role: 'error', content: message }]);
        } finally {
            setSending(false);
        }
    };

    return (
        <PlatformLayout
            title="Ask ANIRA"
            description={`Signed in as ${me.name}. ANIRA only ever sees what you're personally authorized to see — nothing more.`}
            maxWidth="max-w-2xl"
        >
            {companies.length > 1 && (
                <div className="mb-4">
                    <label className="block text-xs font-medium text-slate-600 mb-1">Ask about</label>
                    <select value={companyId} onChange={(e) => setCompanyId(e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All companies you can see</option>
                        {companies.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name} ({c.code})
                            </option>
                        ))}
                    </select>
                </div>
            )}

            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-4 min-h-[320px] flex flex-col gap-3">
                {messages.length === 0 && (
                    <div className="flex flex-col items-center text-center py-10 text-slate-400">
                        <SparklesIcon className="w-8 h-8 mb-2 text-slate-300" />
                        <p className="text-sm">Ask ANIRA about your KPIs, targets, or recent submissions.</p>
                    </div>
                )}
                {messages.map((m, i) => (
                    <div
                        key={i}
                        className={`max-w-[85%] rounded-2xl px-4 py-2.5 text-sm ${
                            m.role === 'user'
                                ? 'self-end bg-brand-900 text-white'
                                : m.role === 'error'
                                  ? 'self-start bg-red-50 border border-red-200 text-red-700'
                                  : 'self-start bg-slate-100 text-slate-800'
                        }`}
                    >
                        {m.content}
                    </div>
                ))}
                {sending && (
                    <div className="self-start flex items-center gap-1.5 text-xs text-slate-400">
                        <SparklesIcon className="w-3.5 h-3.5" /> ANIRA is thinking…
                    </div>
                )}
            </div>

            <form onSubmit={submit} className="flex items-end gap-2">
                <input
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    placeholder="How is my Customer Satisfaction KPI trending?"
                    className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                <PrimaryButton type="submit" disabled={sending || !input.trim()}>
                    Send
                </PrimaryButton>
            </form>
        </PlatformLayout>
    );
}
