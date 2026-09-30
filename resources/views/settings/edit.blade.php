@extends('layouts.app')
@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('settings.update') }}" class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <h3 class="font-serif text-xl font-semibold text-ink mb-1">Business Details</h3>
            <p class="text-sm text-outline">These appear on your printed invoices and receipts.</p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Dairy Name</label>
            <input type="text" name="dairy_name" value="{{ old('dairy_name', $setting->dairy_name) }}" required
                   class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $setting->phone) }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Currency Symbol</label>
                <input type="text" name="currency" value="{{ old('currency', $setting->currency) }}" required placeholder="Rs."
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Address</label>
            <textarea name="address" rows="2" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">{{ old('address', $setting->address) }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Invoice Timing / Region</label>
            <select name="invoice_region" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                @foreach(['Pakistan (PKT)', 'USA - Eastern (EST)', 'USA - Pacific (PST)', 'United Kingdom (GMT)', 'United Arab Emirates (GST)', 'India (IST)'] as $region)
                    <option value="{{ $region }}" @selected(old('invoice_region', $setting->invoice_region) === $region)>{{ $region }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-outline mt-1">Used to show the correct date/time format on printed receipts.</p>
        </div>

        {{-- AI Assistant Configuration --}}
        <div class="pt-4 border-t border-outline-variant">
            <h3 class="font-serif text-xl font-semibold text-ink mb-1">AI Assistant</h3>
            <p class="text-sm text-outline mb-4">Connect your Ollama Cloud account to enable the AI assistant.</p>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Ollama Cloud API Key</label>

                @if($setting->ollama_api_key)
                    <p class="text-[11px] text-forest-700 font-medium mb-1.5 flex items-center gap-1">
                        <i data-lucide="check-circle-2" class="h-3 w-3"></i> API key is saved (encrypted). Type below to replace it, or leave blank to keep it.
                    </p>
                @endif

                <input type="password" name="ollama_api_key"
                       value=""
                       placeholder="{{ $setting->ollama_api_key ? '•••••••••••••••• (enter new key to replace)' : 'Paste your ollama.com API key here' }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">
                    Your key is encrypted before saving.
                    <a href="https://ollama.com/settings/keys" target="_blank" class="text-forest-700 hover:underline">Get your key from ollama.com →</a>
                </p>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
            <i data-lucide="save" class="h-4 w-4"></i> Save Settings
        </button>
    </form>

    {{-- Payment Methods --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 mt-6">
        <h3 class="font-serif text-xl font-semibold text-ink mb-1">Payment Methods</h3>
        <p class="text-sm text-outline mb-5">Shown wherever a sale or purchase asks how it was paid. You can deactivate a method to hide it from POS and Milk Collection, but no payment method can be deleted. Khata and Partial Khata are system methods — they always stay active since unpaid/partial sales rely on them.</p>

        @if(session('success'))
            <div class="mb-4 rounded-control bg-forest-50 border border-forest-200 text-forest-800 px-4 py-2.5 text-sm font-medium">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-control bg-clay-container border border-clay/30 text-clay px-4 py-2.5 text-sm font-medium">{{ session('error') }}</div>
        @endif

        <div class="space-y-2 mb-5">
            @foreach($paymentMethods as $method)
                <div class="flex items-center justify-between px-4 py-2.5 rounded-control border border-outline-variant">
                    <span class="text-sm font-medium text-ink flex items-center gap-2">
                        {{ $method->name }}
                        @if($method->is_system)
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-forest-50 text-forest-700 border border-forest-300/40">System · Always Active</span>
                        @elseif(!$method->is_active)
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-clay-container text-clay border border-clay/30">Inactive</span>
                        @endif
                    </span>
                    @unless($method->is_system)
                        <x-toggle-switch :action="route('payment-methods.toggle', $method)" :active="$method->is_active" />
                    @endunless
                </div>
            @endforeach
        </div>

        <form action="{{ route('payment-methods.store') }}" method="POST" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Add Payment Method</label>
                <input type="text" name="name" placeholder="e.g. Bank Transfer" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                @error('name')<p class="mt-1.5 text-xs text-clay font-medium">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
                <i data-lucide="plus" class="h-4 w-4"></i> Add
            </button>
        </form>
    </div>
</div>
@endsection
