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
            <p class="text-sm text-outline">Raw materials bought from farmers and suppliers.</p>
        </div>
        <button type="button" @click="showForm = !showForm"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 shadow-bento-sm transition">
            <i data-lucide="plus" class="h-4 w-4"></i>
            <span x-text="showForm ? 'Close Form' : 'Record Collection'"></span>
        </button>
    </div>

    <div x-show="showForm" x-cloak x-transition
         x-data="{
            quantity: '', purchasePrice: '', fat: '', snf: '',
            isLiquid: false,
            paymentMethod: '{{ $paymentMethods->firstWhere('code', 'cash')?->code ?? optional($paymentMethods->first())->code }}',
            paidAmount: 0,
            shift: (() => {
                const h = new Date().getHours();
                if (h >= 5 && h < 12) return 'morning';
                if (h >= 12 && h < 17) return 'afternoon';
                if (h >= 17 && h < 21) return 'evening';
                return 'night';
            })(),
            get total() { return ((parseFloat(this.quantity)||0) * (parseFloat(this.purchasePrice)||0)).toFixed(0) },
            get totalNumber() { return (parseFloat(this.quantity)||0) * (parseFloat(this.purchasePrice)||0) },
            get isKhata() { return this.paymentMethod === 'khata' },
            get isPartialKhata() { return this.paymentMethod === 'partial_khata' },
            // Khata: locked at 0. Everything else (Partial Khata, Cash, Other): editable.
            get paidAmountEditable() { return !this.isKhata },

            get returnAmount() {
                if (this.isKhata || this.isPartialKhata) return 0;
                return Math.max(0, (parseFloat(this.paidAmount) || 0) - this.totalNumber);
            },

            get cashUnderpaid() {
                return !this.isKhata && !this.isPartialKhata
                    && this.totalNumber > 0
                    && (parseFloat(this.paidAmount) || 0) + 0.01 < this.totalNumber;
            },

            get partialKhataEqualsTotal() { return this.isPartialKhata && this.totalNumber > 0 && (parseFloat(this.paidAmount) || 0) >= this.totalNumber },
            get totalSolids() {
                if (this.fat === '' || this.snf === '') return '';
                return ((parseFloat(this.fat)||0) + (parseFloat(this.snf)||0)).toFixed(2)
            },
            init() {
                this.paidAmount = this.totalNumber;

                // Auto-fill Paid Amount to the live total for Khata (always 0) and
                // Cash/Other (defaults to total) — Partial Khata is left alone since
                // the user is meant to type a smaller number themselves.
                this.$watch('totalNumber', (value) => {
                    if (this.isKhata) this.paidAmount = 0;
                    else if (!this.isPartialKhata) this.paidAmount = value;
                });

                this.$watch('paymentMethod', () => {
                    if (this.isKhata) this.paidAmount = 0;
                    else if (!this.isPartialKhata) this.paidAmount = this.totalNumber;
                });
            }
         }"
         class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <form method="POST" action="{{ route('milk-collections.store') }}"
            @keydown.enter="if ($event.shiftKey) { $event.preventDefault(); $refs.printFlag.value = '1'; $el.requestSubmit(); } else if ($event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); $refs.printFlag.value = '0'; $el.requestSubmit(); }"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @csrf
            <x-searchable-select name="supplier_id" :options="$suppliers" label="Supplier" placeholder="Search supplier..." required />

            <x-searchable-select
                name="raw_material_id"
                :options="$rawMaterials"
                label="Raw Material"
                placeholder="Search raw material..."
                required
                onSelect="isLiquid = ['liter','litre','l'].includes((item.sublabel || '').toLowerCase().trim())"
            />

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Quantity</label>
                <input type="number" step="0.01" min="0.01" name="quantity" x-model="quantity" required
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">Unit is taken automatically from the selected raw material.</p>
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
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Shift</label>
                <select name="shift" x-model="shift"
                        class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    <option value="morning">Morning</option>
                    <option value="afternoon">Afternoon</option>
                    <option value="evening">Evening</option>
                    <option value="night">Night</option>
                </select>
                <p class="text-[11px] text-outline mt-1">Defaults to current time of day — change if backdating an entry.</p>
            </div>

            <div x-show="isLiquid" x-cloak>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Fat (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="fat" x-model="fat"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>

            <div x-show="isLiquid" x-cloak>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">SNF (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="snf" x-model="snf"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>

            <div x-show="isLiquid" x-cloak>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Total Solids (auto-calculated)</label>
                <input type="text" :value="totalSolids" disabled
                       class="w-full rounded-control border border-outline-variant bg-surface-container-low px-4 py-3 text-sm font-semibold text-ink tabular cursor-not-allowed opacity-70">
                <p class="text-[11px] text-outline mt-1">Total Solids = Fat + SNF</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">
                    <span x-text="isKhata ? 'Paid Amount' : 'Cash Given'"></span>
                </label>
                <input type="number" step="0.01" min="0" name="paid_amount"
                    x-model.number="paidAmount" :readonly="!paidAmountEditable"
                    :class="!paidAmountEditable ? 'bg-surface-container-low text-outline cursor-not-allowed' : ''"
                    class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">

                <p x-show="isKhata" x-cloak class="text-[11px] text-outline mt-1">Fully unpaid — the whole amount goes onto the supplier's khata.</p>
                <p x-show="isPartialKhata" x-cloak class="text-[11px] text-outline mt-1">Enter what's paid now — the rest goes onto the supplier's khata.</p>
                <p x-show="!isKhata && !isPartialKhata" x-cloak class="text-[11px] text-outline mt-1">Auto-filled to the total — edit if you handed over more cash.</p>

                <p x-show="partialKhataEqualsTotal" x-cloak class="text-[11px] text-clay font-semibold mt-1">Paid amount can't equal the total under Partial Khata — select Cash (or another method) if the full amount is being paid.</p>
                <p x-show="cashUnderpaid" x-cloak class="text-[11px] text-clay font-semibold mt-1">Cash given cannot be less than the total.</p>
            </div>

            <div x-show="returnAmount > 0" x-cloak class="sm:col-span-2 lg:col-span-3">
                <div class="flex items-center gap-3 rounded-control border-2 border-red-300 bg-red-50 px-5 py-4">
                    <i data-lucide="alert-circle" class="h-6 w-6 text-red-600 shrink-0"></i>
                    <p class="text-base font-bold text-red-700">
                        Return to give: {{ $settings->currency ?? 'Rs.' }} <span x-text="returnAmount.toFixed(0)"></span>
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Payment Method</label>
                <select name="payment_method" x-model="paymentMethod" class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->code }}">{{ $method->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Note</label>
                <input type="text" name="notes" placeholder="e.g. quality checked, morning delivery"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
            </div>

            <input type="hidden" name="print" x-ref="printFlag" value="0">

            <div class="sm:col-span-2 lg:col-span-3 flex items-center gap-3">
                <button type="submit" @click="$refs.printFlag.value = '0'" :disabled="partialKhataEqualsTotal || cashUnderpaid"
                        class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition disabled:bg-outline-variant disabled:text-outline disabled:cursor-not-allowed">
                    <i data-lucide="save" class="h-4 w-4"></i> Save Collection
                    <span class="text-xs opacity-70 font-normal">(Enter)</span>
                </button>
                <button type="submit" @click="$refs.printFlag.value = '1'" :disabled="partialKhataEqualsTotal || cashUnderpaid"
                        class="inline-flex items-center gap-2 rounded-control border border-forest-700 text-forest-700 hover:bg-forest-50 text-sm font-semibold px-5 py-3 transition disabled:border-outline-variant disabled:text-outline disabled:cursor-not-allowed">
                    <i data-lucide="printer" class="h-4 w-4"></i> Save &amp; Print
                    <span class="text-xs opacity-70 font-normal">(Shift+Enter)</span>
                </button>
            </div>
        </form>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full sm:max-w-lg">
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-control border border-outline-variant px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            <button type="submit" class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition">
                <i data-lucide="filter" class="h-4 w-4"></i> Filter
            </button>
        </form>
        <a href="{{ route('milk-collections.exportCsv', ['from' => request('from'), 'to' => request('to')]) }}"
           class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition shrink-0">
            <i data-lucide="download" class="h-4 w-4"></i> Download CSV
        </a>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Supplier</th>
                        <th class="px-6 py-3 font-semibold">Raw Material</th>
                        <th class="px-6 py-3 font-semibold">Date &amp; Time</th>
                        <th class="px-6 py-3 font-semibold">Shift</th>
                        <th class="px-6 py-3 font-semibold">Quantity</th>
                        <th class="px-6 py-3 font-semibold">Price/Unit</th>
                        <th class="px-6 py-3 font-semibold">Payment</th>
                        <th class="px-6 py-3 font-semibold">Paid</th>
                        <th class="px-6 py-3 font-semibold">Cash Given</th>
                        <th class="px-6 py-3 font-semibold">Return</th>
                        <th class="px-6 py-3 font-semibold text-right">Total</th>
                        <th class="px-6 py-3 font-semibold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($collections as $c)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-3 font-medium text-ink">{{ $c->supplier->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-ink-variant">{{ $c->rawMaterial->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-outline">{{ $settings->localize($c->collected_at)->format('M j, Y g:i A') }}</td>
                            <td class="px-6 py-3 text-ink-variant capitalize">{{ $c->shift }}</td>
                            <td class="px-6 py-3 tabular text-ink-variant">{{ number_format($c->quantity, 2) }} {{ $c->unit }}</td>
                            <td class="px-6 py-3 tabular text-ink-variant">{{ $settings->currency }} {{ number_format($c->purchase_price, 2) }}</td>
                            <td class="px-6 py-3 text-ink-variant capitalize">{{ str_replace('_', ' ', $c->payment_method) }}</td>
                            <td class="px-6 py-3 tabular text-ink-variant">{{ $settings->currency }} {{ number_format($c->paid_amount, 0) }}</td>
                            <td class="px-6 py-3 tabular text-ink-variant">
                                {{ $c->amount_given !== null ? $settings->currency . ' ' . number_format($c->amount_given, 0) : '—' }}
                            </td>
                            <td class="px-6 py-3 tabular text-ink-variant">
                                {{ $c->return_amount > 0 ? $settings->currency . ' ' . number_format($c->return_amount, 0) : '—' }}
                            </td>
                            <td class="px-6 py-3 text-right tabular font-semibold text-ink">{{ $settings->currency }} {{ number_format($c->total_amount, 0) }}</td>
                            <td class="px-6 py-3 text-center">
                                <a href="{{ route('milk-collections.receipt', $c) }}"
                                class="inline-flex items-center justify-center h-8 w-8 rounded-control hover:bg-surface-container-low text-outline hover:text-forest-700 transition"
                                title="View Receipt">
                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-6 py-10 text-center text-outline">No collections recorded yet.</td></tr>
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