<!-- AI CHAT WIDGET -->
<style>
    #aiChatPanel {
        transition: width .25s ease, height .25s ease, bottom .25s ease, right .25s ease;
    }
    #aiChatPanel.maximized {
        width: min(680px, 96vw) !important;
        height: calc(100vh - 80px) !important;
        bottom: 16px !important;
        right: 16px !important;
    }
    .ai-msg-user {
        background: #2563eb;
        color: white;
        border-radius: 18px 18px 4px 18px;
        align-self: flex-end;
    }
    .ai-msg-bot {
        background: #f1f5f9;
        color: #1e293b;
        border-radius: 18px 18px 18px 4px;
        align-self: flex-start;
    }
    .ai-kpi-card {
        background: linear-gradient(135deg, #f5f3ff, #ede9fe);
        border: 1px solid #c4b5fd;
        border-radius: 16px;
        align-self: flex-start;
        width: 96%;
    }
    #aiChatMessages::-webkit-scrollbar { width: 4px; }
    #aiChatMessages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>

<!-- Floating bubble -->
<div x-data>
<button
    id="aiChatBubble"
    @click="toggleAiChat()"
    class="no-print fixed bottom-6 right-6 z-[9999] w-14 h-14 rounded-full bg-violet-600 hover:bg-violet-700 shadow-xl flex items-center justify-center transition"
    title="ANIRA - KPI AI Assistant"
>
    <svg x-show="!$store.aniraChat.open" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"/>
    </svg>
    <svg x-show="$store.aniraChat.open" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
    </svg>
    <span x-show="$store.aniraChat.unread" class="absolute top-1 right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
</button>

<!-- Chat panel -->
<div
    id="aiChatPanel"
    x-show="$store.aniraChat.open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-3 scale-[.97]"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 -translate-y-3 scale-[.97]"
    :class="{ 'maximized': $store.aniraChat.maximized }"
    class="no-print fixed bottom-24 right-6 z-[9998] w-80 sm:w-96 bg-white rounded-3xl shadow-2xl border border-slate-100 flex flex-col"
    style="height: 480px;"
>
    <!-- Header -->
    <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-100 rounded-t-3xl bg-violet-600 flex-shrink-0">
        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2a1 1 0 0 1 .993.883L13 3v1.07a8.002 8.002 0 0 1 6.93 6.93H21a1 1 0 0 1 .117 1.993L21 13h-1.07a8.002 8.002 0 0 1-6.93 6.93V21a1 1 0 0 1-1.993.117L11 21v-1.07a8.002 8.002 0 0 1-6.93-6.93H3a1 1 0 0 1-.117-1.993L3 11h1.07a8.002 8.002 0 0 1 6.93-6.93V3a1 1 0 0 1 1-1z"/>
            </svg>
        </div>
        <div>
            <p class="text-white font-black text-sm">ANIRA</p>
            <p class="text-violet-200 text-xs">KPI AI Assistant</p>
        </div>
        <div class="ml-auto flex items-center gap-1">
            <!-- Clear history -->
            <button onclick="aiClearHistory()" class="text-white/60 hover:text-white p-1 rounded-lg hover:bg-white/10 transition" title="Clear conversation">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
            <!-- Maximize toggle -->
            <button id="aiMaximizeBtn" @click="toggleAiMaximize()" class="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10 transition" title="Expand">
                <svg x-show="!$store.aniraChat.maximized" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
                <svg x-show="$store.aniraChat.maximized" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"/>
                </svg>
            </button>
            <!-- Close -->
            <button @click="toggleAiChat()" class="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Messages -->
    <div id="aiChatMessages" class="flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3">
    </div>

    <!-- Build My KPI bar (hidden until 3+ messages) -->
    <div id="aiBuildKpiBar" class="hidden px-4 pt-2 pb-0 flex-shrink-0">
        <button
            onclick="aiBuildKpi()"
            id="aiBuildKpiBtn"
            class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-2xl bg-violet-50 border border-violet-200 text-violet-700 text-xs font-semibold hover:bg-violet-100 transition"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            Draft my KPI
        </button>
    </div>

    <!-- Input -->
    <div class="px-4 py-3 border-t border-slate-100 flex-shrink-0 mt-2">
        <div class="flex items-center gap-2 bg-slate-50 rounded-2xl px-4 py-2">
            <input
                id="aiChatInput"
                type="text"
                placeholder="Ask something..."
                class="flex-1 bg-transparent text-sm outline-none text-slate-700 placeholder-slate-400"
                onkeydown="if(event.key==='Enter' && !event.shiftKey){ event.preventDefault(); aiSendMessage(); }"
                maxlength="500"
            >
            <button
                id="aiChatSendBtn"
                onclick="aiSendMessage()"
                class="w-8 h-8 rounded-xl bg-violet-600 hover:bg-violet-700 flex items-center justify-center transition flex-shrink-0"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </div>
    </div>
</div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('aniraChat', { open: false, maximized: false, unread: false });
});

