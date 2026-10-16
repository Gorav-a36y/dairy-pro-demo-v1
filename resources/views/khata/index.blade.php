@extends('layouts.app')
@section('title', 'Khata')
@section('page-title', 'Khata (Ledger)')

@section('content')
@php
    // 1500 -> 1,500   1500.4 -> 1,500.40   (the paisa is never hidden)
    $fmt = fn ($v) => abs((float) $v - round((float) $v)) < 0.005
        ? number_format((float) $v, 0)
        : number_format((float) $v, 2);
@endphp
<div class="space-y-6">
    {{-- Tabs --}}
    <div class="inline-flex rounded-control border border-outline-variant bg-white p-1">
        <a href="{{ route('khata.index', ['tab' => 'customer']) }}"
           class="px-4 py-2 rounded-control text-sm font-semibold transition {{ $tab === 'customer' ? 'bg-forest-700 text-white' : 'text-ink-variant hover:bg-surface-container-low' }}">
            Customer Khata
        </a>
        <a href="{{ route('khata.index', ['tab' => 'supplier']) }}"
           class="px-4 py-2 rounded-control text-sm font-semibold transition {{ $tab === 'supplier' ? 'bg-forest-700 text-white' : 'text-ink-variant hover:bg-surface-container-low' }}">
            Supplier Khata
        </a>
    </div>

    @if(session('error'))
    <div class="rounded-control bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium flex items-center gap-2">
        <i data-lucide="alert-circle" class="h-4 w-4"></i>
        {{ session('error') }}
    </div>
    @endif
    @if(session('success'))
        <div class="rounded-control bg-forest-50 border border-forest-200 text-forest-800 px-4 py-3 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="h-4 w-4"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($tab === 'customer')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Customer list --}}
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden lg:col-span-1">
                <div class="p-5 border-b border-outline-variant">
                    <h3 class="font-serif font-semibold text-ink">Customers</h3>
                </div>
                <div class="max-h-[32rem] overflow-y-auto divide-y divide-outline-variant/60">
                    @forelse($customers as $customer)
                        <a href="{{ route('khata.index', ['tab' => 'customer', 'id' => $customer->id]) }}"
                           class="flex items-center justify-between px-5 py-3.5 hover:bg-surface-container-low transition {{ $selectedCustomer?->id === $customer->id ? 'bg-forest-50 border-l-4 border-l-forest-700' : '' }}">
                            <span class="text-sm font-medium text-ink">{{ $customer->name }}</span>
                            @if($selectedCustomer?->id === $customer->id)
                                <i data-lucide="chevron-right" class="h-4 w-4 text-forest-700"></i>
                            @endif
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm text-outline">No customers yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Selected customer ledger --}}
            <div class="lg:col-span-2 space-y-6">
                @if($selectedCustomer)
                    @php
                        $balance = $selectedCustomer->balance;
                        $isCleared = $balance < 0.01;
                    @endphp

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="font-serif text-xl font-semibold text-ink">{{ $selectedCustomer->name }}</h3>
                                <p class="text-sm text-outline">{{ $selectedCustomer->phone ?? 'No phone on file' }}</p>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-3 mb-1">
                                    <p class="text-[11px] uppercase tracking-wide text-outline">Remaining Balance</p>
                                    @if($isCleared)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800 border border-forest-200">
                                            <i data-lucide="check-circle-2" class="h-3 w-3"></i> Account Clear
                                        </span>
                                    @endif
                                </div>
                                <p class="text-2xl font-serif font-semibold tabular {{ $balance > 0 ? 'text-clay' : ($balance < 0 ? 'text-forest-700' : 'text-outline') }}">
                                    {{ $settings->currency }} {{ $fmt($balance) }}
                                </p>
                            </div>
                        </div>

                        {{-- Payment form --}}
                        <div class="{{ $isCleared ? 'opacity-60 pointer-events-none select-none' : '' }} transition-opacity">
                            <form id="customer-payment-form" method="POST" action="{{ route('khata.customer.store') }}"
                                  onkeydown="khataFormShortcut(event)" onsubmit="khataOnSubmit(event)" class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-5 border-t border-outline-variant">
                                @csrf
                                <input type="hidden" name="customer_id" value="{{ $selectedCustomer->id }}">
                                <input type="hidden" name="submit_token" value="{{ $submitToken }}">

                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Amount Received</label>
                                    <input type="number" step="0.01" min="0.01" max="{{ $balance }}" name="amount" required placeholder="0.00"
                                           data-max-balance="{{ $balance }}"
                                           oninput="khataGuardAmount(this)"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                    <p class="khata-amount-error hidden text-[11px] text-red-600 mt-1">Amount cannot exceed the remaining balance.</p>
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Note</label>
                                    <input type="text" name="notes" placeholder="Optional details"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                </div>
                                <div class="sm:col-span-2 flex items-end gap-2">
                                    <button type="submit" name="action" value="save"
                                            class="khata-submit-btn flex-1 inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition disabled:bg-outline-variant disabled:text-outline disabled:cursor-not-allowed"
                                            {{ $isCleared ? 'disabled' : '' }}>
                                        <i data-lucide="check-circle" class="h-4 w-4"></i> Save
                                    </button>
                                    <button type="submit" name="action" value="save_print"
                                            class="khata-submit-btn flex-1 inline-flex items-center justify-center gap-2 rounded-control border border-forest-700 text-forest-700 hover:bg-forest-50 text-sm font-semibold px-4 py-2.5 transition disabled:border-outline-variant disabled:text-outline disabled:cursor-not-allowed"
                                            {{ $isCleared ? 'disabled' : '' }}>
                                        <i data-lucide="printer" class="h-4 w-4"></i> Save & Print
                                    </button>
                                </div>
                            </form>
                            <p class="text-[11px] text-outline mt-2">Enter = Save · Shift+Enter = Save & Print</p>
                            @if($isCleared)
                                <p class="mt-3 text-sm text-forest-800 font-medium flex items-center gap-1.5">
                                    <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                                    This account is fully settled. No payment can be recorded.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Search + filter + download --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full sm:max-w-2xl">
                            <input type="hidden" name="tab" value="customer">
                            <input type="hidden" name="id" value="{{ $selectedCustomer->id }}">
                            <x-search-bar name="invoice" placeholder="Search invoice number..." icon="receipt" />
                            <input type="date" name="from" value="{{ request('from') }}" class="rounded-control border border-outline-variant bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            <input type="date" name="to" value="{{ request('to') }}" class="rounded-control border border-outline-variant bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition shrink-0">
                                <i data-lucide="filter" class="h-4 w-4"></i> Filter
                            </button>
                        </form>
                        <a href="{{ route('khata.exportCsv', ['tab' => 'customer', 'id' => $selectedCustomer->id, 'from' => request('from'), 'to' => request('to'), 'invoice' => request('invoice')]) }}"
                           class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition shrink-0">
                            <i data-lucide="download" class="h-4 w-4"></i> Download CSV
                        </a>
                    </div>

                    {{-- Ledger table --}}
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-5 py-3 font-semibold">Particulars</th>
                                        <th class="px-5 py-3 font-semibold">Note</th>
                                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                                        <th class="px-5 py-3 font-semibold text-center">Receipt</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @forelse($selectedCustomer->transactions as $tx)
                                        <tr>
                                            <td class="px-5 py-3 text-outline whitespace-nowrap">{{ $tx->transaction_date->format('M j, Y') }}</td>
                                            <td class="px-5 py-3">
                                                @if($tx->type === 'khata')
                                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-clay-container text-clay">
                                                        <i data-lucide="shopping-bag" class="h-3 w-3"></i> Credit Sale
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800">
                                                        <i data-lucide="banknote" class="h-3 w-3"></i> Payment Received
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-ink-variant">
                                                @if($tx->invoice_no)
                                                    <span class="font-semibold text-ink">Invoice #{{ $tx->invoice_no }}</span>
                                                @endif
                                                <p class="text-xs text-outline mt-0.5">{{ $tx->notes ?: '—' }}</p>
                                            </td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $tx->type === 'khata' ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $tx->type === 'khata' ? '+' : '-' }} {{ $settings->currency }} {{ $fmt($tx->amount) }}
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                @if($tx->type === 'khata' && $tx->sale_id)
                                                    <a href="{{ route('sales.show', $tx->sale_id) }}" target="_blank"
                                                       class="inline-flex items-center justify-center h-8 w-8 rounded-control hover:bg-surface-container-low text-ink-variant transition" title="View Receipt">
                                                        <i data-lucide="eye" class="h-4 w-4"></i>
                                                    </a>
                                                @elseif($tx->type === 'payment')
                                                    <a href="{{ route('khata.customer.receipt', $tx->id) }}" target="_blank"
                                                       class="inline-flex items-center justify-center h-8 w-8 rounded-control hover:bg-surface-container-low text-ink-variant transition" title="View Receipt">
                                                        <i data-lucide="eye" class="h-4 w-4"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-8 text-center text-outline">
                                                No transactions match your filters.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-10 text-center">
                        <span class="h-12 w-12 rounded-control bg-forest-50 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="user" class="h-6 w-6 text-forest-700"></i>
                        </span>
                        <p class="text-ink-variant text-sm">Select a customer from the list to view their khata ledger.</p>
                    </div>
                @endif
            </div>
        </div>

    @else
        {{-- ==================== SUPPLIER SIDE ==================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden lg:col-span-1">
                <div class="p-5 border-b border-outline-variant">
                    <h3 class="font-serif font-semibold text-ink">Suppliers</h3>
                </div>
                <div class="max-h-[32rem] overflow-y-auto divide-y divide-outline-variant/60">
                    @forelse($suppliers as $supplier)
                        <a href="{{ route('khata.index', ['tab' => 'supplier', 'id' => $supplier->id]) }}"
                           class="flex items-center justify-between px-5 py-3.5 hover:bg-surface-container-low transition {{ $selectedSupplier?->id === $supplier->id ? 'bg-forest-50 border-l-4 border-l-forest-700' : '' }}">
                            <span class="text-sm font-medium text-ink">{{ $supplier->name }}</span>
                            @if($selectedSupplier?->id === $supplier->id)
                                <i data-lucide="chevron-right" class="h-4 w-4 text-forest-700"></i>
                            @endif
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm text-outline">No suppliers yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                @if($selectedSupplier)
                    @php
                        $balance = $selectedSupplier->balance;
                        $isCleared = $balance < 0.01;
                    @endphp

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="font-serif text-xl font-semibold text-ink">{{ $selectedSupplier->name }}</h3>
                                <p class="text-sm text-outline">{{ $selectedSupplier->phone ?? 'No phone on file' }}</p>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-3 mb-1">
                                    <p class="text-[11px] uppercase tracking-wide text-outline">Remaining Balance</p>
                                    @if($isCleared)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800 border border-forest-200">
                                            <i data-lucide="check-circle-2" class="h-3 w-3"></i> Account Clear
                                        </span>
                                    @endif
                                </div>
                                <p class="text-2xl font-serif font-semibold tabular {{ $balance > 0 ? 'text-clay' : ($balance < 0 ? 'text-forest-700' : 'text-outline') }}">
                                    {{ $settings->currency }} {{ $fmt($balance) }}
                                </p>
                            </div>
                        </div>

                        {{-- Payment form --}}
                        <div class="{{ $isCleared ? 'opacity-60 pointer-events-none select-none' : '' }} transition-opacity">
                            <form id="supplier-payment-form" method="POST" action="{{ route('khata.supplier.store') }}"
                                  onkeydown="khataFormShortcut(event)" onsubmit="khataOnSubmit(event)" class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-5 border-t border-outline-variant">
                                @csrf
                                <input type="hidden" name="supplier_id" value="{{ $selectedSupplier->id }}">
                                <input type="hidden" name="submit_token" value="{{ $submitToken }}">

                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Amount Paid</label>
                                    <input type="number" step="0.01" min="0.01" max="{{ $balance }}" name="amount" required placeholder="0.00"
                                           data-max-balance="{{ $balance }}"
                                           oninput="khataGuardAmount(this)"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                    <p class="khata-amount-error hidden text-[11px] text-red-600 mt-1">Amount cannot exceed the remaining balance.</p>
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Note</label>
                                    <input type="text" name="notes" placeholder="Optional details"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                </div>
                                <div class="sm:col-span-2 flex items-end gap-2">
                                    <button type="submit" name="action" value="save"
                                            class="khata-submit-btn flex-1 inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition disabled:bg-outline-variant disabled:text-outline disabled:cursor-not-allowed"
                                            {{ $isCleared ? 'disabled' : '' }}>
                                        <i data-lucide="check-circle" class="h-4 w-4"></i> Save
                                    </button>
                                    <button type="submit" name="action" value="save_print"
                                            class="khata-submit-btn flex-1 inline-flex items-center justify-center gap-2 rounded-control border border-forest-700 text-forest-700 hover:bg-forest-50 text-sm font-semibold px-4 py-2.5 transition disabled:border-outline-variant disabled:text-outline disabled:cursor-not-allowed"
                                            {{ $isCleared ? 'disabled' : '' }}>
                                        <i data-lucide="printer" class="h-4 w-4"></i> Save & Print
                                    </button>
                                </div>
                            </form>
                            <p class="text-[11px] text-outline mt-2">Enter = Save · Shift+Enter = Save & Print</p>
                            @if($isCleared)
                                <p class="mt-3 text-sm text-forest-800 font-medium flex items-center gap-1.5">
                                    <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                                    This account is fully settled. No payment can be recorded.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Search + filter + download --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full sm:max-w-2xl">
                            <input type="hidden" name="tab" value="supplier">
                            <input type="hidden" name="id" value="{{ $selectedSupplier->id }}">
                            <x-search-bar name="invoice" placeholder="Search invoice number..." icon="receipt" />
                            <input type="date" name="from" value="{{ request('from') }}" class="rounded-control border border-outline-variant bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            <input type="date" name="to" value="{{ request('to') }}" class="rounded-control border border-outline-variant bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition shrink-0">
                                <i data-lucide="filter" class="h-4 w-4"></i> Filter
                            </button>
                        </form>
                        <a href="{{ route('khata.exportCsv', ['tab' => 'supplier', 'id' => $selectedSupplier->id, 'from' => request('from'), 'to' => request('to'), 'invoice' => request('invoice')]) }}"
                           class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition shrink-0">
                            <i data-lucide="download" class="h-4 w-4"></i> Download CSV
                        </a>
                    </div>

                    {{-- Ledger table --}}
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-5 py-3 font-semibold">Particulars</th>
                                        <th class="px-5 py-3 font-semibold">Note</th>
                                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                                        <th class="px-5 py-3 font-semibold text-center">Receipt</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @forelse($selectedSupplier->transactions as $tx)
                                        <tr>
                                            <td class="px-5 py-3 text-outline whitespace-nowrap">{{ $tx->transaction_date->format('M j, Y') }}</td>
                                            <td class="px-5 py-3">
                                                @if($tx->type === 'purchase')
                                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-clay-container text-clay">
                                                        <i data-lucide="truck" class="h-3 w-3"></i> Milk Purchase
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800">
                                                        <i data-lucide="banknote" class="h-3 w-3"></i> Payment Made
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-ink-variant">
                                                @if($tx->invoice_no)
                                                    <span class="font-semibold text-ink">Invoice #{{ $tx->invoice_no }}</span>
                                                @endif
                                                <p class="text-xs text-outline mt-0.5">{{ $tx->notes ?: '—' }}</p>
                                            </td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $tx->type === 'purchase' ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $tx->type === 'purchase' ? '+' : '-' }} {{ $settings->currency }} {{ $fmt($tx->amount) }}
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                @if($tx->type === 'purchase' && $tx->milk_collection_id)
                                                    <a href="{{ route('milk-collections.receipt', $tx->milk_collection_id) }}" target="_blank"
                                                       class="inline-flex items-center justify-center h-8 w-8 rounded-control hover:bg-surface-container-low text-ink-variant transition" title="View Receipt">
                                                        <i data-lucide="eye" class="h-4 w-4"></i>
                                                    </a>
                                                @elseif($tx->type === 'payment')
                                                    <a href="{{ route('khata.supplier.receipt', $tx->id) }}" target="_blank"
                                                       class="inline-flex items-center justify-center h-8 w-8 rounded-control hover:bg-surface-container-low text-ink-variant transition" title="View Receipt">
                                                        <i data-lucide="eye" class="h-4 w-4"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-8 text-center text-outline">
                                                No transactions match your filters.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-10 text-center">
                        <span class="h-12 w-12 rounded-control bg-forest-50 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="tractor" class="h-6 w-6 text-forest-700"></i>
                        </span>
                        <p class="text-ink-variant text-sm">Select a supplier from the list to view their khata ledger.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Shift+Enter = Save & Print (plain Enter already submits Save, the first button).
    function khataFormShortcut(event) {
        if (event.key !== 'Enter' || !event.shiftKey) return;
        event.preventDefault();

        const form = event.currentTarget;
        const printBtn = form.querySelector('button[value="save_print"]');
        // Respect the button: if it is disabled (amount too high, account settled,
        // already saving) the shortcut must do nothing too.
        if (!printBtn || printBtn.disabled) return;

        // The button itself is the submitter, so action=save_print is sent and
        // no leftover hidden field can override a later click on Save.
        form.requestSubmit(printBtn);
    }

    // Live guard: disables Save/Save & Print the instant the typed amount
    // exceeds the remaining balance. Backend still re-checks on submit —
    // this is just so the user sees it before they click.
    function khataGuardAmount(input) {
        const form = input.closest('form');
        if (form.dataset.sent === '1') return; // already saving: keep buttons locked

        const max = parseFloat(input.dataset.maxBalance || '0');
        const value = parseFloat(input.value || '0');
        const errorEl = form.querySelector('.khata-amount-error');
        const buttons = form.querySelectorAll('.khata-submit-btn');

        const overLimit = value > max + 0.01;

        buttons.forEach(btn => btn.disabled = overLimit);
        if (errorEl) errorEl.classList.toggle('hidden', !overLimit);
    }

    // Runs when the form is being sent: blocks a 2nd click / Enter, then shows 'Saving...'.
    function khataOnSubmit(event) {
        const form = event.currentTarget;

        // Already sending? Ignore any further click or Enter.
        if (form.dataset.sent === '1') { event.preventDefault(); return; }
        form.dataset.sent = '1';

        const submitter = event.submitter || null;
        const buttons = form.querySelectorAll('.khata-submit-btn');

        // Disable only AFTER the browser has taken the form data. Disabling first
        // would drop the clicked button's value (action=save / save_print).
        setTimeout(function () {
            buttons.forEach(function (btn) {
                btn.dataset.html = btn.innerHTML;
                btn.disabled = true;
                if (btn === submitter) {
                    btn.innerHTML = '<span class="h-4 w-4 rounded-full border-2 border-current border-t-transparent animate-spin"></span> Saving...';
                }
            });
        }, 0);

        // Still on this page after 20s = the request failed or was cancelled
        // (a successful save leaves this page). Unlock so the user can try again;
        // the one-time token on the server stops any double payment.
        setTimeout(function () {
            form.dataset.sent = '0';
            buttons.forEach(function (btn) {
                if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
                btn.disabled = false;
            });
            const amount = form.querySelector('input[name="amount"]');
            if (amount) khataGuardAmount(amount);
        }, 20000);
    }

    // Back button can show an old copy of this page (old token). Load a fresh one.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) window.location.reload();
    });
</script>
@endpush
