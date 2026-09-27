import { useEffect, useRef, useState } from 'react';

const CHAT_URL = '/ai/chat';
const SUGGEST_URL = '/ai/suggest-kpi';
const STORAGE_KEY = 'anira_session_v2';

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

interface KpiDraft {
    title?: string;
    description?: string;
    category?: string;
    sub_category?: string;
    unit?: string;
    base_target?: number;
    stretch_target?: number;
    rationale?: string;
    q1?: number;
    q2?: number;
    q3?: number;
    q4?: number;
    [key: string]: unknown;
}

type UiMessage =
    | { type: 'user'; text: string }
    | { type: 'bot'; text: string }
    | { type: 'kpi_card'; kpi: KpiDraft };

declare global {
    interface Window {
        aniraFillKpiForm?: (kpi: KpiDraft) => void;
    }
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function jsonHeaders(): HeadersInit {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function escHtml(str: string | null | undefined): string {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function inlineFormat(str: string): string {
    return escHtml(str)
        .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold">$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code class="bg-slate-200 text-slate-700 px-1 rounded text-[11px]">$1</code>');
}

/** Ported 1:1 from ai-chat-widget.blade.php's formatBotText(). */
function formatBotText(raw: string): string {
    const text = raw.replace(/^#{1,6}\s+/gm, '');
    const lines = text.split('\n');
    const blocks: { type: 'table' | 'text'; lines: string[] }[] = [];
    let current: { type: 'table' | 'text'; lines: string[] } | null = null;

    const isTableRow = (l: string) => l.trim().startsWith('|') && l.trim().endsWith('|');
    const isSeparator = (l: string) => /^\|[\s\-:|]+\|$/.test(l.trim());

    lines.forEach((line) => {
        if (isTableRow(line)) {
            if (!current || current.type !== 'table') {
                current = { type: 'table', lines: [] };
                blocks.push(current);
            }
            current.lines.push(line.trim());
        } else {
            if (!current || current.type !== 'text') {
                current = { type: 'text', lines: [] };
                blocks.push(current);
            }
            current.lines.push(line);
        }
    });

    let html = '';
    let first = true;

    blocks.forEach((block) => {
        if (block.type === 'table') {
            const rows = block.lines.filter((l) => !isSeparator(l));
            if (rows.length === 0) return;
            const parseRow = (l: string) => l.replace(/^\||\|$/g, '').split('|').map((c) => c.trim());
            const header = parseRow(rows[0]);
            const body = rows.slice(1);
            let tHtml = `<div class="${first ? '' : 'mt-3'} overflow-x-auto"><table class="w-full text-[11px] border-collapse">`;
            tHtml +=
                '<thead><tr>' +
                header
                    .map((h) => `<th class="px-2 py-1.5 text-left font-semibold bg-violet-100 text-violet-800 border border-violet-200 whitespace-nowrap">${inlineFormat(h)}</th>`)
                    .join('') +
                '</tr></thead>';
            if (body.length) {
                tHtml +=
                    '<tbody>' +
                    body
                        .map(
                            (row, i) =>
                                '<tr class="' +
                                (i % 2 === 0 ? 'bg-white' : 'bg-slate-50') +
                                '">' +
                                parseRow(row)
                                    .map((c) => `<td class="px-2 py-1.5 border border-slate-200 text-slate-700 leading-snug">${inlineFormat(c)}</td>`)
                                    .join('') +
                                '</tr>',
                        )
                        .join('') +
                    '</tbody>';
            }
            tHtml += '</table></div>';
            html += tHtml;
            first = false;
        } else {
            let inOl = false;
            let inUl = false;
            const closeList = () => {
                if (inOl) { html += '</ol>'; inOl = false; }
                if (inUl) { html += '</ul>'; inUl = false; }
            };
            block.lines.forEach((rawLine) => {
                const line = rawLine.trim();
                if (!line) { closeList(); return; }
                const olMatch = line.match(/^(\d+)\.\s+(.*)/);
                const ulMatch = line.match(/^[-•]\s+(.*)/);
                if (olMatch) {
                    if (inUl) { html += '</ul>'; inUl = false; }
                    if (!inOl) { html += `<ol class="list-decimal pl-5 ${first ? '' : 'mt-2'} space-y-1.5 text-[13px]">`; inOl = true; }
                    html += `<li class="leading-snug">${inlineFormat(olMatch[2])}</li>`;
                    first = false;
                } else if (ulMatch) {
                    if (inOl) { html += '</ol>'; inOl = false; }
                    if (!inUl) { html += `<ul class="list-disc pl-5 ${first ? '' : 'mt-2'} space-y-1.5 text-[13px]">`; inUl = true; }
                    html += `<li class="leading-snug">${inlineFormat(ulMatch[1])}</li>`;
                    first = false;
                } else {
                    closeList();
                    html += `<p class="leading-snug text-[13px] ${first ? '' : 'mt-2'}">${inlineFormat(line)}</p>`;
                    first = false;
                }
            });
            closeList();
        }
    });

    return html;
}

const GREETING = "Hi! I'm ANIRA, your KPI advisor and coach. Tell me your role and what you're working on this year — I'll suggest the best KPIs for you based on your job description, or help you refine one you already have in mind.";

function KpiCard({ kpi, onFillForm, onTryAgain }: { kpi: KpiDraft; onFillForm: () => void; onTryAgain: () => void }) {
    const unit = kpi.unit === 'currency' ? 'RM ' : '';
    const suffix = kpi.unit === 'percentage' ? '%' : '';
    const fmt = (v: unknown) => (v != null ? `${unit}${Number(v).toLocaleString('en-MY')}${suffix}` : '-');

    return (
        <div className="ai-kpi-card text-sm px-4 py-4">
            <p className="text-[10px] uppercase tracking-widest font-black text-violet-500 mb-2">ANIRA KPI Draft</p>
            <p className="font-black text-slate-900 text-sm leading-snug mb-1">{kpi.title ?? ''}</p>
            <p className="text-xs text-slate-600 leading-relaxed mb-3">{kpi.description ?? ''}</p>
            <div className="grid grid-cols-2 gap-1.5 text-xs mb-3">
                <div className="bg-white/70 rounded-xl p-2">
                    <p className="text-slate-400 text-[10px] uppercase font-bold">Category</p>
                    <p className="font-semibold text-slate-800 mt-0.5">{kpi.category ?? '-'}</p>
                </div>
                <div className="bg-white/70 rounded-xl p-2">
                    <p className="text-slate-400 text-[10px] uppercase font-bold">Sub-Category</p>
                    <p className="font-semibold text-slate-800 mt-0.5">{kpi.sub_category ?? '-'}</p>
                </div>
                <div className="bg-white/70 rounded-xl p-2">
                    <p className="text-slate-400 text-[10px] uppercase font-bold">Unit</p>
                    <p className="font-semibold text-slate-800 mt-0.5 capitalize">{kpi.unit ?? '-'}</p>
                </div>
                <div className="bg-white/70 rounded-xl p-2">
                    <p className="text-slate-400 text-[10px] uppercase font-bold">Base → Stretch</p>
                    <p className="font-semibold text-slate-800 mt-0.5">
                        {fmt(kpi.base_target)} → {fmt(kpi.stretch_target)}
                    </p>
                </div>
            </div>
            <div className="flex gap-1 text-[10px] mb-3">
                {(['q1', 'q2', 'q3', 'q4'] as const).map((q) => (
                    <div key={q} className="flex-1 bg-white/70 rounded-xl p-2 text-center">
                        <p className="text-slate-400 font-bold">{q.toUpperCase()}</p>
                        <p className="font-semibold text-slate-800 mt-0.5">{fmt(kpi[q])}</p>
                    </div>
                ))}
            </div>
            {kpi.rationale && <p className="text-[11px] text-violet-700 italic mb-3">{kpi.rationale}</p>}
            <div className="flex gap-2">
                <button onClick={onFillForm} className="flex-1 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold transition">
                    Fill Form
                </button>
                <button onClick={onTryAgain} className="flex-1 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 transition">
                    Try Again
                </button>
            </div>
        </div>
    );
}

export default function AniraChatWidget() {
    const [open, setOpen] = useState(false);
    const [maximized, setMaximized] = useState(false);
    const [unread, setUnread] = useState(false);
    const [isWaiting, setIsWaiting] = useState(false);
    const [isTyping, setIsTyping] = useState(false);
    const [kpiReadyToFill, setKpiReadyToFill] = useState(false);
    const [uiMessages, setUiMessages] = useState<UiMessage[]>([]);
    const [input, setInput] = useState('');

    const historyRef = useRef<ChatMessage[]>([]);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // Restore a persisted conversation on mount.
    useEffect(() => {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return;
            const data = JSON.parse(raw);
            if (!data || !Array.isArray(data.history)) return;
            historyRef.current = data.history ?? [];
            setUiMessages(data.uiMessages ?? []);
            setKpiReadyToFill(data.kpiReadyToFill ?? false);
        } catch {
            // ignore malformed session storage
        }
    }, []);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ block: 'end' });
    }, [uiMessages, isTyping]);

    function saveSession(nextUiMessages: UiMessage[], nextKpiReadyToFill: boolean) {
        try {
            sessionStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ history: historyRef.current, uiMessages: nextUiMessages, kpiReadyToFill: nextKpiReadyToFill }),
            );
        } catch {
            // ignore quota errors
        }
    }

    function toggleOpen() {
        setOpen((prev) => {
            const next = !prev;
            if (next) {
                setUnread(false);
                setTimeout(() => inputRef.current?.focus(), 50);
            }
            return next;
        });
    }

    async function sendMessage(text: string) {
        if (isWaiting || !text.trim()) return;
        const trimmed = text.trim();

        const nextUi: UiMessage[] = [...uiMessages, { type: 'user', text: trimmed }];
        setUiMessages(nextUi);
        setInput('');

        historyRef.current = [...historyRef.current, { role: 'user' as const, content: trimmed }].slice(-20);

        setIsWaiting(true);
        setIsTyping(true);

        try {
            const res = await fetch(CHAT_URL, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ messages: historyRef.current }),
            });

            setIsTyping(false);

            if (res.status === 419) {
                const withError: UiMessage[] = [...nextUi, { type: 'bot', text: 'Your session has expired. Please refresh the page and try again.' }];
                setUiMessages(withError);
                setIsWaiting(false);
                return;
            }

            let data: { success?: boolean; reply?: string; message?: string };
            try {
                data = await res.json();
            } catch {
                setUiMessages([...nextUi, { type: 'bot', text: `Server error (${res.status}). Please try again.` }]);
                setIsWaiting(false);
                return;
            }

            const reply = data.success ? (data.reply ?? '') : (data.message ?? 'Something went wrong.');
            const withReply: UiMessage[] = [...nextUi, { type: 'bot', text: reply }];
            setUiMessages(withReply);
            historyRef.current = [...historyRef.current, { role: 'assistant' as const, content: reply }];

            let nextReady = kpiReadyToFill;
            if (!kpiReadyToFill && /your kpi is finalised/i.test(reply)) {
                nextReady = true;
                setKpiReadyToFill(true);
            }

            saveSession(withReply, nextReady);

            if (!open) setUnread(true);
        } catch {
            setIsTyping(false);
            setUiMessages([...nextUi, { type: 'bot', text: 'Could not reach the server. Check your connection and try again.' }]);
        } finally {
            setIsWaiting(false);
        }
    }

    async function buildKpi() {
        if (isWaiting || historyRef.current.length < 2) return;

        const withThinking: UiMessage[] = [...uiMessages, { type: 'bot', text: "Give me a moment — I'm putting together your best KPI based on our conversation..." }];
        setUiMessages(withThinking);
        setIsWaiting(true);
        setIsTyping(true);

        try {
            const res = await fetch(SUGGEST_URL, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ messages: historyRef.current }),
            });

            setIsTyping(false);

            if (res.status === 419) {
                setUiMessages([...withThinking, { type: 'bot', text: 'Your session has expired. Please refresh the page and try again.' }]);
                setIsWaiting(false);
                return;
            }

            let data: { success?: boolean; kpi?: KpiDraft; message?: string };
            try {
                data = await res.json();
            } catch {
                setUiMessages([...withThinking, { type: 'bot', text: `Server error (${res.status}). Please try again.` }]);
                setIsWaiting(false);
                return;
            }

            if (!data.success || !data.kpi) {
                setUiMessages([...withThinking, { type: 'bot', text: data.message ?? "Could not generate a suggestion right now. Let's keep chatting." }]);
                setIsWaiting(false);
                return;
            }

            const withCard: UiMessage[] = [...withThinking, { type: 'kpi_card', kpi: data.kpi }];
            setUiMessages(withCard);
            saveSession(withCard, kpiReadyToFill);
        } catch {
            setIsTyping(false);
            setUiMessages([...withThinking, { type: 'bot', text: 'Could not reach the server. Check your connection and try again.' }]);
        } finally {
            setIsWaiting(false);
        }
    }

    function fillOrRedirect(kpi: KpiDraft) {
        if (typeof window.aniraFillKpiForm === 'function') {
            window.aniraFillKpiForm(kpi);
            setUiMessages((prev) => [...prev, { type: 'bot', text: "Done! I've filled in all the KPI details. Review and tweak before submitting." }]);
        } else {
            try {
                sessionStorage.setItem('anira_pending_kpi', JSON.stringify(kpi));
            } catch {
                // ignore
            }
            window.location.href = '/kpi/create';
        }
    }

    function clearHistory() {
        historyRef.current = [];
        setUiMessages([]);
        setKpiReadyToFill(false);
        saveSession([], false);
    }

    const showQuickPrompts = uiMessages.length === 0;
    const showBuildBar = kpiReadyToFill;

    return (
        <>
            <style>{`
                #aiChatPanel { transition: width .25s ease, height .25s ease, bottom .25s ease, right .25s ease; }
                #aiChatPanel.maximized { width: min(680px, 96vw) !important; height: calc(100vh - 80px) !important; bottom: 16px !important; right: 16px !important; }
                .ai-msg-user { background: #2563eb; color: white; border-radius: 18px 18px 4px 18px; align-self: flex-end; }
                .ai-msg-bot { background: #f1f5f9; color: #1e293b; border-radius: 18px 18px 18px 4px; align-self: flex-start; }
                .ai-kpi-card { background: linear-gradient(135deg, #f5f3ff, #ede9fe); border: 1px solid #c4b5fd; border-radius: 16px; align-self: flex-start; width: 96%; }
                #aiChatMessages::-webkit-scrollbar { width: 4px; }
                #aiChatMessages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
            `}</style>
            <button
                onClick={toggleOpen}
                className="no-print fixed bottom-6 right-6 z-[9999] w-14 h-14 rounded-full bg-violet-600 hover:bg-violet-700 shadow-xl flex items-center justify-center transition"
                title="ANIRA - KPI AI Assistant"
            >
                {!open ? (
                    <svg className="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" />
                    </svg>
                ) : (
                    <svg className="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                )}
                {unread && <span className="absolute top-1 right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white" />}
            </button>

            {open && (
                <div
                    className={`no-print fixed bottom-24 right-6 z-[9998] w-80 sm:w-96 bg-white rounded-3xl shadow-2xl border border-slate-100 flex flex-col ${maximized ? 'maximized' : ''}`}
                    style={{ height: 480 }}
                    id="aiChatPanel"
                >
                    <div className="flex items-center gap-3 px-5 py-4 border-b border-slate-100 rounded-t-3xl bg-violet-600 flex-shrink-0">
                        <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                            <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2a1 1 0 0 1 .993.883L13 3v1.07a8.002 8.002 0 0 1 6.93 6.93H21a1 1 0 0 1 .117 1.993L21 13h-1.07a8.002 8.002 0 0 1-6.93 6.93V21a1 1 0 0 1-1.993.117L11 21v-1.07a8.002 8.002 0 0 1-6.93-6.93H3a1 1 0 0 1-.117-1.993L3 11h1.07a8.002 8.002 0 0 1 6.93-6.93V3a1 1 0 0 1 1-1z" />
                            </svg>
                        </div>
                        <div>
                            <p className="text-white font-black text-sm">ANIRA</p>
                            <p className="text-violet-200 text-xs">KPI AI Assistant</p>
                        </div>
                        <div className="ml-auto flex items-center gap-1">
                            <button onClick={clearHistory} className="text-white/60 hover:text-white p-1 rounded-lg hover:bg-white/10 transition" title="Clear conversation">
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <button onClick={() => setMaximized((v) => !v)} className="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10 transition" title="Expand">
                                {!maximized ? (
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                    </svg>
                                ) : (
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                                    </svg>
                                )}
                            </button>
                            <button onClick={toggleOpen} className="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3" id="aiChatMessages">
                        {uiMessages.length === 0 && (
                            <>
                                <div className="ai-msg-bot text-sm px-4 py-3 max-w-[85%]" dangerouslySetInnerHTML={{ __html: formatBotText(GREETING) }} />
                                {showQuickPrompts && (
                                    <div className="flex flex-col gap-2 mt-1">
                                        <p className="text-xs text-slate-400 font-medium">Quick questions:</p>
                                        <button onClick={() => sendMessage('Suggest a KPI for me based on my job description')} className="text-left text-xs px-3 py-2 rounded-xl border border-violet-200 text-violet-700 hover:bg-violet-50 transition">
                                            Suggest a KPI based on my job description
                                        </button>
                                        <button onClick={() => sendMessage('How do I score high on my KPI?')} className="text-left text-xs px-3 py-2 rounded-xl border border-violet-200 text-violet-700 hover:bg-violet-50 transition">
                                            How do I score high?
                                        </button>
                                        <button onClick={() => sendMessage('How does the approval process work?')} className="text-left text-xs px-3 py-2 rounded-xl border border-violet-200 text-violet-700 hover:bg-violet-50 transition">
                                            How does approval work?
                                        </button>
                                    </div>
                                )}
                            </>
                        )}

                        {uiMessages.map((m, i) => {
                            if (m.type === 'user') {
                                return (
                                    <div key={i} className="ai-msg-user text-sm px-4 py-3 max-w-[85%]">
                                        {m.text}
                                    </div>
                                );
                            }
                            if (m.type === 'bot') {
                                return <div key={i} className="ai-msg-bot text-sm px-4 py-3 max-w-[85%]" dangerouslySetInnerHTML={{ __html: formatBotText(m.text) }} />;
                            }
                            return <KpiCard key={i} kpi={m.kpi} onFillForm={() => fillOrRedirect(m.kpi)} onTryAgain={buildKpi} />;
                        })}

                        {isTyping && (
                            <div className="ai-msg-bot text-sm px-4 py-3 max-w-[85%]">
                                <span className="inline-flex gap-1">
                                    <span className="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                                    <span className="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                                    <span className="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
                                </span>
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {showBuildBar && (
                        <div className="px-4 pt-2 pb-0 flex-shrink-0">
                            <button
                                onClick={buildKpi}
                                className="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-2xl bg-violet-50 border border-violet-200 text-violet-700 text-xs font-semibold hover:bg-violet-100 transition"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                                Draft my KPI
                            </button>
                        </div>
                    )}

                    <div className="px-4 py-3 border-t border-slate-100 flex-shrink-0 mt-2">
                        <div className="flex items-center gap-2 bg-slate-50 rounded-2xl px-4 py-2">
                            <input
                                ref={inputRef}
                                type="text"
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                placeholder="Ask something..."
                                maxLength={500}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        sendMessage(input);
                                    }
                                }}
                                className="flex-1 bg-transparent text-sm outline-none text-slate-700 placeholder-slate-400"
                            />
                            <button
                                onClick={() => sendMessage(input)}
                                className="w-8 h-8 rounded-xl bg-violet-600 hover:bg-violet-700 flex items-center justify-center transition flex-shrink-0"
                            >
                                <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
