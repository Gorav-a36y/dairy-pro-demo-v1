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
                           class="flex items-center justify-between px-5 py-3.5 hover:bg-surface-container-low transition {{ $selectedCustomer?->id === $customer->id ? 'bg-forest-50 border-l-4 border-l-forest-700' : '' }}">
                            <span class="text-sm font-medium text-ink">{{ $customer->name }}</span>
                            <span class="text-sm font-semibold tabular {{ $customer->balance > 0 ? 'text-clay' : ($customer->balance < 0 ? 'text-forest-700' : 'text-outline') }}">
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
                    @php
                        $balance = $selectedCustomer->balance;
                        $isCleared = abs($balance) < 0.01;
                    @endphp

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="font-serif text-xl font-semibold text-ink">{{ $selectedCustomer->name }}</h3>
                                <p class="text-sm text-outline">{{ $selectedCustomer->phone ?? 'No phone on file' }}</p>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-3 mb-1">
                                    <p class="text-[11px] uppercase tracking-wide text-outline">Current Balance</p>
                                    @if($isCleared)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800 border border-forest-200">
                                            <i data-lucide="check-circle-2" class="h-3 w-3"></i> Account Clear
                                        </span>
                                    @endif
                                </div>
                                <p class="text-2xl font-serif font-semibold tabular {{ $balance > 0 ? 'text-clay' : ($balance < 0 ? 'text-forest-700' : 'text-outline') }}">
                                    {{ $settings->currency }} {{ number_format($balance, 0) }}
                                </p>
                            </div>
                        </div>

                        {{-- Payment form: ONLY 2 visible inputs (Amount + Note) --}}
                        <div class="{{ $isCleared ? 'opacity-60 pointer-events-none select-none' : '' }} transition-opacity">
                            <form method="POST" action="{{ route('khata.customer.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-5 border-t border-outline-variant">
                                @csrf
                                <input type="hidden" name="customer_id" value="{{ $selectedCustomer->id }}">
                                <input type="hidden" name="transaction_date" value="{{ now()->format('Y-m-d') }}">

                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Amount Received</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Note</label>
                                    <input type="text" name="notes" placeholder="Optional details"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                </div>
                                <div class="flex items-end">
                                    <button type="submit"
                                            class="w-full inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition disabled:bg-outline-variant disabled:text-outline disabled:cursor-not-allowed"
                                            {{ $isCleared ? 'disabled' : '' }}>
                                        <i data-lucide="banknote" class="h-4 w-4"></i> Record Payment
                                    </button>
                                </div>
                            </form>
                            @if($isCleared)
                                <p class="mt-3 text-sm text-forest-800 font-medium flex items-center gap-1.5">
                                    <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                                    This account is fully settled. No payment can be recorded.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Ledger table --}}
                    @php
                        $runningBalance = 0;
                        $transactions = $selectedCustomer->transactions->sortBy(['transaction_date', 'id'])->values();
                    @endphp

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-5 py-3 font-semibold">Particulars</th>
                                        <th class="px-5 py-3 font-semibold">Note</th>
                                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                                        <th class="px-5 py-3 font-semibold text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @forelse($transactions as $tx)
                                        @php
                                            if($tx->type === 'khata') $runningBalance += $tx->amount;
                                            elseif($tx->type === 'payment') $runningBalance -= $tx->amount;
                                        @endphp
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
                                                @if($tx->type === 'khata')
                                                    {{-- Show invoice number for khata (sale) --}}
                                                    <span class="font-medium text-ink">Invoice #{{ $tx->invoice_no }}</span>
                                                    @if($tx->notes && $tx->notes !== 'Sale on khata')
                                                        <p class="text-xs text-outline mt-0.5">{{ $tx->notes }}</p>
                                                    @endif
                                                @else
                                                    {{-- Payment: auto-show "Payment Received" if notes empty --}}
                                                    @if(!$tx->notes)
                                                        <span class="text-forest-700 font-medium">Payment Received</span>
                                                    @else
                                                        <span class="text-ink-variant">{{ $tx->notes }}</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $tx->type === 'khata' ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $tx->type === 'khata' ? '+' : '-' }} {{ $settings->currency }} {{ number_format($tx->amount, 0) }}
                                            </td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $runningBalance > 0 ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $settings->currency }} {{ number_format($runningBalance, 0) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-8 text-center text-outline">
                                                No transactions yet. Credit sales from the POS will appear here automatically.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if($transactions->isNotEmpty())
                                    <tfoot class="border-t-2 border-outline-variant bg-surface-container-low/40">
                                        <tr>
                                            <td colspan="4" class="px-5 py-3 text-right text-xs font-semibold text-ink-variant uppercase tracking-wide">Closing Balance</td>
                                            <td class="px-5 py-3 text-right font-serif text-lg font-semibold tabular {{ $runningBalance > 0 ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $settings->currency }} {{ number_format($runningBalance, 0) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                @endif
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
            {{-- Supplier list --}}
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden lg:col-span-1">
                <div class="p-5 border-b border-outline-variant">
                    <h3 class="font-serif font-semibold text-ink">Suppliers</h3>
                </div>
                <div class="max-h-[32rem] overflow-y-auto divide-y divide-outline-variant/60">
                    @forelse($suppliers as $supplier)
                        <a href="{{ route('khata.index', ['tab' => 'supplier', 'id' => $supplier->id]) }}"
                           class="flex items-center justify-between px-5 py-3.5 hover:bg-surface-container-low transition {{ $selectedSupplier?->id === $supplier->id ? 'bg-forest-50 border-l-4 border-l-forest-700' : '' }}">
                            <span class="text-sm font-medium text-ink">{{ $supplier->name }}</span>
                            <span class="text-sm font-semibold tabular {{ $supplier->balance > 0 ? 'text-clay' : ($supplier->balance < 0 ? 'text-forest-700' : 'text-outline') }}">
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
                    @php
                        $balance = $selectedSupplier->balance;
                        $isCleared = abs($balance) < 0.01;
                    @endphp

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="font-serif text-xl font-semibold text-ink">{{ $selectedSupplier->name }}</h3>
                                <p class="text-sm text-outline">{{ $selectedSupplier->phone ?? 'No phone on file' }}</p>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center justify-end gap-3 mb-1">
                                    <p class="text-[11px] uppercase tracking-wide text-outline">Amount Owed</p>
                                    @if($isCleared)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800 border border-forest-200">
                                            <i data-lucide="check-circle-2" class="h-3 w-3"></i> Account Clear
                                        </span>
                                    @endif
                                </div>
                                <p class="text-2xl font-serif font-semibold tabular {{ $balance > 0 ? 'text-clay' : ($balance < 0 ? 'text-forest-700' : 'text-outline') }}">
                                    {{ $settings->currency }} {{ number_format($balance, 0) }}
                                </p>
                            </div>
                        </div>

                        {{-- Payment form: ONLY 2 visible inputs (Amount + Note) --}}
                        <div class="{{ $isCleared ? 'opacity-60 pointer-events-none select-none' : '' }} transition-opacity">
                            <form method="POST" action="{{ route('khata.supplier.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-5 border-t border-outline-variant">
                                @csrf
                                <input type="hidden" name="supplier_id" value="{{ $selectedSupplier->id }}">
                                <input type="hidden" name="transaction_date" value="{{ now()->format('Y-m-d') }}">

                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Amount Paid</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Note</label>
                                    <input type="text" name="notes" placeholder="Optional details"
                                           class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                           {{ $isCleared ? 'disabled' : '' }}>
                                </div>
                                <div class="flex items-end">
                                    <button type="submit"
                                            class="w-full inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition disabled:bg-outline-variant disabled:text-outline disabled:cursor-not-allowed"
                                            {{ $isCleared ? 'disabled' : '' }}>
                                        <i data-lucide="banknote" class="h-4 w-4"></i> Record Payment
                                    </button>
                                </div>
                            </form>
                            @if($isCleared)
                                <p class="mt-3 text-sm text-forest-800 font-medium flex items-center gap-1.5">
                                    <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                                    This account is fully settled. No payment can be recorded.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Ledger table --}}
                    @php
                        $runningBalance = 0;
                        $transactions = $selectedSupplier->transactions->sortBy(['transaction_date', 'id'])->values();
                    @endphp

                    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                        <th class="px-5 py-3 font-semibold">Date</th>
                                        <th class="px-5 py-3 font-semibold">Particulars</th>
                                        <th class="px-5 py-3 font-semibold">Note</th>
                                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                                        <th class="px-5 py-3 font-semibold text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @forelse($transactions as $tx)
                                        @php
                                            if($tx->type === 'purchase') $runningBalance += $tx->amount;
                                            elseif($tx->type === 'payment') $runningBalance -= $tx->amount;
                                        @endphp
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
                                                @if($tx->type === 'purchase')
                                                    {{-- Show reference for purchase (supplier schema uses 'reference') --}}
                                                    @if($tx->reference)
                                                        <span class="font-medium text-ink">Ref: {{ $tx->reference }}</span>
                                                    @endif
                                                    @if($tx->notes)
                                                        <p class="text-xs text-outline mt-0.5">{{ $tx->notes }}</p>
                                                    @endif
                                                    @if(!$tx->reference && !$tx->notes)
                                                        <span class="text-outline">—</span>
                                                    @endif
                                                @else
                                                    {{-- Payment: auto-show "Payment Received" if notes empty --}}
                                                    @if(!$tx->notes)
                                                        <span class="text-forest-700 font-medium">Payment Received</span>
                                                    @else
                                                        <span class="text-ink-variant">{{ $tx->notes }}</span>
                                                    @endif
                                                    @if($tx->reference)
                                                        <p class="text-xs text-outline mt-0.5">Ref: {{ $tx->reference }}</p>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $tx->type === 'purchase' ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $tx->type === 'purchase' ? '+' : '-' }} {{ $settings->currency }} {{ number_format($tx->amount, 0) }}
                                            </td>
                                            <td class="px-5 py-3 text-right tabular font-semibold {{ $runningBalance > 0 ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $settings->currency }} {{ number_format($runningBalance, 0) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-8 text-center text-outline">
                                                No transactions yet. Milk collections will appear here automatically.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if($transactions->isNotEmpty())
                                    <tfoot class="border-t-2 border-outline-variant bg-surface-container-low/40">
                                        <tr>
                                            <td colspan="4" class="px-5 py-3 text-right text-xs font-semibold text-ink-variant uppercase tracking-wide">Closing Balance</td>
                                            <td class="px-5 py-3 text-right font-serif text-lg font-semibold tabular {{ $runningBalance > 0 ? 'text-clay' : 'text-forest-700' }}">
                                                {{ $settings->currency }} {{ number_format($runningBalance, 0) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                @endif
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