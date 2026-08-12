@extends('layouts.app')
@section('title', 'Khata')
@section('page-title', 'Khata (Ledger)')

@section('content')
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
                           class="flex items-center justify-between px-5 py-3.5 hover:bg-surface-container-low transition {{ $selectedCustomer?->id === $customer->id ? 'bg-forest-50' : '' }}">
                            <span class="text-sm font-medium text-ink">{{ $customer->name }}</span>
                            <span class="text-sm font-semibold tabular {{ $customer->balance > 0 ? 'text-clay' : 'text-outline' }}">
                                {{ $settings->currency }} {{ number_format($customer->balance, 0) }}
                            </span>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm text-outline">No customers yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Selected customer ledger --}}
            <div class="lg:col-span-2 space-y-6">
                @if($selectedCustomer)
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="font-serif text-xl font-semibold text-ink">{{ $selectedCustomer->name }}</h3>
                                <p class="text-sm text-outline">{{ $selectedCustomer->phone ?? 'No phone on file' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] uppercase tracking-wide text-outline">Current Balance</p>
                                <p class="text-2xl font-serif font-semibold tabular {{ $selectedCustomer->currentBalance() > 0 ? 'text-clay' : 'text-forest-700' }}">
                                    {{ $settings->currency }} {{ number_format($selectedCustomer->currentBalance(), 0) }}
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('khata.customer.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 pt-5 border-t border-outline-variant">
                            @csrf
                            <input type="hidden" name="customer_id" value="{{ $selectedCustomer->id }}">
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Type</label>
                                <select name="type" class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                                    <option value="khata">Khata Purchase</option>
                                    <option value="payment">Payment Received</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Amount</label>
                                <input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Invoice #</label>
                                <input type="text" name="invoice_no" placeholder="optional" class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Date</label>
                                <input type="date" name="transaction_date" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition">
                                    <i data-lucide="plus" class="h-4 w-4"></i> Add
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-5 py-3 font-semibold">Type</th>
                                        <th class="px-5 py-3 font-semibold">Invoice #</th>
                                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @forelse($selectedCustomer->transactions as $tx)
                                        <tr>
                                            <td class="px-5 py-3 text-outline">{{ $tx->transaction_date->format('M j, Y') }}</td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] font-semibold px-2 py-1 rounded-full {{ $tx->type === 'khata' ? 'bg-clay-container text-clay' : 'bg-forest-50 text-forest-800' }}">
                                                    {{ $tx->type === 'khata' ? 'Khata Purchase' : 'Payment Received' }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-ink-variant">{{ $tx->invoice_no ?? '—' }}</td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $tx->type === 'khata' ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $tx->type === 'khata' ? '+' : '-' }} {{ $settings->currency }} {{ number_format($tx->amount, 0) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-outline">No transactions yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-10 text-center">
                        <span class="h-12 w-12 rounded-control bg-forest-50 flex items-center justify-center mx-auto mb-3"><i data-lucide="user" class="h-6 w-6 text-forest-700"></i></span>
                        <p class="text-ink-variant text-sm">Select a customer from the list to view their khata ledger.</p>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Supplier list --}}
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden lg:col-span-1">
                <div class="p-5 border-b border-outline-variant">
                    <h3 class="font-serif font-semibold text-ink">Suppliers</h3>
                </div>
                <div class="max-h-[32rem] overflow-y-auto divide-y divide-outline-variant/60">
                    @forelse($suppliers as $supplier)
                        <a href="{{ route('khata.index', ['tab' => 'supplier', 'id' => $supplier->id]) }}"
                           class="flex items-center justify-between px-5 py-3.5 hover:bg-surface-container-low transition {{ $selectedSupplier?->id === $supplier->id ? 'bg-forest-50' : '' }}">
                            <span class="text-sm font-medium text-ink">{{ $supplier->name }}</span>
                            <span class="text-sm font-semibold tabular {{ $supplier->balance > 0 ? 'text-clay' : 'text-outline' }}">
                                {{ $settings->currency }} {{ number_format($supplier->balance, 0) }}
                            </span>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm text-outline">No suppliers yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Selected supplier ledger --}}
            <div class="lg:col-span-2 space-y-6">
                @if($selectedSupplier)
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="font-serif text-xl font-semibold text-ink">{{ $selectedSupplier->name }}</h3>
                                <p class="text-sm text-outline">{{ $selectedSupplier->phone ?? 'No phone on file' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] uppercase tracking-wide text-outline">Amount Owed</p>
                                <p class="text-2xl font-serif font-semibold tabular {{ $selectedSupplier->currentBalance() > 0 ? 'text-clay' : 'text-forest-700' }}">
                                    {{ $settings->currency }} {{ number_format($selectedSupplier->currentBalance(), 0) }}
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('khata.supplier.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 pt-5 border-t border-outline-variant">
                            @csrf
                            <input type="hidden" name="supplier_id" value="{{ $selectedSupplier->id }}">
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Type</label>
                                <select name="type" class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                                    <option value="purchase">Purchase (we owe more)</option>
                                    <option value="payment">Payment Made</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Amount</label>
                                <input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Reference</label>
                                <input type="text" name="reference" placeholder="optional" class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Date</label>
                                <input type="date" name="transaction_date" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition">
                                    <i data-lucide="plus" class="h-4 w-4"></i> Add
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-5 py-3 font-semibold">Type</th>
                                        <th class="px-5 py-3 font-semibold">Reference</th>
                                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @forelse($selectedSupplier->transactions as $tx)
                                        <tr>
                                            <td class="px-5 py-3 text-outline">{{ $tx->transaction_date->format('M j, Y') }}</td>
                                            <td class="px-5 py-3">
                                                <span class="text-[11px] font-semibold px-2 py-1 rounded-full {{ $tx->type === 'purchase' ? 'bg-clay-container text-clay' : 'bg-forest-50 text-forest-800' }}">
                                                    {{ $tx->type === 'purchase' ? 'Purchase' : 'Payment Made' }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-ink-variant">{{ $tx->reference ?? '—' }}</td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $tx->type === 'purchase' ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $tx->type === 'purchase' ? '+' : '-' }} {{ $settings->currency }} {{ number_format($tx->amount, 0) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-outline">No transactions yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-10 text-center">
                        <span class="h-12 w-12 rounded-control bg-forest-50 flex items-center justify-center mx-auto mb-3"><i data-lucide="tractor" class="h-6 w-6 text-forest-700"></i></span>
                        <p class="text-ink-variant text-sm">Select a supplier from the list to view their khata ledger.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
