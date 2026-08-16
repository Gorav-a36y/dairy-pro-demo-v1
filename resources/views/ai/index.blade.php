@extends('layouts.app')
@section('title', 'AI Assistant')
@section('page-title', 'AI Assistant')

@section('content')
<div class="max-w-3xl mx-auto"
     x-data="{
        messages: {{ collect($history)->toJson() }},
        draft: '',
        sending: false,
        send() {
            const text = this.draft.trim();
            if (!text || this.sending) return;

            this.messages.push({ role: 'user', content: text });
            this.draft = '';
            this.sending = true;
            this.$nextTick(() => this.scrollDown());

            fetch('{{ route('ai.send') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ message: text }),
            })
            .then(r => r.json())
            .then(data => {
                this.messages.push({ role: 'assistant', content: data.reply ?? 'Sorry, something went wrong.' });
            })
            .catch(() => {
                this.messages.push({ role: 'assistant', content: 'Sorry, I could not reach the server. Please try again.' });
            })
            .finally(() => {
                this.sending = false;
                this.$nextTick(() => { this.scrollDown(); lucide.createIcons(); });
            });
        },
        clearChat() {
            fetch('{{ route('ai.clear') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            }).then(() => { this.messages = []; });
        },
        scrollDown() {
            const log = this.$refs.log;
            if (log) log.scrollTop = log.scrollHeight;
        },
     }"
     x-init="$nextTick(() => { scrollDown(); lucide.createIcons(); })">
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden flex flex-col" style="height: 70vh;">
        <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant">
            <div class="flex items-center gap-3">
                <span class="h-10 w-10 rounded-control bg-forest-900 flex items-center justify-center">
                    <i data-lucide="sparkles" class="h-5 w-5 text-white"></i>
                </span>
                <div>
                    <p class="font-serif font-semibold text-ink">{{ $settings->dairy_name ?? 'DairyPro' }} Assistant</p>
                    <p class="text-xs text-outline">Powered by <span class="font-semibold text-ink-variant">Gorav</span><span class="font-semibold text-forest-700">AI</span> &middot; Ollama Cloud</p>
                </div>
            </div>
            <button type="button" @click="clearChat()" class="text-xs font-medium text-outline hover:text-clay flex items-center gap-1.5 transition">
                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Clear chat
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4" x-ref="log">
            <template x-if="messages.length === 0">
                <div class="h-full flex flex-col items-center justify-center text-center py-10">
                    <span class="h-14 w-14 rounded-control bg-forest-50 flex items-center justify-center mb-4"><i data-lucide="sparkles" class="h-7 w-7 text-forest-700"></i></span>
                    <p class="font-serif font-semibold text-ink mb-1">Ask me anything about your dairy business</p>
                    <p class="text-sm text-outline max-w-sm">I can see today's sales, stock, and khata numbers — try "what's my revenue today?" or "who owes the most khata?"</p>
                </div>
            </template>

            <template x-for="(message, index) in messages" :key="index">
                <div>
                    <template x-if="message.role === 'user'">
                        <div class="flex justify-end">
                            <div class="max-w-[75%] bg-forest-700 text-white rounded-bento rounded-tr-sm px-4 py-2.5 text-sm" x-text="message.content"></div>
                        </div>
                    </template>
                    <template x-if="message.role === 'assistant'">
                        <div class="flex justify-start gap-2.5">
                            <span class="h-7 w-7 rounded-control bg-forest-50 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="sparkles" class="h-3.5 w-3.5 text-forest-700"></i></span>
                            <div class="max-w-[75%] bg-surface-container-low text-ink rounded-bento rounded-tl-sm px-4 py-2.5 text-sm whitespace-pre-line" x-text="message.content"></div>
                        </div>
                    </template>
                </div>
            </template>

            <div x-show="sending" x-cloak class="flex justify-start gap-2.5">
                <span class="h-7 w-7 rounded-control bg-forest-50 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="sparkles" class="h-3.5 w-3.5 text-forest-700"></i></span>
                <div class="bg-surface-container-low text-outline rounded-bento rounded-tl-sm px-4 py-3 text-sm flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-outline animate-bounce" style="animation-delay:0ms"></span>
                    <span class="h-1.5 w-1.5 rounded-full bg-outline animate-bounce" style="animation-delay:150ms"></span>
                    <span class="h-1.5 w-1.5 rounded-full bg-outline animate-bounce" style="animation-delay:300ms"></span>
                </div>
            </div>
        </div>

        <form @submit.prevent="send()" class="p-4 border-t border-outline-variant flex items-center gap-3">
            <input type="text" x-model="draft" required autocomplete="off" placeholder="Ask about production, costs, khata, or sales..."
                   :disabled="sending"
                   class="flex-1 rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low">
            <button type="submit" :disabled="sending || !draft.trim()"
                    class="shrink-0 h-11 w-11 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant text-white flex items-center justify-center transition">
                <i data-lucide="send" class="h-4 w-4"></i>
            </button>
        </form>
    </div>
</div>
@endsection