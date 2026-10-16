@extends('layouts.app')
@section('title', 'Waste Management')
@section('page-title', 'Waste Management')

@section('content')
<div class="space-y-6" x-data="{ showForm: {{ $errors->any() ? 'true' : 'false' }} }">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="trash-2" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $todayCount }}</p>
            <p class="text-xs text-outline mt-1">Waste Entries Today</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="calendar-days" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $monthCount }}</p>
            <p class="text-xs text-outline mt-1">Entries This Month</p>
        </div>
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
            <span class="h-10 w-10 rounded-control bg-forest-50 flex items-center justify-center mb-3"><i data-lucide="package-minus" class="h-5 w-5 text-forest-700"></i></span>
            <p class="text-2xl font-serif font-semibold text-ink tabular">{{ $monthItems }}</p>
            <p class="text-xs text-outline mt-1">Distinct Items Wasted (Month)</p>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <div>
            <h3 class="font-serif text-lg font-semibold text-ink">Waste History</h3>
            <p class="text-sm text-outline">Raw materials and products marked as wasted or spoiled.</p>
        </div>
        <button type="button" @click="showForm = !showForm"
                class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 shadow-bento-sm transition">
            <i data-lucide="plus" class="h-4 w-4"></i>
            <span x-text="showForm ? 'Close Form' : 'Add Waste'"></span>
        </button>
    </div>

    <div x-show="showForm" x-cloak x-transition
         x-data="{
            quantity: @js(old('quantity', '')),
            stock: null,
            unit: '',
            typeLabel: '',
            get overStock() { return this.stock !== null && parseFloat(this.quantity || 0) > this.stock }
         }"
         class="bg-white rounded-bento border border-outline-variant shadow-bento p-8">
        <form method="POST" action="{{ route('waste.store') }}"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @csrf

            <x-searchable-select
                name="item_key"
                :options="$items"
                label="Raw Material / Product"
                placeholder="Search raw material or product..."
                required
                onSelect="quantity=''; stock=item.stock; unit=item.unit; typeLabel=item.type"
            />

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Waste Quantity</label>
                <input type="number" step="0.01" min="0.01" name="quantity" x-model="quantity" required
                       :class="overStock ? 'border-clay focus:ring-clay focus:border-clay' : 'border-outline-variant focus:ring-forest-700 focus:border-forest-700'"
                       class="w-full rounded-control border px-4 py-3 text-sm focus:outline-none focus:ring-2">
                <p class="text-[11px] text-outline mt-1" x-show="stock !== null" x-cloak>
                    <span x-text="typeLabel"></span> — current stock: <span x-text="stock" class="font-semibold"></span> <span x-text="unit"></span>
                </p>
                <p x-show="overStock" x-cloak class="text-[11px] text-clay font-semibold mt-1">Cannot exceed current stock.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Date</label>
                <input type="date" name="wasted_at" required
                       value="{{ old('wasted_at', now()->toDateString()) }}"
                       max="{{ now()->toDateString() }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">
                <p class="text-[11px] text-outline mt-1">Today by default. A future date is not allowed.</p>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Reason / Note (optional)</label>
                <textarea name="note" rows="2" placeholder="e.g. spoiled, expired, spilled during production"
                    class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700">{{ old('note') }}</textarea>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <button type="submit" :disabled="overStock"
                        class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition disabled:bg-outline-variant disabled:text-outline disabled:cursor-not-allowed">
                    <i data-lucide="save" class="h-4 w-4"></i> Save Waste Entry
                </button>
            </div>
        </form>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 w-full sm:max-w-lg">
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-control border border-outline-variant bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-control border border-outline-variant bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            <button type="submit" class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition">
                <i data-lucide="filter" class="h-4 w-4"></i> Filter
            </button>
        </form>
        <a href="{{ route('waste.exportCsv', ['from' => request('from'), 'to' => request('to')]) }}"
           class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition shrink-0">
            <i data-lucide="download" class="h-4 w-4"></i> Download CSV
        </a>
    </div>

    <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                        <th class="px-6 py-3 font-semibold">Date</th>
                        <th class="px-6 py-3 font-semibold">Type</th>
                        <th class="px-6 py-3 font-semibold">Item</th>
                        <th class="px-6 py-3 font-semibold">Quantity</th>
                        <th class="px-6 py-3 font-semibold">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($wastes as $waste)
                        <tr class="hover:bg-surface-container-low transition">
                            <td class="px-6 py-3 text-outline">{{ $waste->wasted_at->format('M j, Y') }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $waste->isRawMaterial() ? 'bg-forest-50 text-forest-800' : 'bg-secondary-container text-on-secondary-container' }}">
                                    {{ $waste->isRawMaterial() ? 'Raw Material' : 'Product' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 font-medium text-ink">{{ $waste->item_name }}</td>
                            <td class="px-6 py-3 tabular text-ink-variant">{{ number_format($waste->quantity, 2) }} {{ $waste->unit }}</td>
                            <td class="px-6 py-3 text-ink-variant">{{ $waste->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-outline">No waste entries recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($wastes->hasPages())
            <div class="px-6 py-4 border-t border-outline-variant">{{ $wastes->links() }}</div>
        @endif
    </div>
</div>
@endsection
