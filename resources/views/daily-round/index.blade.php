@extends('layouts.app')
@section('title', 'Daily Round')
@section('page-title', 'Daily Round')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-serif text-lg font-semibold text-ink">Today's Deliveries — {{ now()->format('F j, Y') }}</h2>
            <p class="text-sm text-outline">Adjust any quantity if it's different today, or click Skip for anyone who wasn't around. You'll get a confirmation listing exactly who's about to be logged before anything is saved.</p>
        </div>
    </div>

    @if($customers->isEmpty())
        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-10 text-center">
            <span class="h-12 w-12 rounded-control bg-forest-50 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="milk" class="h-6 w-6 text-forest-700"></i>
            </span>
            <p class="text-ink-variant text-sm mb-1">No daily customers set up yet.</p>
            <p class="text-outline text-xs mb-4">Go to a customer's edit page and tick "Daily Customer" to add them here.</p>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-forest-700 hover:text-forest-800">
                <i data-lucide="users" class="h-4 w-4"></i> Go to Customers
            </a>
        </div>
    @else
        <form method="POST" action="{{ route('daily-round.store') }}"
              x-data="{
                rows: [
                    @foreach($customers as $row)
                    {
                        id: {{ $row['customer']->id }},
                        name: @js($row['customer']->name),
                        qty: '{{ $row['delivered_today'] ? '' : ($row['customer']->daily_quantity ?? '') }}',
                        prevQty: '{{ $row['delivered_today'] ? '' : ($row['customer']->daily_quantity ?? '') }}',
                        skip: false,
                        delivered: {{ $row['delivered_today'] ? 'true' : 'false' }},
                        hasItem: {{ $row['item'] ? 'true' : 'false' }},
                    },
                    @endforeach
                ],
                toggleSkip(row) {
                    if (row.delivered || !row.hasItem) return;
                    row.skip = !row.skip;
                    if (row.skip) { row.prevQty = row.qty; row.qty = ''; }
                    else { row.qty = row.prevQty || ''; }
                },
                get willLog() {
                    return this.rows.filter(r => !r.delivered && r.hasItem && !r.skip && parseFloat(r.qty) > 0);
                },
                confirmSubmit(e) {
                    const list = this.willLog;
                    if (list.length === 0) {
                        alert('Nothing to log — enter a quantity for at least one customer, or make sure they are not marked Skipped.');
                        e.preventDefault();
                        return;
                    }
                    const names = list.map(r => r.name).join('\\n — ');
                    const ok = confirm(`Log today's delivery for ${list.length} customer(s)?\\n\\n — ${names}`);
                    if (!ok) e.preventDefault();
                },
              }"
              @submit="confirmSubmit($event)">
            @csrf
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                <th class="px-6 py-3 font-semibold">Customer</th>
                                <th class="px-6 py-3 font-semibold">Usual Item</th>
                                <th class="px-6 py-3 font-semibold">Quantity Today</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            @foreach($customers as $row)
                                @php $c = $row['customer']; $item = $row['item']; $i = $loop->index; @endphp
                                <tr class="{{ $row['delivered_today'] ? 'bg-forest-50/40' : '' }}"
                                    :class="rows[{{ $i }}].skip ? 'bg-clay-container/30' : ''">
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-ink">{{ $c->name }}</p>
                                        <p class="text-xs text-outline">{{ $c->phone ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-ink-variant">
                                        @if($item)
                                            {{ $item->name }} <span class="text-outline">({{ $item->unit }})</span>
                                        @else
                                            <span class="text-clay text-xs font-semibold">No item set — edit customer</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($row['delivered_today'] || !$item)
                                            <input type="number" step="0.01" min="0" value="" disabled
                                                   class="w-24 rounded-control border border-outline-variant px-3 py-2 text-sm bg-surface-container-low text-outline">
                                        @else
                                            <div class="flex items-center gap-2">
                                                <input type="number" step="0.01" min="0" name="deliveries[{{ $c->id }}]"
                                                       x-model="rows[{{ $i }}].qty" :disabled="rows[{{ $i }}].skip"
                                                       class="w-24 rounded-control border border-outline-variant px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline">
                                                <button type="button" @click="toggleSkip(rows[{{ $i }}])"
                                                        :class="rows[{{ $i }}].skip ? 'bg-clay-container text-clay border-clay/30' : 'bg-surface-container-low text-outline border-outline-variant hover:text-ink'"
                                                        class="text-[11px] font-semibold px-2.5 py-2 rounded-control border transition shrink-0">
                                                    <span x-text="rows[{{ $i }}].skip ? 'Skipped' : 'Skip'"></span>
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($row['delivered_today'])
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800 border border-forest-300/40">
                                                <i data-lucide="check-circle-2" class="h-3 w-3"></i> Delivered Today
                                            </span>
                                        @elseif($item)
                                            <span x-show="!rows[{{ $i }}].skip" class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-surface-container-low text-outline">
                                                <i data-lucide="clock" class="h-3 w-3"></i> Will Log
                                            </span>
                                            <span x-show="rows[{{ $i }}].skip" x-cloak class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-clay-container text-clay">
                                                <i data-lucide="x-circle" class="h-3 w-3"></i> Skipped Today
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-surface-container-low text-outline">
                                                <i data-lucide="minus" class="h-3 w-3"></i> —
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-4">
                <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition">
                    <i data-lucide="truck" class="h-4 w-4"></i> Log Today's Deliveries
                </button>
                <p class="text-sm text-ink-variant">
                    <span class="font-semibold text-ink tabular" x-text="willLog.length"></span> customer(s) will be logged
                </p>
            </div>
            <p class="text-xs text-outline mt-2">Each logged delivery goes straight onto that customer's khata — nothing is collected today. You'll be asked to confirm the exact list before it saves.</p>
        </form>
    @endif
</div>
@endsection