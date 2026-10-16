@extends('layouts.app')
@section('title', 'AI Assistant')
@section('page-title', 'AI Assistant')

@php
    $aiConfig = [
        'sendUrl' => route('ai.send'),
        'clearUrl' => route('ai.clear'),
        'csrf' => csrf_token(),
        'suggestions' => [
            ['icon' => 'trending-up', 'title' => "Today's performance", 'prompt' => 'How is my business doing today? Give me the key numbers and what needs attention.'],
            ['icon' => 'percent', 'title' => 'Profit margins', 'prompt' => 'Which of my products has the best and the worst profit margin?'],
            ['icon' => 'package-minus', 'title' => 'Low stock', 'prompt' => 'Which items are low or out of stock, and what should I make or buy first?'],
            ['icon' => 'book-text', 'title' => 'Khata risk', 'prompt' => 'Who owes me the most on khata and what should I do about it?'],
            ['icon' => 'milk', 'title' => 'Milk buying cost', 'prompt' => 'How is my milk buying cost this month compared with my selling price?'],
            ['icon' => 'trash-2', 'title' => 'Reduce waste', 'prompt' => 'How much did I waste this month and how can I reduce it?'],
        ],
        'chips' => [
            ['label' => "Today's summary", 'prompt' => 'Give me a short summary of today.'],
            ['label' => 'This month vs last month', 'prompt' => 'How is this month going compared with last month?'],
            ['label' => 'Low stock', 'prompt' => 'Which items are low on stock?'],
            ['label' => 'Khata owed', 'prompt' => 'Who owes me the most on khata?'],
            ['label' => 'Best sellers', 'prompt' => 'What are my best selling items this month?'],
        ],
    ];
@endphp

