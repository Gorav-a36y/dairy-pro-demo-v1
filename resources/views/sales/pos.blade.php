@extends('layouts.app')
@section('title', 'New Sale')
@section('page-title', 'New Sale (POS)')

@section('content')
<form method="POST" action="{{ route('sales.store') }}"
      x-data="{
        sellableItems: {{ $sellableItems->toJson() }},
        customers: {{ $customers->toJson() }},
        selectedCustomerId: '',
        customerQuery: '',
        customerOpen: false,
        items: [],
        isPaid: true,
        paymentMethod: 'cash',
        productQuery: '',

        get selectedCustomer() { return this.customers.find(c => c.id === this.selectedCustomerId) || null },
        customerLabel() {
            if (this.customerOpen) return this.customerQuery;
            const c = this.selectedCustomer;
            return c ? c.label : 'Walk-in Customer';
        },
        filteredCustomers() {
            const q = this.customerQuery.toLowerCase();
            if (!q) return this.customers;
            return this.customers.filter(c => c.label.toLowerCase().includes(q));
        },
        selectCustomer(c) { this.selectedCustomerId = c.id; this.customerQuery = ''; this.customerOpen = false },
        clearCustomer() { this.selectedCustomerId = ''; this.customerQuery = '' },

        filteredProducts() {
            const q = this.productQuery.toLowerCase();
            if (!q) return this.sellableItems;
            return this.sellableItems.filter(p => p.label.toLowerCase().includes(q));
        },

        cartItem(key) { return this.items.find(i => i.key === key) },
        cartQty(key) { const i = this.cartItem(key); return i ? i.quantity : 0 },

        addToCart(product) {
            if (!product || product.stock <= 0) return;
            const existing = this.items.find(i => i.key === product.id);
            if (existing) {
                if (existing.quantity < product.stock) existing.quantity++;
            } else {
                this.items.push({ key: product.id, quantity: 1, discount: 0 });
                this.$nextTick(() => lucide.createIcons());
            }
        },

        removeItem(index) { this.items.splice(index, 1) },

        itemInfo(key) { return this.sellableItems.find(o => o.id === key) || null },
        lineGross(item) { const o = this.itemInfo(item.key); return o ? o.price * (parseFloat(item.quantity) || 0) : 0 },
        lineTotal(item) { return Math.max(this.lineGross(item) - (parseFloat(item.discount) || 0), 0) },
        get subtotal() { return this.items.reduce((s, i) => s + this.lineGross(i), 0) },
        get totalDiscount() { return this.items.reduce((s, i) => s + (parseFloat(i.discount) || 0), 0) },
        get grandTotal() { return Math.max(this.subtotal - this.totalDiscount, 0) },
        get hasStockIssue() {
            return this.items.some(i => { const o = this.itemInfo(i.key); return o && (parseFloat(i.quantity) || 0) > o.stock; });
        },
        get hasEmptyCart() { return this.items.length === 0 },
      }"
      class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    @csrf
    <input type="hidden" name="sale_date" value="{{ now()->format('Y-m-d') }}">

    {{-- LEFT: Product Catalog --}}
    <div class="lg:col-span-8 space-y-6">
        {{-- Search Bar --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-4 flex items-center gap-4">
            <div class="relative flex-1">
                <i data-lucide="search" class="h-5 w-5 text-outline absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input type="text" x-model="productQuery" placeholder="Search products by name..."
                       class="w-full rounded-control border border-outline-variant bg-white pl-12 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div class="hidden sm:block text-xs text-outline font-medium shrink-0">
                {{ now()->format('M j, Y — g:i A') }}
            </div>
        </div>

        {{-- Products Grid --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-serif text-lg font-semibold text-ink">Products</h3>
                <span class="text-xs text-outline font-medium bg-surface-container-low px-2.5 py-1 rounded-full"
                      x-text="filteredProducts().length + ' available'"></span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
                <template x-for="product in filteredProducts()" :key="product.id">
                    <div @click="addToCart(product)"
                         class="group relative rounded-control border border-outline-variant bg-white p-4 cursor-pointer transition-all hover:shadow-bento hover:border-forest-700/30 hover:-translate-y-0.5"
                         :class="product.stock <= 0 ? 'opacity-50 cursor-not-allowed hover:border-outline-variant hover:shadow-none hover:translate-y-0' : ''">

                        {{-- Badges --}}
                        <div class="absolute top-3 right-3 flex flex-col items-end gap-1 z-10">
                            <span x-show="cartQty(product.id) > 0" x-cloak
                                  class="bg-forest-700 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm"
                                  x-text="cartQty(product.id) + ' in cart'"></span>
                            <span x-show="product.stock <= 5 && product.stock > 0" x-cloak
                                  class="bg-clay-container text-clay text-[10px] font-bold px-2 py-0.5 rounded-full">Low Stock</span>
                            <span x-show="product.stock <= 0" x-cloak
                                  class="bg-gray-100 text-gray-500 text-[10px] font-bold px-2 py-0.5 rounded-full">Out of Stock</span>
                        </div>

                        {{-- Image Placeholder --}}
                        <div class="w-full aspect-[4/3] rounded-control bg-surface-container-low flex items-center justify-center mb-3 group-hover:bg-forest-50 transition-colors">
                            <span class="text-2xl font-serif font-bold text-outline/40 group-hover:text-forest-700/30 transition-colors"
                                  x-text="product.label.charAt(0).toUpperCase()"></span>
                        </div>

                        {{-- Info --}}
                        <h4 class="text-sm font-semibold text-ink mb-0.5 line-clamp-1" x-text="product.label"></h4>
                        <p class="text-xs text-outline mb-3 line-clamp-1" x-text="product.sublabel"></p>

                        <div class="flex items-center justify-between mt-auto pt-2 border-t border-outline-variant/40">
                            <span class="text-sm font-bold text-forest-700 tabular"
                                  x-text="'{{ $settings->currency }} ' + Number(product.price).toFixed(0)"></span>
                            <span class="text-[11px] text-outline font-medium"
                                  x-text="product.stock + ' ' + product.unit"></span>
                        </div>
                    </div>
                </template>

                <div x-show="filteredProducts().length === 0" x-cloak class="col-span-full py-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-surface-container-low flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="search-x" class="h-5 w-5 text-outline"></i>
                    </div>
                    <p class="text-sm text-outline font-medium">No products found</p>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT: Cart & Checkout --}}
    <div class="lg:col-span-4 space-y-6">
        {{-- Customer --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-5">Customer</h3>
            <div @click.outside="customerOpen = false" class="relative">
                <input type="hidden" name="customer_id" :value="selectedCustomerId">
                <div class="relative">
                    <i data-lucide="user" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="text" autocomplete="off"
                           :value="customerLabel()"
                           @focus="customerOpen = true; customerQuery = ''"
                           @input="customerOpen = true; customerQuery = $event.target.value"
                           class="w-full rounded-control border border-outline-variant bg-white pl-10 pr-9 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    <button type="button" x-show="selectedCustomerId" x-cloak @click="clearCustomer()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-clay">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
                <div x-show="customerOpen" x-cloak x-transition
                     class="absolute z-30 mt-1.5 w-full max-h-56 overflow-y-auto rounded-control border border-outline-variant bg-white shadow-bento py-1">
                    <button type="button" @click="clearCustomer(); customerOpen = false"
                            class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center gap-2 font-medium text-forest-700 border-b border-outline-variant/60">
                        <i data-lucide="user-round" class="h-3.5 w-3.5"></i> Walk-in Customer
                    </button>
                    <template x-for="c in filteredCustomers()" :key="c.id">
                        <button type="button" @click="selectCustomer(c)"
                                class="w-full text-left px-4 py-2.5 text-sm hover:bg-surface-container-low flex items-center justify-between gap-3">
                            <span class="text-ink font-medium" x-text="c.label"></span>
                            <span class="text-xs"
                                  :class="c.balance > 0 ? 'text-clay font-semibold' : 'text-outline'"
                                  x-text="c.balance > 0 ? ('Dues: {{ $settings->currency }} ' + c.balance) : (c.phone || '')"></span>
                        </button>
                    </template>
                    <div x-show="filteredCustomers().length === 0" class="px-4 py-3 text-sm text-outline">No results found.</div>
                </div>
            </div>

            <div x-show="selectedCustomer && selectedCustomer.balance > 0" x-cloak
                 class="mt-3 p-3 rounded-control bg-clay-container/40 border border-clay/20">
                <p class="text-xs text-clay font-semibold flex items-start gap-1.5">
                    <i data-lucide="alert-triangle" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>
                    This customer already owes {{ $settings->currency }} <span x-text="selectedCustomer?.balance"></span> on khata.
                </p>
            </div>
        </div>

        {{-- Cart Items --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-serif text-lg font-semibold text-ink">Cart</h3>
                <span x-show="items.length > 0" x-cloak
                      class="text-xs font-medium text-outline bg-surface-container-low px-2 py-1 rounded-full"
                      x-text="items.length + ' item' + (items.length > 1 ? 's' : '')"></span>
            </div>

            <div class="space-y-3 max-h-[360px] overflow-y-auto pr-1 -mr-1">
                <template x-for="(item, index) in items" :key="index">
                    <div class="p-3 rounded-control border border-outline-variant/60 bg-surface-container-low/30">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink truncate" x-text="itemInfo(item.key)?.label"></p>
                                <p class="text-[11px] text-outline" x-text="itemInfo(item.key)?.sublabel"></p>
                            </div>
                            <button type="button" @click="removeItem(index)"
                                    class="p-1.5 rounded text-outline hover:text-clay hover:bg-clay-container transition shrink-0">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>

                        <div class="flex items-end gap-2">
                            {{-- Form inputs --}}
                            <input type="hidden" :name="`items[${index}][item_key]`" :value="item.key">

                            {{-- Qty Stepper --}}
                            <div class="flex items-center gap-1">
                                <button type="button"
                                        @click="item.quantity = Math.max(1, (parseFloat(item.quantity) || 1) - 1)"
                                        class="w-8 h-8 rounded-control border border-outline-variant bg-white flex items-center justify-center text-ink hover:bg-surface-container-low transition">
                                    <i data-lucide="minus" class="h-3 w-3"></i>
                                </button>
                                <input type="number" step="0.01" min="0.01"
                                       :name="`items[${index}][quantity]`"
                                       x-model.number="item.quantity"
                                       :max="itemInfo(item.key)?.stock ?? null"
                                       class="w-14 text-center rounded-control border border-outline-variant bg-white py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 tabular"
                                       :class="(itemInfo(item.key) && item.quantity > itemInfo(item.key).stock) ? 'border-clay bg-clay-container' : ''">
                                <button type="button"
                                        @click="const stock = itemInfo(item.key)?.stock; if (stock && item.quantity < stock) item.quantity++"
                                        class="w-8 h-8 rounded-control border border-outline-variant bg-white flex items-center justify-center text-ink hover:bg-surface-container-low transition">
                                    <i data-lucide="plus" class="h-3 w-3"></i>
                                </button>
                            </div>

                            {{-- Discount --}}
                            <div class="flex-1">
                                <label class="block text-[10px] text-outline uppercase font-semibold mb-1">Discount</label>
                                <input type="number" step="0.01" min="0"
                                       :name="`items[${index}][discount]`"
                                       x-model.number="item.discount"
                                       class="w-full rounded-control border border-outline-variant bg-white px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 tabular">
                            </div>

                            {{-- Line Total --}}
                            <div class="text-right min-w-[60px]">
                                <label class="block text-[10px] text-outline uppercase font-semibold mb-1">Total</label>
                                <p class="text-sm font-bold text-forest-700 tabular" x-text="lineTotal(item).toFixed(0)"></p>
                            </div>
                        </div>

                        <p x-show="itemInfo(item.key) && item.quantity > itemInfo(item.key).stock" x-cloak
                           class="text-[10px] text-clay font-semibold mt-1.5 flex items-center gap-1">
                            <i data-lucide="alert-circle" class="h-3 w-3"></i>
                            Only <span x-text="itemInfo(item.key)?.stock + ' ' + itemInfo(item.key)?.unit"></span> available
                        </p>
                    </div>
                </template>

                <div x-show="items.length === 0" x-cloak class="py-10 text-center">
                    <div class="w-14 h-14 rounded-full bg-surface-container-low flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="shopping-cart" class="h-6 w-6 text-outline"></i>
                    </div>
                    <p class="text-sm text-outline font-medium">Your cart is empty</p>
                    <p class="text-xs text-outline mt-0.5">Click a product card to add it</p>
                </div>
            </div>
        </div>

        {{-- Payment --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
            <h3 class="font-serif text-lg font-semibold text-ink mb-5">Payment</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Payment Method</label>
                    <select name="payment_method" x-model="paymentMethod" :disabled="!isPaid"
                            class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700 disabled:bg-surface-container-low disabled:text-outline">
                        <option value="cash" selected>Cash</option>
                        <option value="easypaisa">Easypaisa</option>
                        <option value="jazzcash">JazzCash</option>
                    </select>
                </div>

                <div class="p-3 rounded-control border transition-colors"
                     :class="isPaid ? 'border-forest-700/20 bg-forest-50/50' : 'border-clay/20 bg-clay-container/30'">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="hidden" name="is_paid" :value="isPaid ? 1 : 0">
                        <input type="checkbox" x-model="isPaid" checked
                               class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                        <span class="text-sm font-medium" :class="isPaid ? 'text-forest-800' : 'text-clay'">Paid in full now</span>
                    </label>
                    <p x-show="!isPaid" x-cloak class="text-xs text-clay font-semibold mt-2 flex items-start gap-1.5">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>
                        This sale will go on <span x-text="selectedCustomer ? selectedCustomer.label + `'s` : 'the customer\'s'"></span> khata — nothing is collected today.
                    </p>
                    <p x-show="!isPaid && !selectedCustomerId" x-cloak class="text-xs text-clay mt-1">
                        Select a registered customer above — khata sales can't be Walk-in.
                    </p>
                </div>
            </div>
        </div>

        {{-- Order Summary --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6 sticky top-24">
            <h3 class="font-serif text-lg font-semibold text-ink mb-5">Order Summary</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-outline">Subtotal</span>
                    <span class="font-medium tabular text-ink" x-text="'{{ $settings->currency }} ' + subtotal.toFixed(0)"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-outline">Discount</span>
                    <span class="font-medium tabular text-clay" x-text="'- {{ $settings->currency }} ' + totalDiscount.toFixed(0)"></span>
                </div>
                <div class="flex justify-between pt-3 border-t border-outline-variant">
                    <span class="font-serif font-semibold text-ink">Grand Total</span>
                    <span class="font-serif text-xl font-semibold text-forest-700 tabular" x-text="'{{ $settings->currency }} ' + grandTotal.toFixed(0)"></span>
                </div>
            </div>

            <p x-show="hasStockIssue" x-cloak class="text-xs text-clay font-semibold mt-4 flex items-start gap-1.5">
                <i data-lucide="alert-triangle" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>
                One or more items exceed available stock — reduce quantities to continue.
            </p>

            <button type="submit"
                    :disabled="hasStockIssue || hasEmptyCart || (!isPaid && !selectedCustomerId)"
                    class="w-full mt-6 inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-3.5 shadow-bento-sm transition">
                <i data-lucide="check-circle" class="h-4 w-4"></i> Complete Sale
            </button>
        </div>
    </div>
</form>
@endsection