@extends('layouts.app')
@section('title', 'New Sale')
@section('page-title', 'New Sale (POS)')

@section('content')
<form method="POST" action="{{ route('sales.store') }}"
      x-ref="form"
      @keydown.window.enter.prevent="submitSale($event.shiftKey ? 'save_print' : 'save')"
      x-data="{
        sellableItems: {{ $sellableItems->toJson() }},
        customers: {{ $customers->toJson() }},
        paymentMethods: {{ $paymentMethods->map(fn($m) => ['code' => $m->code, 'name' => $m->name])->toJson() }},
        selectedCustomerId: '',
        customerQuery: '',
        customerOpen: false,
        items: [],
        paymentMethod: '{{ $paymentMethods->firstWhere('code', 'cash')?->code ?? optional($paymentMethods->first())->code }}',
        amountReceived: 0,
        amountTouched: false,
        productQuery: '',

        get isKhata() { return this.paymentMethod === 'khata' },
        get isPartialKhata() { return this.paymentMethod === 'partial_khata' },
        get requiresCustomer() { return this.isKhata || this.isPartialKhata },
        // Khata is always 0 and locked. Partial Khata and every other
        // method (cash, card, transfer, ...) are editable — for non-khata
        // methods the cashier can type more than the total and the
        // difference is handed back as change.
        get amountFieldEditable() { return !this.isKhata },
        get isOverpayable() { return !this.isKhata && !this.isPartialKhata },

        // Cash/other-method underpayment warnings — nudges the cashier
        // toward the correct payment method instead of silently letting
        // the submit button stay disabled with no explanation.
        get isZeroPaidCash() { return this.isOverpayable && (parseFloat(this.amountReceived) || 0) <= 0 },
        get isUnderpaidCash() { return this.isOverpayable && (parseFloat(this.amountReceived) || 0) > 0 && this.remaining > 0.009 },
        get paymentMethodLabel() {
            const m = this.paymentMethods.find(p => p.code === this.paymentMethod);
            return m ? m.name : this.paymentMethod;
        },

        init() {
            this.amountReceived = this.grandTotal;
            this.$watch('grandTotal', (value) => {
                // Only auto-follow the total while the cashier hasn't typed
                // their own figure yet — once touched, their entry (e.g. a
                // round cash amount tendered) is left alone.
                if (this.isKhata) { this.amountReceived = 0; return; }
                if (!this.amountTouched) this.amountReceived = value;
            });
            this.$watch('paymentMethod', () => {
                this.amountTouched = false;
                if (this.isKhata) this.amountReceived = 0;
                else this.amountReceived = this.grandTotal;
            });
        },

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
        get remaining() { return Math.max(this.grandTotal - (parseFloat(this.amountReceived) || 0), 0) },
        get changeDue() { return this.isOverpayable ? Math.max((parseFloat(this.amountReceived) || 0) - this.grandTotal, 0) : 0 },
        get hasStockIssue() {
            return this.items.some(i => { const o = this.itemInfo(i.key); return o && (parseFloat(i.quantity) || 0) > o.stock; });
        },
        get hasEmptyCart() { return this.items.length === 0 },
        get needsCustomerForKhata() { return this.requiresCustomer && !this.selectedCustomerId },
        get amountExceedsTotal() { return this.isPartialKhata && (parseFloat(this.amountReceived) || 0) > this.grandTotal + 0.009 },
        get partialKhataEqualsTotal() { return this.isPartialKhata && this.grandTotal > 0 && (parseFloat(this.amountReceived) || 0) >= this.grandTotal - 0.009 },
        get unpaidNotAllowed() { return !this.requiresCustomer && this.remaining > 0.009 },
        get canSubmit() { return !this.hasStockIssue && !this.hasEmptyCart && !this.needsCustomerForKhata && !this.amountExceedsTotal && !this.unpaidNotAllowed && !this.partialKhataEqualsTotal },

        submitSale(action) {
            if (!this.canSubmit) return;
            // Write directly to the field instead of a reactive binding — Alpine's
            // DOM updates are async, so setting a data property and submitting on
            // the same line can submit before the hidden input actually changes.
            this.$refs.actionField.value = action;
            this.$nextTick(() => this.$refs.form.requestSubmit());
        },
      }"
      class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    @csrf
    <input type="hidden" name="sale_date" value="{{ now()->format('Y-m-d') }}">
    <input type="hidden" name="action" x-ref="actionField" value="save">
    <input type="hidden" name="amount_received" :value="Number(amountReceived || 0).toFixed(2)">

    {{-- LEFT: Product Catalog --}}
    <div class="lg:col-span-8 space-y-6">
        {{-- Search Bar --}}
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-4 flex items-center gap-4">
            <div class="relative flex-1">
                <i data-lucide="search" class="h-5 w-5 text-outline absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input type="text" x-model="productQuery" placeholder="Search products by name..."
                       class="w-full rounded-control border border-outline-variant bg-white pl-12 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>
            <div class="hidden sm:flex items-center gap-1.5 text-xs text-outline font-medium shrink-0 tabular"
                 x-data="liveClock('{{ $settings->timezone() }}')" x-init="start()">
                <i data-lucide="clock" class="h-3.5 w-3.5"></i>
                <span x-text="display">{{ $settings->localize(now())->format('M j, Y — g:i A') }}</span>
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
                    <select name="payment_method" x-model="paymentMethod"
                            class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                    <p x-show="isKhata" x-cloak class="text-[11px] text-outline mt-1">Fully unpaid — the whole amount goes onto the customer's khata.</p>
                    <p x-show="isPartialKhata" x-cloak class="text-[11px] text-outline mt-1">Enter what's paid now — the rest goes onto the customer's khata.</p>
                    <p x-show="partialKhataEqualsTotal" x-cloak class="text-[11px] text-clay font-semibold mt-1">Paid amount can't equal the total under Partial Khata — select Cash (or another method) if the full amount is being paid.</p>
                    <p x-show="isOverpayable" x-cloak class="text-[11px] text-outline mt-1">Must cover at least the total — enter more than that to hand back change.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-ink-variant uppercase tracking-wide">Amount Received</label>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-outline">{{ $settings->currency }}</span>
                        <input type="number" step="0.01" min="0" :max="isPartialKhata ? grandTotal : null"
                               x-model.number="amountReceived"
                               @input="amountTouched = true"
                               :readonly="!amountFieldEditable"
                               :class="!amountFieldEditable ? 'bg-surface-container-low text-outline cursor-not-allowed' : ''"
                               class="w-full rounded-control border border-outline-variant bg-white pl-12 pr-4 py-3 text-sm tabular focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    </div>

                    {{-- NEW: cash/other-method underpayment warnings --}}
                    <p x-show="isZeroPaidCash" x-cloak class="text-xs text-clay font-semibold mt-1.5 flex items-center gap-1.5">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                        Amount received is 0 — choose <span class="underline">Khata</span> instead of <span x-text="paymentMethodLabel"></span>.
                    </p>
                    <p x-show="isUnderpaidCash" x-cloak class="text-xs text-clay font-semibold mt-1.5 flex items-center gap-1.5">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                        Amount received is less than the total — choose <span class="underline">Partial Khata</span> instead of <span x-text="paymentMethodLabel"></span>.
                    </p>
                </div>

                <div class="p-3 rounded-control border transition-colors"
                     :class="remaining <= 0.009 ? 'border-forest-700/20 bg-forest-50/50' : 'border-clay/20 bg-clay-container/30'">
                    <p class="text-sm font-semibold flex items-center gap-2"
                       :class="remaining <= 0.009 ? 'text-forest-800' : 'text-clay'">
                        <i x-show="remaining <= 0.009" x-cloak data-lucide="check-circle" class="h-4 w-4"></i>
                        <i x-show="remaining > 0.009" x-cloak data-lucide="alert-triangle" class="h-4 w-4"></i>
                        <span x-text="remaining <= 0.009 ? 'Paid in full' : (requiresCustomer ? ('Going to khata: {{ $settings->currency }} ' + remaining.toFixed(0)) : 'Underpaid')"></span>
                    </p>
                    <p x-show="remaining > 0.009 && selectedCustomerId && requiresCustomer" x-cloak class="text-xs text-clay mt-1.5">
                        Remainder will be added to <span x-text="selectedCustomer ? selectedCustomer.label + `'s` : 'the customer\'s'"></span> khata.
                    </p>
                    <p x-show="requiresCustomer && !selectedCustomerId" x-cloak class="text-xs text-clay font-semibold mt-1.5">
                        Select a registered customer above — Khata and Partial Khata sales can't be Walk-in.
                    </p>
                    <p x-show="changeDue > 0.009" x-cloak class="text-xs text-forest-800 font-semibold mt-1.5 flex items-center gap-1.5">
                        <i data-lucide="hand-coins" class="h-3.5 w-3.5"></i>
                        Change to return: <span x-text="'{{ $settings->currency }} ' + changeDue.toFixed(0)"></span>
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
                <div class="flex justify-between" x-show="remaining > 0.009" x-cloak>
                    <span class="text-clay font-medium">To Khata</span>
                    <span class="font-medium tabular text-clay" x-text="'{{ $settings->currency }} ' + remaining.toFixed(0)"></span>
                </div>
                <div class="flex justify-between" x-show="changeDue > 0.009" x-cloak>
                    <span class="text-forest-800 font-medium">Change Due</span>
                    <span class="font-medium tabular text-forest-800" x-text="'{{ $settings->currency }} ' + changeDue.toFixed(0)"></span>
                </div>
            </div>

            <p x-show="hasStockIssue" x-cloak class="text-xs text-clay font-semibold mt-4 flex items-start gap-1.5">
                <i data-lucide="alert-triangle" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>
                One or more items exceed available stock — reduce quantities to continue.
            </p>

            <div class="grid grid-cols-1 gap-2 mt-6">
                <button type="button"
                        @click="submitSale('save')"
                        :disabled="!canSubmit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 disabled:bg-outline-variant disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-3.5 shadow-bento-sm transition">
                    <i data-lucide="check-circle" class="h-4 w-4"></i>
                    Save Sale
                    <span class="text-[10px] font-normal opacity-70">(Enter)</span>
                </button>
                <button type="button"
                        @click="submitSale('save_print')"
                        :disabled="!canSubmit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-control bg-white border border-forest-700 hover:bg-forest-50 disabled:bg-surface-container-low disabled:border-outline-variant disabled:text-outline disabled:cursor-not-allowed text-forest-700 text-sm font-semibold px-5 py-3.5 transition">
                    <i data-lucide="printer" class="h-4 w-4"></i>
                    Save &amp; Print
                    <span class="text-[10px] font-normal opacity-70">(Shift+Enter)</span>
                </button>
            </div>
        </div>
    </div>
</form>
@endsection