@section('content')
<style>
    /* Clean reply formatting (no markdown needed) */
    .ai-msg .ai-line,
    .ai-msg .ai-bullet { margin-top: .5rem; }
    .ai-msg .ai-heading { margin-top: .875rem; font-size: .8125rem; font-weight: 600; letter-spacing: .01em; color: #1b3022; }
    .ai-msg > :first-child { margin-top: 0; }
    .ai-msg .ai-bullet { display: flex; align-items: flex-start; gap: .625rem; }
    .ai-msg .ai-dot { flex: none; width: .375rem; height: .375rem; margin-top: .5rem; border-radius: 9999px; background: #1b3022; opacity: .6; }
    .ai-msg .ai-num { flex: none; min-width: 1.25rem; font-weight: 600; color: #1b3022; }

    /* Message box */
    .ai-composer { transition: border-color .15s, box-shadow .15s; }
    .ai-composer:focus-within { border-color: #1b3022; box-shadow: 0 0 0 2px rgba(27, 48, 34, .22); }
    .ai-input { resize: none; border: 0 !important; outline: none !important; box-shadow: none !important; background: transparent; }
</style>

<div class="max-w-5xl mx-auto"
     style="height: calc(100vh - 8rem); min-height: 560px;"
     x-data="aiChat(@js(array_values($history)), @js($aiConfig))">

    <div class="relative bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden flex flex-col h-full">

        {{-- ==================== HEADER ==================== --}}
        <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-outline-variant shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <span class="h-11 w-11 rounded-control bg-forest-900 flex items-center justify-center shrink-0">
                    <i data-lucide="sparkles" class="h-5 w-5 text-white"></i>
                </span>
                <div class="min-w-0">
                    <p class="font-serif text-lg font-semibold text-ink truncate">{{ $settings->dairy_name ?? 'DairyPro' }} Business Assistant</p>
                    <p class="text-xs text-outline truncate">Knows your live sales, stock, khata and costs &middot; Powered by <span class="font-semibold text-ink-variant">Gorav</span><span class="font-semibold text-forest-700">AI</span></p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span class="hidden md:inline-flex items-center gap-1.5 text-xs text-outline bg-surface-container-low px-2.5 py-1 rounded-full">
                    <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                    <span x-text="nowLabel"></span>
                </span>
                <button type="button" @click="clearChat()" x-show="messages.length > 0" x-cloak
                        class="inline-flex items-center gap-1.5 rounded-control border border-outline-variant text-xs font-semibold text-ink-variant px-3 py-2 hover:bg-surface-container-low hover:text-clay transition">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> New chat
                </button>
            </div>
        </div>

        {{-- ==================== MESSAGES ==================== --}}
        <div class="flex-1 min-h-0 overflow-y-auto" x-ref="log" @scroll.passive="onScroll()">
            <div class="max-w-3xl mx-auto px-6 py-8 space-y-6"
                 :class="messages.length === 0 ? 'min-h-full flex flex-col justify-center' : ''">

                {{-- Welcome screen --}}
                <template x-if="messages.length === 0">
                    <div>
                        <div class="text-center mb-8">
                            <span class="h-14 w-14 rounded-control bg-forest-50 flex items-center justify-center mx-auto mb-4"><i data-lucide="sparkles" class="h-7 w-7 text-forest-700"></i></span>
                            <h3 class="font-serif text-3xl font-semibold text-ink mb-2" x-text="greeting + '!'"></h3>
                            <p class="text-sm text-outline max-w-md mx-auto">Ask me about sales, profit, stock, milk buying, khata or waste. I use your real numbers and give you simple advice.</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <template x-for="s in suggestions" :key="s.title">
                                <button type="button" @click="ask(s.prompt)"
                                        class="text-left rounded-control border border-outline-variant p-4 hover:border-forest-700/40 hover:bg-forest-50 transition flex items-start gap-3">
                                    <span class="h-9 w-9 rounded-control bg-forest-50 flex items-center justify-center shrink-0"><i :data-lucide="s.icon" class="h-4 w-4 text-forest-700"></i></span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-ink" x-text="s.title"></span>
                                        <span class="block text-xs text-outline mt-0.5" x-text="s.prompt"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Conversation --}}
                <template x-for="(message, index) in messages" :key="index">
                    <div>
                        <template x-if="message.role === 'user'">
                            <div class="flex flex-col items-end">
                                <div class="max-w-[80%] bg-forest-700 text-white rounded-bento rounded-tr-sm px-4 py-3 text-sm leading-relaxed whitespace-pre-line" x-text="message.content"></div>
                                <span x-show="message.time" class="mt-1 mr-1 text-[11px] text-outline" x-text="message.time"></span>
                            </div>
                        </template>
                        <template x-if="message.role === 'assistant'">
                            <div class="flex justify-start gap-3">
                                <span class="h-8 w-8 rounded-control bg-forest-50 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="sparkles" class="h-4 w-4 text-forest-700"></i></span>
                                <div class="min-w-0 max-w-[88%]">
                                    <div class="ai-msg rounded-bento rounded-tl-sm px-4 py-3 text-sm leading-relaxed"
                                         :class="message.error ? 'bg-clay-container text-clay' : 'bg-surface-container-low text-ink'"
                                         x-html="format(message.content)"></div>
                                    <div class="mt-1 ml-1 flex items-center gap-3">
                                        <span x-show="message.time" class="text-[11px] text-outline" x-text="message.time"></span>
                                        <button type="button" x-show="!message.error" @click="copy(message.content, index)"
                                                class="text-[11px] font-medium text-outline hover:text-forest-700 transition"
                                                x-text="copied === index ? 'Copied' : 'Copy'"></button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Typing indicator --}}
                <div x-show="sending" x-cloak class="flex justify-start gap-3">
                    <span class="h-8 w-8 rounded-control bg-forest-50 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="sparkles" class="h-4 w-4 text-forest-700"></i></span>
                    <div class="bg-surface-container-low text-outline rounded-bento rounded-tl-sm px-4 py-3.5 text-sm flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-outline animate-bounce" style="animation-delay:0ms"></span>
                        <span class="h-1.5 w-1.5 rounded-full bg-outline animate-bounce" style="animation-delay:150ms"></span>
                        <span class="h-1.5 w-1.5 rounded-full bg-outline animate-bounce" style="animation-delay:300ms"></span>
                        <span class="text-xs ml-1">Checking your numbers...</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Jump to latest message --}}
        <button type="button" x-show="showJump" x-cloak @click="scrollDown(true)"
                class="absolute left-1/2 h-9 w-9 rounded-full bg-white border border-outline-variant shadow-bento flex items-center justify-center text-ink-variant hover:text-forest-700 transition"
                style="bottom: 9rem; transform: translateX(-50%);" title="Go to latest message">
            <i data-lucide="arrow-down" class="h-4 w-4"></i>
        </button>

        {{-- ==================== MESSAGE BOX ==================== --}}
        <form @submit.prevent="send()" class="border-t border-outline-variant shrink-0">
            <div class="max-w-3xl mx-auto px-6 py-4 space-y-3">
                <div x-show="messages.length > 0" x-cloak class="flex gap-2 overflow-x-auto pb-1">
                    <template x-for="chip in chips" :key="chip.label">
                        <button type="button" @click="ask(chip.prompt)" :disabled="sending"
                                class="shrink-0 whitespace-nowrap rounded-full border border-outline-variant text-xs font-medium text-ink-variant px-3 py-1.5 hover:bg-forest-50 hover:border-forest-700/40 hover:text-forest-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                x-text="chip.label"></button>
                    </template>
                </div>

                <div class="ai-composer flex items-end gap-2 rounded-bento border border-outline-variant bg-white p-2 pl-4">
                    <textarea x-ref="input" x-model="draft" rows="1" maxlength="2000" :disabled="sending"
                              placeholder="Ask about sales, profit, stock, khata or waste..."
                              @input="resize($event.target)"
                              @keydown.enter="onEnter($event)"
                              class="ai-input flex-1 py-2.5 text-sm text-ink placeholder:text-outline"
                              style="max-height: 160px;"></textarea>
                    <button type="submit" :disabled="sending || !draft.trim()"
                            class="shrink-0 h-10 w-10 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white flex items-center justify-center transition">
                        <i data-lucide="send" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="flex items-center justify-between text-[11px] text-outline">
                    <span>Enter to send &middot; Shift + Enter for a new line</span>
                    <span x-show="draft.length > 1500" x-cloak x-text="draft.length + ' / 2000'"></span>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function aiChat(initialMessages, config) {
        return {
            messages: initialMessages,
            suggestions: config.suggestions,
            chips: config.chips,
            draft: '',
            sending: false,
            copied: null,
            showJump: false,
            nowLabel: '',

            init() {
                this.updateClock();
                setInterval(() => this.updateClock(), 30000);
                this.$nextTick(() => { this.scrollDown(); this.refreshIcons(); });
            },

            get greeting() {
                const h = new Date().getHours();
                if (h < 12) return 'Good morning';
                if (h < 17) return 'Good afternoon';
                return 'Good evening';
            },

            updateClock() {
                this.nowLabel = new Date().toLocaleDateString('en-GB', {
                    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
                });
            },

            timeNow() {
                return new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            },

            // The browser sends its own date, time and timezone so the AI always knows today's date.
            clientContext() {
                const d = new Date();
                const p = (n) => String(n).padStart(2, '0');
                return {
                    client_datetime: d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
                        + 'T' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds()),
                    client_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                };
            },

            refreshIcons() {
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            },

            resize(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 160) + 'px';
            },

            onEnter(e) {
                if (e.shiftKey || e.isComposing) return;
                e.preventDefault();
                this.send();
            },

            ask(text) {
                if (this.sending) return;
                this.draft = text;
                this.send();
            },

            send() {
                const text = this.draft.trim();
                if (!text || this.sending) return;

                this.messages.push({ role: 'user', content: text, time: this.timeNow() });
                this.draft = '';
                this.sending = true;
                if (this.$refs.input) this.$refs.input.style.height = 'auto';
                this.$nextTick(() => this.scrollDown());

                fetch(config.sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify(Object.assign({ message: text }, this.clientContext())),
                })
                .then(r => r.json())
                .then(data => {
                    if (data.reply) {
                        this.messages.push({ role: 'assistant', content: data.reply, time: this.timeNow() });
                    } else {
                        this.messages.push({ role: 'assistant', content: 'Sorry, something went wrong. Please try again.', error: true });
                    }
                })
                .catch(() => {
                    this.messages.push({ role: 'assistant', content: 'Sorry, I could not reach the server. Please try again.', error: true });
                })
                .finally(() => {
                    this.sending = false;
                    this.$nextTick(() => { this.scrollDown(); if (this.$refs.input) this.$refs.input.focus(); });
                    this.refreshIcons();
                });
            },

            clearChat() {
                if (!confirm('Start a new chat? This clears the current conversation.')) return;

                fetch(config.clearUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                }).then(() => {
                    this.messages = [];
                    this.showJump = false;
                    this.refreshIcons();
                });
            },

            onScroll() {
                const log = this.$refs.log;
                if (!log) return;
                this.showJump = (log.scrollHeight - log.scrollTop - log.clientHeight) > 140;
            },

            scrollDown(smooth = false) {
                const log = this.$refs.log;
                if (!log) return;
                log.scrollTo({ top: log.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
            },

            copy(text, index) {
                const done = () => {
                    this.copied = index;
                    setTimeout(() => { if (this.copied === index) this.copied = null; }, 1500);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                    return;
                }

                // Fallback for sites running on plain http (like local .test domains)
                const area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(area);
            },

            escape(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            },

            // Turns the plain-text reply into clean blocks. All text is escaped first.
            format(text) {
                const lines = String(text || '').split('\n').map(l => l.trim()).filter(l => l.length);

                return lines.map(line => {
                    if (/^[-•]\s+/.test(line)) {
                        return '<div class="ai-bullet"><span class="ai-dot"></span><span>' + this.escape(line.replace(/^[-•]\s+/, '')) + '</span></div>';
                    }

                    const num = line.match(/^(\d+)[.)]\s+(.*)$/);
                    if (num) {
                        return '<div class="ai-bullet"><span class="ai-num">' + num[1] + '.</span><span>' + this.escape(num[2]) + '</span></div>';
                    }

                    if (/:$/.test(line) && line.length <= 60) {
                        return '<div class="ai-heading">' + this.escape(line) + '</div>';
                    }

                    return '<div class="ai-line">' + this.escape(line) + '</div>';
                }).join('');
            },
        };
    }
</script>
@endsection