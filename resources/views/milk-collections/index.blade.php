@extends('layouts.app')
@section('title', 'Milk Collection')
@section('page-title', 'Milk Collection')

@section('content')
<div class="space-y-6" x-data="{ showForm: false }">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="milk" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ number_format($todayQty, 1) }}</p>
            <p class="text-xs text-outline mt-1">Collected Today</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="calendar-days" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ number_format($monthQty, 1) }}</p>
            <p class="text-xs text-outline mt-1">This Month</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="wallet" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $settings->currency }} {{ number_format($monthSpend, 0) }}</p>
            <p class="text-xs text-outline mt-1">Spent This Month</p>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div>
            <h3 class="font-serif text-lg font-semibold text-ink">Collection History</h3>
            <p class="text-sm text-outline">Products bought from farmers and suppliers.</p>
        </div>
        <button type="button" @click="showForm = !showForm"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 shadow-bento-sm transition">
            <i data-lucide="plus" class="h-4 w-4"></i>
            <span x-text="showForm ? 'Close Form' : 'Record Collection'"></span>
        </button>
    </div>

    <div x-show="showForm" x-cloak x-transition
         x-data="{
            quantity: '', purchasePrice: '',
            get total() { return ((parseFloat(this.quantity)||0) * (parseFloat(this.purchasePrice)||0)).toFixed(0) }
         }"
         class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <form method="POST" action="{{ route('milk-collections.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @csrf
            <x-searchable-select name="supplier_id" :options="$suppliers" label="Supplier" placeholder="Search supplier..." required />

            <x-searchable-select name="product_id" :options="$products" label="Product" placeholder="Search product..." required />

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Quantity</label>
                <input type="number" step="0.01" min="0.01" name="quantity" x-model="quantity" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">Unit is taken automatically from the selected product.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Purchase Price (per unit)</label>
                <input type="number" step="0.01" min="0" name="purchase_price" x-model="purchasePrice" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Total (auto-calculated)</label>
                <div class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-3 text-sm font-semibold text-ink tabular">
                    {{ $settings->currency ?? 'Rs.' }} <span x-text="total"></span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Paid Amount</label>
                <input type="number" step="0.01" min="0" name="paid_amount" value="0"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">Leave 0 if this purchase goes fully onto the supplier's khata.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Payment Method</label>
                <select name="payment_method" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    <option value="cash" selected>Cash</option>
                    <option value="easypaisa">Easypaisa</option>
                    <option value="jazzcash">JazzCash</option>
                </select>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Notes (optional)</label>
                <input type="text" name="notes" placeholder="e.g. quality checked, morning delivery"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
                    <i data-lucide="save" class="h-4 w-4"></i> Save Collection
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Supplier</th>
                        <th class="px-6 py-3 font-semibold">Product</th>
                        <th class="px-6 py-3 font-semibold">Date &amp; Time</th>
                        <th class="px-6 py-3 font-semibold">Quantity</th>
                        <th class="px-6 py-3 font-semibold">Paid</th>
                        <th class="px-6 py-3 font-semibold text-right">Total</th>
                        <th class="px-6 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($collections as $c)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-3 font-medium text-ink">{{ $c->supplier->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-ink-variant">{{ $c->product->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-outline">{{ $c->collected_at->format('M j, Y g:i A') }}</td>
                            <td class="px-6 py-3 tabular text-ink-variant">{{ number_format($c->quantity, 2) }} {{ $c->unit }}</td>
                            <td class="px-6 py-3 tabular text-ink-variant">{{ $settings->currency }} {{ number_format($c->paid_amount, 0) }}</td>
                            <td class="px-6 py-3 text-right tabular font-semibold text-ink">{{ $settings->currency }} {{ number_format($c->total_amount, 0) }}</td>
                            <td class="px-6 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="{{ route('milk-collections.destroy', $c) }}" onsubmit="return confirm('Delete this entry?')">
                                        @csrf @method('DELETE')
                                        <button class="p-2 rounded-control text-outline hover:text-clay hover:bg-clay-container transition"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-outline">No collections recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($collections->hasPages())
            <div class="px-6 py-4 border-t border-outline-variant">{{ $collections->links() }}</div>
        @endif
    </div>
</div>
@endsection
