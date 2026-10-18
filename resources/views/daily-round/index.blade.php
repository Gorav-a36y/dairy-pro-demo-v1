@extends('layouts.app')

@section('title', 'Daily Round')
@section('page-title', 'Daily Round')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-serif text-lg font-semibold text-ink">
                Today's Deliveries — {{ $settings->now()->format('F j, Y') }}
            </h2>

            <p class="text-sm text-outline">
                Uncheck anyone who wasn't around today, adjust quantities if needed.
                You'll get a confirmation listing exactly who's about to be logged
                before anything is saved.
            </p>
        </div>

        <a href="{{ route('daily-round.history') }}"
           class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition shrink-0">
            <i data-lucide="file-text" class="h-4 w-4"></i>
            Customer History
        </a>
    </div>

    @if($customers->isEmpty())

        <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-10 text-center">
            <span class="h-12 w-12 rounded-control bg-forest-50 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="milk" class="h-6 w-6 text-forest-700"></i>
            </span>

            <p class="text-ink-variant text-sm mb-1">
                No daily customers set up yet.
            </p>

            <p class="text-outline text-xs mb-4">
                Go to a customer's edit page and tick "Daily Customer" to add them here.
            </p>

            <a href="{{ route('customers.index') }}"
               class="inline-flex items-center gap-2 text-sm font-semibold text-forest-700 hover:text-forest-800">
                <i data-lucide="users" class="h-4 w-4"></i>
                Go to Customers
            </a>
        </div>

    @else

        <form method="POST"
              action="{{ route('daily-round.store') }}"
              x-data="{
                  search: '',

                  rows: [
                      @foreach($customers as $row)
                          {
                              id: {{ $row['customer']->id }},
                              name: @js($row['customer']->name),

                              hasItems: {{ count($row['items']) > 0 ? 'true' : 'false' }},

                              checked: {{
                                  (!$row['delivered_today'] && !$row['skipped'] && count($row['items']) > 0)
                                      ? 'true'
                                      : 'false'
                              }},

                              delivered: {{ $row['delivered_today'] ? 'true' : 'false' }},

                              items: [
                                  @foreach($row['items'] as $item)
                                      {
                                          key: @js($item['key']),
                                          name: @js($item['name']),
                                          unit: @js($item['unit']),

                                          qty: '{{ $item['delivered'] ? '' : $item['default_qty'] }}',

                                          delivered: {{ $item['delivered'] ? 'true' : 'false' }},
                                          stock: {{ $item['stock'] }},
                                          blocked: @js($item['blocked_reason']),
                                      },
                                  @endforeach
                              ],

                              status: @js($row['status']),
                              skipReason: @js($row['skip_reason'] ?? ''),
                          },
                      @endforeach
                  ],

                  get selectable() {
                      return this.rows.filter(r => !r.delivered && r.hasItems);
                  },

                  selectAll() {
                      this.selectable.forEach(r => r.checked = true);
                  },

                  selectNone() {
                      this.selectable.forEach(r => r.checked = false);
                  },

                  // Why an item cannot be delivered right now (empty text = it can).
                  itemProblem(i) {
                      if (i.delivered) return '';
                      if (i.blocked) return i.blocked;
                      const q = parseFloat(i.qty) || 0;
                      if (q > i.stock) return 'Only ' + i.stock + ' ' + i.unit + ' in stock';
                      return '';
                  },

                  // Quantity that will really be delivered (blocked items do not count).
                  rowQty(r) {
                      return r.items
                          .filter(i => !i.delivered && !this.itemProblem(i))
                          .reduce((sum, i) => sum + (parseFloat(i.qty) || 0), 0);
                  },

                  get willLog() {
                      return this.rows.filter(
                          r => !r.delivered && r.hasItems && r.checked && this.rowQty(r) > 0
                      );
                  },

                  get willSkip() {
                      return this.rows.filter(
                          r => !r.delivered && r.hasItems && !r.checked
                      );
                  },

                  get willBlock() {
                      return this.rows.filter(
                          r => !r.delivered && r.hasItems && r.checked &&
                               r.items.some(i => !i.delivered && parseFloat(i.qty) > 0 && this.itemProblem(i))
                      );
                  },

                  rowItemsLabel(r) {
                      return r.items
                          .filter(i => !i.delivered && !this.itemProblem(i) && parseFloat(i.qty) > 0)
                          .map(i => `${i.name} ${i.qty} ${i.unit}`)
                          .join(', ');
                  },

                  rowBlockedLabel(r) {
                      return r.items
                          .filter(i => !i.delivered && parseFloat(i.qty) > 0 && this.itemProblem(i))
                          .map(i => `${i.name}: ${this.itemProblem(i)}`)
                          .join('; ');
                  },

                  confirmSubmit(e) {
                      const deliveryList = this.willLog;
                      const skipList = this.willSkip;
                      const blockList = this.willBlock;

                      if (deliveryList.length === 0 && skipList.length === 0) {
                          alert(blockList.length > 0
                              ? 'Nothing can be delivered. Fix the red items first.'
                              : 'Nothing to save.');
                          e.preventDefault();
                          return;
                      }

                      let message = '';

                      if (deliveryList.length > 0) {
                          const deliveryNames = deliveryList
                              .map(r => `${r.name} (${this.rowItemsLabel(r)})`)
                              .join('\n — ');

                          message +=
                              `Deliver to ${deliveryList.length} customer(s)?\n\n — ${deliveryNames}`;
                      }

                      if (blockList.length > 0) {
                          const blockNames = blockList
                              .map(r => `${r.name} — ${this.rowBlockedLabel(r)}`)
                              .join('\n — ');

                          if (message) {
                              message += '\n\n';
                          }

                          message += `These items will NOT be delivered:\n\n — ${blockNames}`;
                      }

                      if (skipList.length > 0) {
                          const skipNames = skipList
                              .map(r => r.name)
                              .join('\n — ');

                          if (message) {
                              message += '\n\n';
                          }

                          message +=
                              `Skip ${skipList.length} customer(s) today?\n\n — ${skipNames}`;
                      }

                      const ok = confirm(message);

                      if (!ok) {
                          e.preventDefault();
                      }
                  }
              }"
              @submit="confirmSubmit($event)">

            @csrf

            <div class="flex flex-col sm:flex-row sm:items-stretch sm:justify-between gap-3 mb-4">

                <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-4 flex items-center gap-4 w-full sm:flex-1 min-w-0">
                    <x-search-bar
                        placeholder="Search customers..."
                        x-model="search"
                        :live="false"
                    />
                </div>

                <div class="shrink-0 flex items-center gap-2 bg-white rounded-control border border-outline-variant p-2 shadow-bento-sm">

                    <button type="button"
                            @click="selectAll()"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition">
                        <i data-lucide="check-check" class="h-4 w-4"></i>
                        Select All
                    </button>

                    <button type="button"
                            @click="selectNone()"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control border border-outline-variant bg-white hover:bg-surface-container-low text-ink-variant hover:text-ink text-sm font-semibold px-4 py-2.5 transition">
                        <i data-lucide="square" class="h-4 w-4"></i>
                        Select None
                    </button>

                </div>
            </div>

            <div class="bg-white rounded-bento border border-outline-variant shadow-bento overflow-hidden">

                <div class="overflow-x-auto">

                    <table class="w-full text-sm">

                        <thead>
                            <tr class="text-left text-outline text-[11px] uppercase tracking-wider border-b border-outline-variant">
                                <th class="px-6 py-3 font-semibold w-10"></th>
                                <th class="px-6 py-3 font-semibold">Customer</th>
                                <th class="px-6 py-3 font-semibold">Items &amp; Quantity Today</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-outline-variant/60">

                            @foreach($customers as $row)

                                @php
                                    $c = $row['customer'];
                                    $i = $loop->index;
                                @endphp

                                <tr
                                    class="{{ $row['delivered_today'] ? 'bg-forest-50/40' : '' }}"
                                    x-show="rows[{{ $i }}].name.toLowerCase().includes(search.toLowerCase())"
                                    :class="
                                        !rows[{{ $i }}].checked &&
                                        !rows[{{ $i }}].delivered &&
                                        rows[{{ $i }}].hasItems
                                            ? 'bg-clay-container/30'
                                            : ''
                                    "
                                >

                                    <td class="px-6 py-4 align-top">
                                        @if(!$row['delivered_today'] && count($row['items']) > 0)
                                            <input
                                                type="checkbox"
                                                x-model="rows[{{ $i }}].checked"
                                                class="h-4 w-4 rounded border-outline-variant text-forest-700 focus:ring-forest-700"
                                            >

                                            {{-- When unchecked, save today's skipped state. --}}
                                            <input
                                                type="hidden"
                                                name="skipped[{{ $c->id }}]"
                                                value="1"
                                                :disabled="
                                                    rows[{{ $i }}].checked ||
                                                    rows[{{ $i }}].delivered ||
                                                    !rows[{{ $i }}].hasItems
                                                "
                                            >
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 align-top">
                                        <p class="font-semibold text-ink">{{ $c->name }}</p>
                                        <p class="text-xs text-outline">{{ $c->phone ?? '' }}</p>
                                    </td>

                                    {{-- One line per item: name, unit, quantity box and its own status. --}}
                                    <td class="px-6 py-4 align-top">
                                        @if(count($row['items']) > 0)
                                            <div class="space-y-3">
                                                @foreach($row['items'] as $j => $item)
                                                    <div>
                                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                            <span class="min-w-[8rem] font-medium text-ink">
                                                                {{ $item['name'] }}
                                                                <span class="text-outline font-normal">({{ $item['unit'] }})</span>
                                                            </span>

                                                            @if($item['delivered'])
                                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-forest-700">
                                                                    <i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i>
                                                                    Delivered
                                                                </span>
                                                            @else
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    aria-label="Quantity of {{ $item['name'] }}"
                                                                    name="deliveries[{{ $c->id }}][{{ $item['key'] }}]"
                                                                    x-model="rows[{{ $i }}].items[{{ $j }}].qty"
                                                                    :disabled="!rows[{{ $i }}].checked || {{ $item['blocked_reason'] ? 'true' : 'false' }}"
                                                                    :class="itemProblem(rows[{{ $i }}].items[{{ $j }}]) ? 'border-clay' : 'border-outline-variant'"
                                                                    class="w-24 rounded-control border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 disabled:bg-surface-container-low disabled:text-outline"
                                                                >
                                                                <span class="text-xs text-outline">{{ $item['unit'] }}</span>
                                                            @endif
                                                        </div>

                                                        @unless($item['delivered'])
                                                            <p x-show="itemProblem(rows[{{ $i }}].items[{{ $j }}])" x-cloak
                                                               class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-clay">
                                                                <i data-lucide="alert-triangle" class="h-3 w-3"></i>
                                                                Blocked: <span x-text="itemProblem(rows[{{ $i }}].items[{{ $j }}])"></span>
                                                            </p>
                                                        @endunless
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-clay text-xs font-semibold">No items set — edit customer</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 align-top">
                                        @if($row['delivered_today'])

                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800 border border-forest-300/40">
                                                <i data-lucide="check-circle-2" class="h-3 w-3"></i>
                                                Delivered
                                            </span>

                                        @elseif(count($row['items']) > 0)

                                            @if($row['status'] === 'partial')
                                                <span class="mb-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-forest-50 text-forest-800 border border-forest-300/40">
                                                    <i data-lucide="circle-dot" class="h-3 w-3"></i>
                                                    Partially Delivered
                                                </span>
                                            @endif

                                            <div>
                                                <span x-show="rows[{{ $i }}].checked"
                                                      class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-surface-container-low text-outline">
                                                    <i data-lucide="clock" class="h-3 w-3"></i>
                                                    Will Log
                                                </span>

                                                <span x-show="!rows[{{ $i }}].checked" x-cloak
                                                      class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-clay-container text-clay">
                                                    <i data-lucide="x-circle" class="h-3 w-3"></i>
                                                    Skipped Today
                                                </span>
                                            </div>

                                            {{-- Reason (optional). Only sent while the customer is unticked. --}}
                                            <div x-show="!rows[{{ $i }}].checked" x-cloak class="mt-2">
                                                <input type="text"
                                                       name="skip_reason[{{ $c->id }}]"
                                                       x-model="rows[{{ $i }}].skipReason"
                                                       :disabled="rows[{{ $i }}].checked"
                                                       maxlength="255"
                                                       placeholder="Reason (optional)"
                                                       class="w-48 rounded-control border border-outline-variant px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-forest-700">
                                            </div>

                                            {{-- Customer was skipped earlier but came back: log it in one click. --}}
                                            @if($row['skipped'])
                                                <button type="submit"
                                                        form="log-delivery-{{ $c->id }}"
                                                        class="mt-2 inline-flex items-center gap-1.5 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-xs font-semibold px-3 py-1.5 transition">
                                                    <i data-lucide="truck" class="h-3.5 w-3.5"></i>
                                                    Log delivery
                                                </button>
                                            @endif

                                        @else

                                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full bg-surface-container-low text-outline">
                                                <i data-lucide="minus" class="h-3 w-3"></i>
                                                —
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

                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 shadow-bento-sm transition"
                >
                    <i data-lucide="truck" class="h-4 w-4"></i>
                    Save Today's Round
                </button>

                <p class="text-sm text-ink-variant">
                    <span class="font-semibold text-ink tabular" x-text="willLog.length"></span>
                    customer(s) will be logged
                    <span class="mx-1">·</span>
                    <span class="font-semibold text-clay tabular" x-text="willSkip.length"></span>
                    customer(s) skipped
                </p>

            </div>

            <p class="text-xs text-outline mt-2">
                Delivered customers are added to khata. Skipped customers are remembered
                for today only, and you can log them later today with the "Log delivery" button.
                An item that is inactive or out of stock is blocked, and the other items are still delivered.
            </p>

        </form>

        {{-- Separate one-click forms for customers who were skipped earlier today.
             They send each item's usual quantity. --}}
        @foreach($customers as $row)
            @if($row['skipped'] && !$row['delivered_today'])
                <form id="log-delivery-{{ $row['customer']->id }}"
                      method="POST"
                      action="{{ route('daily-round.store') }}"
                      onsubmit="return confirm('Log today\'s delivery for {{ addslashes($row['customer']->name) }}?');">
                    @csrf
                    @foreach($row['items'] as $item)
                        @if(!$item['delivered'] && !$item['blocked_reason'])
                            <input type="hidden"
                                   name="deliveries[{{ $row['customer']->id }}][{{ $item['key'] }}]"
                                   value="{{ $item['default_qty'] }}">
                        @endif
                    @endforeach
                </form>
            @endif
        @endforeach

    @endif

</div>
@endsection