(function () {
    const CSRF        = '{{ csrf_token() }}';
    const chatUrl     = '{{ route("ai.chat") }}';
    const suggestUrl  = '{{ route("ai.suggest-kpi") }}';

    const JSON_HEADERS = {
        'Content-Type':     'application/json',
        'Accept':           'application/json',
        'X-CSRF-TOKEN':     CSRF,
        'X-Requested-With': 'XMLHttpRequest',
    };
    const STORAGE_KEY = 'anira_session_v2';

    // State
    let history          = [];  // API message array
    let uiMessages       = [];  // {type:'user'|'bot'|'kpi_card', text?, kpi?}
    let isWaiting        = false;
    let kpiReadyToFill   = false;  // true only after ANIRA signals "Your KPI is finalised."

    /* -----------------------------------------------------------------------
     | SESSION STORAGE — persist conversation across page navigations
     ----------------------------------------------------------------------- */
    function saveSession() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ history, uiMessages, kpiReadyToFill }));
        } catch (_) {}
    }

    function loadSession() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            const data = JSON.parse(raw);
            if (!data || !Array.isArray(data.history)) return false;
            history          = data.history          ?? [];
            uiMessages       = data.uiMessages       ?? [];
            kpiReadyToFill   = data.kpiReadyToFill   ?? false;
            return true;
        } catch (_) {
            return false;
        }
    }

    function restoreMessages() {
        const wrap = document.getElementById('aiChatMessages');
        if (uiMessages.length === 0) {
            renderGreeting();
            return;
        }
        uiMessages.forEach(m => {
            if (m.type === 'user') {
                renderMessage('user', m.text, false);
            } else if (m.type === 'bot') {
                renderMessage('bot', m.text, false);
            } else if (m.type === 'kpi_card') {
                renderKpiCard(m.kpi, false);
            }
        });
        wrap.scrollTop = wrap.scrollHeight;
        maybeShowBuildBar();
    }

    function renderGreeting() {
        const wrap = document.getElementById('aiChatMessages');
        const greeting = document.createElement('div');
        greeting.className = 'ai-msg-bot text-sm px-4 py-3 max-w-[85%]';
        greeting.innerHTML = formatBotText("Hi! I'm ANIRA, your KPI advisor and coach. Tell me your role and what you're working on this year — I'll suggest the best KPIs for you based on your job description, or help you refine one you already have in mind.");
        wrap.appendChild(greeting);

        const quickDiv = document.createElement('div');
        quickDiv.id = 'aiQuickPrompts';
        quickDiv.className = 'flex flex-col gap-2 mt-1';
        quickDiv.innerHTML = `
            <p class="text-xs text-slate-400 font-medium">Quick questions:</p>
            <button onclick="aiQuickSend('Suggest a KPI for me based on my job description')" class="text-left text-xs px-3 py-2 rounded-xl border border-violet-200 text-violet-700 hover:bg-violet-50 transition">Suggest a KPI based on my job description</button>
            <button onclick="aiQuickSend('How do I score high on my KPI?')" class="text-left text-xs px-3 py-2 rounded-xl border border-violet-200 text-violet-700 hover:bg-violet-50 transition">How do I score high?</button>
            <button onclick="aiQuickSend('How does the approval process work?')" class="text-left text-xs px-3 py-2 rounded-xl border border-violet-200 text-violet-700 hover:bg-violet-50 transition">How does approval work?</button>
        `;
        wrap.appendChild(quickDiv);
    }

    /* -----------------------------------------------------------------------
     | OPEN / CLOSE
     ----------------------------------------------------------------------- */
    window.toggleAiChat = function () {
        const store = Alpine.store('aniraChat');
        store.open = !store.open;

        if (store.open) {
            store.unread = false;
            setTimeout(() => document.getElementById('aiChatInput')?.focus(), 50);
        }
    };

    /* -----------------------------------------------------------------------
     | MAXIMIZE / RESTORE
     ----------------------------------------------------------------------- */
    window.toggleAiMaximize = function () {
        const store = Alpine.store('aniraChat');
        store.maximized = !store.maximized;
    };

    /* -----------------------------------------------------------------------
     | CLEAR HISTORY
     ----------------------------------------------------------------------- */
    window.aiClearHistory = function () {
        history          = [];
        uiMessages       = [];
        kpiReadyToFill   = false;
        saveSession();
        const wrap = document.getElementById('aiChatMessages');
        wrap.innerHTML = '';
        document.getElementById('aiBuildKpiBar')?.classList.add('hidden');
        renderGreeting();
    };

    /* -----------------------------------------------------------------------
     | QUICK PROMPTS
     ----------------------------------------------------------------------- */
    window.aiQuickSend = function (text) {
        document.getElementById('aiQuickPrompts')?.remove();
        document.getElementById('aiChatInput').value = text;
        aiSendMessage();
    };

    /* -----------------------------------------------------------------------
     | SEND MESSAGE
     ----------------------------------------------------------------------- */
    window.aiSendMessage = async function () {
        if (isWaiting) return;

        const input = document.getElementById('aiChatInput');
        const text  = input.value.trim();
        if (!text) return;

        input.value = '';
        document.getElementById('aiQuickPrompts')?.remove();

        renderMessage('user', text, true);
        history.push({ role: 'user', content: text });
        if (history.length > 20) history = history.slice(-20);

        maybeShowBuildBar();
        isWaiting = true;
        const typing = appendTyping();

        try {
            const res = await fetch(chatUrl, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify({ messages: history }),
            });

            typing.remove();

            if (res.status === 419) {
                renderMessage('bot', 'Your session has expired. Please refresh the page and try again.', false);
                isWaiting = false;
                return;
            }

            let data;
            try { data = await res.json(); }
            catch (_) {
                renderMessage('bot', `Server error (${res.status}). Please try again.`, false);
                isWaiting = false;
                return;
            }

            const reply = data.success ? data.reply : (data.message ?? 'Something went wrong.');
            renderMessage('bot', reply, true);
            history.push({ role: 'assistant', content: reply });

            // Detect ANIRA's step-9 finalisation signal to unlock the Draft button
            if (!kpiReadyToFill && /your kpi is finalised/i.test(reply)) {
                kpiReadyToFill = true;
                maybeShowBuildBar();
            }

            saveSession();

            if (!Alpine.store('aniraChat').open) Alpine.store('aniraChat').unread = true;

        } catch (e) {
            document.querySelectorAll('#aiChatMessages .ai-msg-bot:last-child')
                .forEach(el => { if (el.querySelector('.animate-bounce')) el.remove(); });
            renderMessage('bot', 'Could not reach the server. Check your connection and try again.', false);
        } finally {
            isWaiting = false;
        }
    };

    /* -----------------------------------------------------------------------
     | BUILD MY KPI — generate a complete structured KPI draft
     ----------------------------------------------------------------------- */
    window.aiBuildKpi = async function () {
        if (isWaiting || history.length < 2) return;

        document.getElementById('aiBuildKpiBar').classList.add('hidden');

        renderMessage('bot', "Give me a moment — I'm putting together your best KPI based on our conversation...", false);
        const typing = appendTyping();
        isWaiting = true;

        try {
            const res = await fetch(suggestUrl, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify({ messages: history }),
            });

            typing.remove();

            if (res.status === 419) {
                renderMessage('bot', 'Your session has expired. Please refresh the page and try again.', false);
                maybeShowBuildBar();
                isWaiting = false;
                return;
            }

            let data;
            try { data = await res.json(); }
            catch (_) {
                renderMessage('bot', `Server error (${res.status}). Please try again.`, false);
                maybeShowBuildBar();
                isWaiting = false;
                return;
            }

            if (!data.success || !data.kpi) {
                renderMessage('bot', data.message ?? "Could not generate a suggestion right now. Let's keep chatting.", false);
                maybeShowBuildBar();
                isWaiting = false;
                return;
            }

            renderKpiCard(data.kpi, true);
            saveSession();

        } catch (e) {
            document.querySelectorAll('#aiChatMessages .ai-msg-bot:last-child')
                .forEach(el => { if (el.querySelector('.animate-bounce')) el.remove(); });
            renderMessage('bot', 'Could not reach the server. Check your connection and try again.', false);
            maybeShowBuildBar();
        } finally {
            isWaiting = false;
        }
    };

    /* -----------------------------------------------------------------------
     | KPI CARD RENDER
     ----------------------------------------------------------------------- */
    function renderKpiCard(kpi, persist) {
        const wrap = document.getElementById('aiChatMessages');
        const div  = document.createElement('div');
        div.className = 'ai-kpi-card text-sm px-4 py-4';

        const unit = kpi.unit === 'currency' ? 'RM ' : kpi.unit === 'percentage' ? '' : '';
        const suffix = kpi.unit === 'percentage' ? '%' : '';
        const fmt  = (v) => v != null ? `${unit}${Number(v).toLocaleString('en-MY')}${suffix}` : '-';

        const kpiJson = escAttr(JSON.stringify(kpi));

        const fillBtn = `<button onclick="aniraFillOrRedirect(JSON.parse(this.dataset.kpi))" data-kpi="${kpiJson}" class="flex-1 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold transition">Fill Form</button>`;

        div.innerHTML = `
            <p class="text-[10px] uppercase tracking-widest font-black text-violet-500 mb-2">ANIRA KPI Draft</p>
            <p class="font-black text-slate-900 text-sm leading-snug mb-1">${escHtml(kpi.title ?? '')}</p>
            <p class="text-xs text-slate-600 leading-relaxed mb-3">${escHtml(kpi.description ?? '')}</p>
            <div class="grid grid-cols-2 gap-1.5 text-xs mb-3">
                <div class="bg-white/70 rounded-xl p-2">
                    <p class="text-slate-400 text-[10px] uppercase font-bold">Category</p>
                    <p class="font-semibold text-slate-800 mt-0.5">${escHtml(kpi.category ?? '-')}</p>
                </div>
                <div class="bg-white/70 rounded-xl p-2">
                    <p class="text-slate-400 text-[10px] uppercase font-bold">Sub-Category</p>
                    <p class="font-semibold text-slate-800 mt-0.5">${escHtml(kpi.sub_category ?? '-')}</p>
                </div>
                <div class="bg-white/70 rounded-xl p-2">
                    <p class="text-slate-400 text-[10px] uppercase font-bold">Unit</p>
                    <p class="font-semibold text-slate-800 mt-0.5 capitalize">${escHtml(kpi.unit ?? '-')}</p>
                </div>
                <div class="bg-white/70 rounded-xl p-2">
                    <p class="text-slate-400 text-[10px] uppercase font-bold">Base → Stretch</p>
                    <p class="font-semibold text-slate-800 mt-0.5">${fmt(kpi.base_target)} → ${fmt(kpi.stretch_target)}</p>
                </div>
            </div>
            <div class="flex gap-1 text-[10px] mb-3">
                ${['Q1','Q2','Q3','Q4'].map(q => `<div class="flex-1 bg-white/70 rounded-xl p-2 text-center"><p class="text-slate-400 font-bold">${q}</p><p class="font-semibold text-slate-800 mt-0.5">${fmt(kpi[q.toLowerCase()])}</p></div>`).join('')}
            </div>
            ${kpi.rationale ? `<p class="text-[11px] text-violet-700 italic mb-3">${escHtml(kpi.rationale)}</p>` : ''}
            <div class="flex gap-2">
                ${fillBtn}
                <button onclick="aiBuildKpi()" class="flex-1 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 transition">Try Again</button>
            </div>
        `;

        wrap.appendChild(div);
        wrap.scrollTop = wrap.scrollHeight;

        if (persist) {
            uiMessages.push({ type: 'kpi_card', kpi });
        }
    }

    /* -----------------------------------------------------------------------
     | FILL OR REDIRECT
     | If already on KPI create page — fill immediately.
     | Otherwise save KPI to sessionStorage and go to /kpi/create.
     ----------------------------------------------------------------------- */
    window.aniraFillOrRedirect = function (kpi) {
        if (typeof window.aniraFillKpiForm === 'function') {
            // Already on create page — fill right now
            window.aniraFillKpiForm(kpi);
            renderMessage('bot', "Done! I've filled in all the KPI details. Review and tweak before submitting.", false);
        } else {
            // Save KPI and redirect — create page will auto-fill on load
            try { sessionStorage.setItem('anira_pending_kpi', JSON.stringify(kpi)); } catch (_) {}
            window.location.href = '/kpi/create';
        }
    };

    /* -----------------------------------------------------------------------
     | SHOW BUILD BAR
     ----------------------------------------------------------------------- */
    function maybeShowBuildBar() {
        if (kpiReadyToFill) {
            document.getElementById('aiBuildKpiBar')?.classList.remove('hidden');
        }
    }

    /* -----------------------------------------------------------------------
     | RENDER HELPERS
     ----------------------------------------------------------------------- */
    function renderMessage(role, text, persist) {
        const wrap = document.getElementById('aiChatMessages');
        const div  = document.createElement('div');
        div.className = (role === 'user' ? 'ai-msg-user' : 'ai-msg-bot') + ' text-sm px-4 py-3 max-w-[85%]';

        if (role === 'bot') {
            div.innerHTML = formatBotText(text);
        } else {
            div.textContent = text;
        }

        wrap.appendChild(div);
        wrap.scrollTop = wrap.scrollHeight;

        if (persist) {
            uiMessages.push({ type: role, text });
        }

        return div;
    }

    function appendTyping() {
        const wrap = document.getElementById('aiChatMessages');
        const div  = document.createElement('div');
        div.className = 'ai-msg-bot text-sm px-4 py-3 max-w-[85%]';
        div.innerHTML = '<span class="inline-flex gap-1"><span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay:0ms"></span><span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay:150ms"></span><span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay:300ms"></span></span>';
        wrap.appendChild(div);
        wrap.scrollTop = wrap.scrollHeight;
        return div;
    }

    function inlineFormat(str) {
        return escHtml(str)
            .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold">$1</strong>')
            .replace(/\*(.+?)\*/g,     '<em>$1</em>')
            .replace(/`(.+?)`/g,       '<code class="bg-slate-200 text-slate-700 px-1 rounded text-[11px]">$1</code>');
    }

    function formatBotText(raw) {
        // Strip heading markers but keep the text
        let text = raw.replace(/^#{1,6}\s+/gm, '');

        // Split into blocks: table runs vs everything else
        const lines = text.split('\n');
        const blocks = [];  // {type: 'table'|'text', lines: []}
        let current = null;

        const isTableRow  = (l) => l.trim().startsWith('|') && l.trim().endsWith('|');
        const isSeparator = (l) => /^\|[\s\-:|]+\|$/.test(l.trim());

        lines.forEach(line => {
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

        let html  = '';
        let first = true;

        blocks.forEach(block => {
            if (block.type === 'table') {
                const rows = block.lines.filter(l => !isSeparator(l));
                if (rows.length === 0) return;
                const parseRow = (l) => l.replace(/^\||\|$/g, '').split('|').map(c => c.trim());
                const header = parseRow(rows[0]);
                const body   = rows.slice(1);
                let tHtml = `<div class="${first ? '' : 'mt-3'} overflow-x-auto"><table class="w-full text-[11px] border-collapse">`;
                tHtml += '<thead><tr>' + header.map(h =>
                    `<th class="px-2 py-1.5 text-left font-semibold bg-violet-100 text-violet-800 border border-violet-200 whitespace-nowrap">${inlineFormat(h)}</th>`
                ).join('') + '</tr></thead>';
                if (body.length) {
                    tHtml += '<tbody>' + body.map((row, i) =>
                        '<tr class="' + (i % 2 === 0 ? 'bg-white' : 'bg-slate-50') + '">' +
                        parseRow(row).map(c =>
                            `<td class="px-2 py-1.5 border border-slate-200 text-slate-700 leading-snug">${inlineFormat(c)}</td>`
                        ).join('') + '</tr>'
                    ).join('') + '</tbody>';
                }
                tHtml += '</table></div>';
                html += tHtml;
                first = false;
            } else {
                // Text block: line-by-line with lists
                let inOl = false;
                let inUl = false;
                const closeList = () => {
                    if (inOl) { html += '</ol>'; inOl = false; }
                    if (inUl) { html += '</ul>'; inUl = false; }
                };
                block.lines.forEach(line => {
                    line = line.trim();
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

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function escAttr(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
    }

    /* -----------------------------------------------------------------------
     | INIT — restore or show greeting
     ----------------------------------------------------------------------- */
    const restored = loadSession();
    restoreMessages();

})();
</script>
