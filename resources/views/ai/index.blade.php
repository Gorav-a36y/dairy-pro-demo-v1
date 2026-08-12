@extends('layouts.app')
@section('title', 'AI Assistant')
@section('page-title', 'AI Assistant')

@section('content')
<div class="max-w-3xl mx-auto">
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
            <form method="POST" action="{{ route('ai.clear') }}">
                @csrf
                <button class="text-xs font-medium text-outline hover:text-clay flex items-center gap-1.5 transition">
                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Clear chat
                </button>
            </form>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chat-log">
            @forelse($history as $message)
                @if($message['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[75%] bg-forest-700 text-white rounded-bento rounded-tr-sm px-4 py-2.5 text-sm">{{ $message['content'] }}</div>
                    </div>
                @else
                    <div class="flex justify-start gap-2.5">
                        <span class="h-7 w-7 rounded-control bg-forest-50 flex items-center justify-center shrink-0 mt-0.5"><i data-lucide="sparkles" class="h-3.5 w-3.5 text-forest-700"></i></span>
                        <div class="max-w-[75%] bg-surface-container-low text-ink rounded-bento rounded-tl-sm px-4 py-2.5 text-sm whitespace-pre-line">{{ $message['content'] }}</div>
                    </div>
                @endif
            @empty
                <div class="h-full flex flex-col items-center justify-center text-center py-10">
                    <span class="h-14 w-14 rounded-control bg-forest-50 flex items-center justify-center mb-4"><i data-lucide="sparkles" class="h-7 w-7 text-forest-700"></i></span>
                    <p class="font-serif font-semibold text-ink mb-1">Ask me anything about your dairy business</p>
                    <p class="text-sm text-outline max-w-sm">Production costing, batch sizing, khata trends, sales trends — I'm here to help.</p>
                </div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('ai.send') }}" class="p-4 border-t border-outline-variant flex items-center gap-3">
            @csrf
            <input type="text" name="message" required autocomplete="off" placeholder="Ask about production, costs, or sales..."
                   class="flex-1 rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            <button type="submit" class="shrink-0 h-11 w-11 rounded-control bg-forest-700 hover:bg-forest-800 text-white flex items-center justify-center transition">
                <i data-lucide="send" class="h-4 w-4"></i>
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const log = document.getElementById('chat-log');
    if (log) log.scrollTop = log.scrollHeight;
</script>
@endpush
