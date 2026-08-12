@extends('layouts.app')
@section('title', 'New Sale')
@section('page-title', 'New Sale (POS)')

@section('content')
<form method="POST" action="{{ route('sales.store') }}"
      x-data="{
        customers: {{ $customers->toJson() }},
        products: {{ $products->toJson() }},
        items: [{ product_id: '', quantity: 1 }],
        selectedCustomerId: '',
        discount: 0,
        isPaid: true,
        productInfo(id) { return this.products.find(p => p.id == id) },
        lineTotal(item) { const p = this.productInfo(item.product_id); return p ? (p.price * item.quantity) : 0 },
        get subtotal() { return this.items.reduce((s, i) => s + this.lineTotal(i), 0) },
        get grandTotal() { return Math.max(this.subtotal - (parseFloat(this.discount)||0), 0) },
        get selectedCustomer() { return this.customers.find(c => c.id == this.selectedCustomerId) },
      }"
      class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    @csrf

    <div class="xl:col-span-2 space-y-6">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-5">Customer &amp; Payment</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div x-data="searchableSelect(customers)" @click.outside="open=false"  class="relative">
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Customer</label>
                    <input type="hidden" name="customer_id" :value="selectedId" x-init="$watch('selectedId', v => selectedCustomerId = v)">
                    <div class="relative">
                        <i data-lucide="user" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input type="text" autocomplete="off" placeholder="Walk-in Customer (search to select a registered one)"
                               :value="open ? query : selectedLabel" @focus="open=true; query=''" @input="open=true; query=$event.target.value"
                               class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-9 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                        <button type="button" x-show="selectedId" x-cloak @click="clear(); selectedCustomerId=''" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-clay"><i data-lucide="x" class="h-3.5 w-3.5"></i></button>
                    </div>
                    <div x-show="open" x-cloak x-transition class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                        <template x-for="item in filtered" :key="item.id">
                            <button type="button" @click="select(item)" class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3">
                                <span class="text-ink font-medium" x-text="item.label"></span>
                                <span class="text-xs" :class="item.balance > 0 ? 'text-clay font-semibold' : 'text-outline'" x-text="item.balance > 0 ? ('Dues: {{ $settings->currency }} ' + item.balance) : item.sublabel"></span>
                            </button>
                        </template>
                        <div x-show="filtered.length === 0" class="px-4 py-3 text-sm text-outline">No results found.</div>
                    </div>
                    <p class="text-[11px] mt-1.5" x-show="selectedCustomer && selectedCustomer.balance > 0" x-cloak>
                        <span class="text-clay font-semibold">This customer owes {{ $settings->currency }} <span x-text="selectedCustomer?.balance"></span> on khata.</span>
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Sale Date</label>
                    <input type="date" name="sale_date" value="{{ now()->format('Y-m-d') }}" required
                           class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Payment Method</label>
                    <select name="payment_method" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                        <option value="cash" selected>Cash</option>
                        <option value="easypaisa">Easypaisa</option>
                        <option value="jazzcash">JazzCash</option>
                    </select>
                </div>

                <div class="flex items-center gap-2.5 pt-6">
                    <input type="hidden" name="is_paid" :value="isPaid ? 1 : 0">
                    <input type="checkbox" x-model="isPaid" id="isPaid" class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                    <label for="isPaid" class="text-sm text-ink-variant">Paid in full now <span class="text-outline">(uncheck to add to customer's khata)</span></label>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-5">Items</h3>
            <div class="space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 pb-3 border-b border-outline-variant/60 last:border-0">
                        <div x-data="searchableSelect(products)" @click.outside="open=false"  class="relative flex-1">
                            <input type="hidden" :name="`items[${index}][product_id]`" :value="selectedId" x-init="$watch('selectedId', v => items[index].product_id = v)">
                            <div class="relative">
                                <i data-lucide="search" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                <input type="text" autocomplete="off" placeholder="Search product..."
                                       :value="open ? query : selectedLabel" @focus="open=true; query=''" @input="open=true; query=$event.target.value"
                                       class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                            </div>
                            <div x-show="open" x-cloak x-transition class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                                <template x-for="p in filtered" :key="p.id">
                                    <button type="button" @click="select(p)" class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3">
                                        <span class="text-ink font-medium" x-text="p.label"></span>
                                        <span class="text-xs text-outline" x-text="`${p.unit} · {{ $settings->currency }} ${p.price} · ${p.stock} in stock`"></span>
                                    </button>
                                </template>
                                <div x-show="filtered.length === 0" class="px-4 py-3 text-sm text-outline">No results found.</div>
                            </div>
                        </div>

                        <div class="w-full sm:w-24 shrink-0">
                            <input type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model.number="item.quantity" placeholder="Qty"
                                   class="w-full rounded-control border border-outline-variant px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
                        </div>
                        <div class="w-full sm:w-20 shrink-0 text-sm text-outline">
                            <span x-text="productInfo(item.product_id)?.unit || 'unit'"></span>
                        </div>
                        <div class="w-full sm:w-28 shrink-0 text-right text-sm font-semibold tabular text-ink">
                            {{ $settings->currency }} <span x-text="lineTotal(item).toFixed(0)"></span>
                        </div>
                        <button type="button" @click="items.splice(index, 1)" class="p-2 rounded-control text-outline hover:text-clay hover:bg-clay-container transition shrink-0">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                </template>
            </div>

            <button type="button" @click="items.push({product_id: '', quantity: 1}); $nextTick(() => lucide.createIcons())"
                    class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-forest-700 hover:text-forest-800">
                <i data-lucide="plus-circle" class="h-4 w-4"></i> Add Item
            </button>
        </div>
    </div>

    {{-- Summary --}}
    <div class="xl:col-span-1">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6 sticky top-24">
            <h3 class="font-serif text-lg font-semibold text-ink mb-5">Order Summary</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-outline">Subtotal</span><span class="font-medium tabular text-ink" x-text="'{{ $settings->currency }} ' + subtotal.toFixed(0)"></span></div>
                <div class="flex justify-between items-center">
                    <span class="text-outline">Discount</span>
                    <input type="number" step="0.01" min="0" name="discount" x-model.number="discount" placeholder="0"
                           class="w-28 rounded-control border border-outline-variant px-3 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-forest-700">
                </div>
                <div class="flex justify-between pt-3 border-t border-outline-variant">
                    <span class="font-serif font-semibold text-ink">Grand Total</span>
                    <span class="font-serif text-xl font-semibold text-forest-700 tabular" x-text="'{{ $settings->currency }} ' + grandTotal.toFixed(0)"></span>
                </div>
            </div>

            <button type="submit" class="w-full mt-6 inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3.5 shadow-bento-sm transition">
                <i data-lucide="check-circle" class="h-4 w-4"></i> Complete Sale
            </button>
        </div>
    </div>
</form>
@endsection
