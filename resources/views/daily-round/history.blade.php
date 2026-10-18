@extends('layouts.app')
@section('title', 'Daily Round History')
@section('page-title', 'Customer Delivery History')

@php
    $customerOptions = $customers->map(fn ($c) => [
        'id' => $c->id,
        'label' => $c->name,
        'sublabel' => $c->phone,
    ]);

    $hasBalance = $selectedCustomer && ($summary['balance'] ?? 0) > 0;
@endphp

@section('content')
<div class="space-y-6">

    {{-- ============ FILTER CARD ============ --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6 sm:p-8 print:hidden">
        <div class="mb-6">
            <h2 class="font-serif text-2xl font-semibold text-ink">
                Find a customer's deliveries
            </h2>
            <p class="text-sm text-ink-variant mt-1">
                Pick a customer and a date range to view their delivery history.
            </p>
        </div>

        <form method="GET" id="historyFilterForm" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">

            <div class="sm:col-span-2">
                <x-searchable-select
                    name="customer_id"
                    :options="$customerOptions"
                    label="Customer"
                    placeholder="Search customer..."
                    :selected="request('customer_id')"
                    required
                />
            </div>

            <div>
                <label
                    for="fromInput"
                    class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide"
                >
                    From
                </label>

                <input
                    type="date"
                    name="from"
                    id="fromInput"
                    value="{{ request('from') }}"
                    class="w-full h-12 rounded-control border border-outline-variant bg-white px-4 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700"
                >
            </div>

            <div>
                <label
                    for="toInput"
                    class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide"
                >
                    To
                </label>

                <input
                    type="date"
                    name="to"
                    id="toInput"
                    value="{{ request('to') }}"
                    class="w-full h-12 rounded-control border border-outline-variant bg-white px-4 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700"
                >
            </div>

            <div class="sm:col-span-4 flex flex-wrap items-center gap-2 pt-2 border-t border-outline-variant/60">
                <span class="text-[11px] font-semibold text-outline uppercase tracking-wide mr-1">
                    Quick range
                </span>

                <button
                    type="button"
                    onclick="setRangeAndSubmit('last_7')"
                    class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-white text-ink-variant text-sm font-medium px-4 py-2 hover:bg-surface-container-low transition"
                >
                    Last 7 days
                </button>

                <button
                    type="button"
                    onclick="setRangeAndSubmit('this_month')"
                    class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-white text-ink-variant text-sm font-medium px-4 py-2 hover:bg-surface-container-low transition"
                >
                    This month
                </button>

                <button
                    type="button"
                    onclick="setRangeAndSubmit('last_month')"
                    class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-white text-ink-variant text-sm font-medium px-4 py-2 hover:bg-surface-container-low transition"
                >
                    Last month
                </button>

                <button
                    type="button"
                    onclick="setRangeAndSubmit('all')"
                    class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-white text-ink-variant text-sm font-medium px-4 py-2 hover:bg-surface-container-low transition"
                >
                    All time
                </button>

                <button
                    type="submit"
                    class="ml-auto inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-6 py-3 transition w-full sm:w-auto"
                >
                    <i data-lucide="search" class="h-4 w-4"></i>
                    View History
                </button>
            </div>
        </form>
    </div>

    @if(!$selectedCustomer)

        {{-- ============ EMPTY STATE: nothing chosen yet ============ --}}
        <div class="bg-white rounded-bento border border-dashed border-outline-variant p-12 text-center print:hidden">
            <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-surface-container-low flex items-center justify-center">
                <i data-lucide="truck" class="h-6 w-6 text-forest-700"></i>
            </div>

            <p class="font-serif text-lg font-semibold text-ink">
                No customer selected
            </p>

            <p class="text-sm text-outline mt-1">
                Choose a customer above to see their delivery history.
            </p>
        </div>

    @else

        @if($deliveries->isEmpty())

            {{-- ============ EMPTY STATE: no deliveries ============ --}}
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-12 text-center print:hidden">
                <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-surface-container-low flex items-center justify-center">
                    <i data-lucide="package-open" class="h-6 w-6 text-outline"></i>
                </div>

                <p class="font-serif text-lg font-semibold text-ink">
                    No deliveries found
                </p>

                <p class="text-sm text-outline mt-1">
                    {{ $selectedCustomer->name }} has no deliveries in this date range.
                    Try a wider range.
                </p>
            </div>

        @else

            {{-- ============ SUMMARY CARDS ============ --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 print:hidden">

                <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
                    <p class="text-[11px] font-semibold text-outline uppercase tracking-wide">
                        Deliveries
                    </p>

                    <p class="font-serif text-3xl font-bold text-ink mt-2 tabular">
                        {{ $deliveries->count() }}
                    </p>
                </div>

                <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
                    <p class="text-[11px] font-semibold text-outline uppercase tracking-wide">
                        Total Quantity
                    </p>

                    <p class="font-serif text-3xl font-bold text-ink mt-2 tabular">
                        {{ number_format($summary['total_quantity'], 2) }}
                    </p>
                </div>

                <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-5">
                    <p class="text-[11px] font-semibold text-outline uppercase tracking-wide">
                        Total Amount
                    </p>

                    <p class="font-serif text-2xl font-bold text-ink mt-2 tabular">
                        <span class="text-sm font-sans font-semibold text-outline">
                            {{ $settings->currency }}
                        </span>

                        {{ number_format($summary['total_amount'], 0) }}
                    </p>
                </div>

                <div class="rounded-bento border shadow-bento p-5
                    {{ $hasBalance
                        ? 'bg-clay/10 border-clay/30'
                        : 'bg-forest-700/10 border-forest-700/30' }}">

                    <p class="text-[11px] font-semibold uppercase tracking-wide
                        {{ $hasBalance ? 'text-clay' : 'text-forest-700' }}">
                        Balance Owed
                    </p>

                    <p class="font-serif text-2xl font-bold mt-2 tabular
                        {{ $hasBalance ? 'text-clay' : 'text-forest-700' }}">

                        <span class="text-sm font-sans font-semibold">
                            {{ $settings->currency }}
                        </span>

                        {{ number_format($summary['balance'], 0) }}
                    </p>
                </div>
            </div>

            {{-- ============ ACTION BAR ============ --}}
            <div class="max-w-2xl mx-auto flex flex-wrap items-center gap-3 print:hidden">
                <button
                    type="button"
                    id="printBtn"
                    class="inline-flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 transition"
                >
                    <i data-lucide="printer" class="h-4 w-4"></i>
                    Print History
                </button>
            </div>

            {{-- ============ DAILY ROUND HISTORY / PRINT AREA ============ --}}
            <div
                id="report-content"
                class="bg-white rounded-bento border border-outline-variant shadow-bento max-w-2xl mx-auto overflow-hidden"
            >

                {{-- Header --}}
                <div class="bg-forest-700 text-white text-center px-8 py-7 print-header">
                    <h2 class="font-serif text-2xl font-bold">
                        {{ $settings->dairy_name ?? 'DairyPro' }}
                    </h2>

                    @if($settings->address)
                        <p class="text-sm text-white/80 mt-1">
                            {{ $settings->address }}
                        </p>
                    @endif

                    @if($settings->phone)
                        <p class="text-sm text-white/80">
                            Phone: {{ $settings->phone }}
                        </p>
                    @endif
                </div>

                <div class="p-8">

                    {{-- Title + status --}}
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <p class="text-[11px] font-semibold text-outline uppercase tracking-wide">
                                Statement
                            </p>

                            <p class="font-serif text-xl font-semibold text-ink">
                                Delivery History
                            </p>
                        </div>

                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                            {{ $hasBalance
                                ? 'bg-clay/10 text-clay'
                                : 'bg-forest-700/10 text-forest-700' }}"
                        >
                            {{ $hasBalance ? 'Balance due' : 'Fully paid' }}
                        </span>
                    </div>

                    {{-- Customer details --}}
                    <div class="rounded-control bg-surface-container-low p-4 mb-6 grid grid-cols-2 gap-y-2 text-sm">

                        <p class="text-outline">
                            Customer
                        </p>

                        <p class="font-semibold text-ink text-right">
                            {{ $selectedCustomer->name }}
                        </p>

                        @if($selectedCustomer->phone)
                            <p class="text-outline">
                                Phone
                            </p>

                            <p class="text-ink text-right tabular">
                                {{ $selectedCustomer->phone }}
                            </p>
                        @endif

                        <p class="text-outline">
                            Period
                        </p>

                        <p class="text-ink text-right">
                            {{ request('from')
                                ? \Carbon\Carbon::parse(request('from'))->format('M j, Y')
                                : 'Start' }}

                            —

                            {{ request('to')
                                ? \Carbon\Carbon::parse(request('to'))->format('M j, Y')
                                : 'Today' }}
                        </p>

                        <p class="text-outline">
                            Printed on
                        </p>

                        <p class="text-ink text-right">
                            {{ $settings->now()->format('M j, Y') }}
                        </p>
                    </div>

                    {{-- Delivery history table --}}
                    <table class="w-full text-sm mb-6">
                        <thead>
                            <tr class="border-b-2 border-outline-variant text-left text-outline text-[11px] uppercase tracking-wide">
                                <th class="py-2 pr-2 font-semibold">
                                    Date
                                </th>

                                <th class="py-2 pr-2 font-semibold">
                                    Item
                                </th>

                                <th class="py-2 pr-2 font-semibold text-right">
                                    Qty
                                </th>

                                <th class="py-2 font-semibold text-right">
                                    Amount
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-outline-variant/60">

                            @foreach($deliveries as $sale)

                                @foreach($sale->items as $item)

                                    <tr>
                                        <td class="py-2.5 pr-2 text-ink-variant whitespace-nowrap align-top">
                                            @if($loop->first)
                                                <span class="font-semibold text-ink">
                                                    {{ $sale->sale_date->format('M j, Y') }}
                                                </span>
                                            @endif
                                        </td>

                                        <td class="py-2.5 pr-2 text-ink-variant">
                                            {{ $item->item_name }}
                                        </td>

                                        <td class="py-2.5 pr-2 text-right tabular text-ink-variant whitespace-nowrap">
                                            {{ number_format($item->quantity, 2) }}
                                            {{ $item->unit }}
                                        </td>

                                        <td class="py-2.5 text-right tabular font-semibold text-ink whitespace-nowrap">
                                            {{ $settings->currency }}
                                            {{ number_format($item->subtotal, 0) }}
                                        </td>
                                    </tr>

                                @endforeach

                            @endforeach

                        </tbody>
                    </table>

                    {{-- Totals --}}
                    <div class="rounded-control border border-outline-variant p-4 space-y-2 text-sm">

                        <div class="flex justify-between">
                            <span class="text-outline">
                                Total Quantity
                            </span>

                            <span class="font-semibold text-ink tabular">
                                {{ number_format($summary['total_quantity'], 2) }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-outline">
                                Total Amount
                            </span>

                            <span class="font-semibold text-ink tabular">
                                {{ $settings->currency }}
                                {{ number_format($summary['total_amount'], 0) }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-outline">
                                Paid
                            </span>

                            <span class="font-semibold text-forest-700 tabular">
                                {{ $settings->currency }}
                                {{ number_format($summary['total_paid'], 0) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center text-base pt-3 border-t border-outline-variant">
                            <span class="font-semibold text-ink">
                                Balance Owed
                            </span>

                            <span
                                class="font-serif text-xl font-bold tabular
                                {{ $hasBalance ? 'text-clay' : 'text-forest-700' }}"
                            >
                                {{ $settings->currency }}
                                {{ number_format($summary['balance'], 0) }}
                            </span>
                        </div>

                    </div>

                    {{-- Footer --}}
                    <div class="mt-6 pt-4 border-t border-outline-variant text-center">
                        <p class="text-xs text-ink-variant">
                            We truly value your trust. See you at the next delivery!
                        </p>

                        <p class="text-[13px] text-outline mt-2">
                            Software by
                            <span class="font-semibold text-ink-variant">
                                GoravAI
                            </span>

                            <span class="mx-1">
                                ·
                            </span>

                            WhatsApp
                            <span class="font-semibold text-forest-700 tabular">
                                +92 331 2726787
                            </span>
                        </p>
                    </div>

                </div>
            </div>

        @endif
    @endif

</div>
@endsection


@push('scripts')
<script>
    // Format a date using the LOCAL day.
    // This avoids toISOString() shifting the date in Pakistan time.
    function fmtLocal(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');

        return `${y}-${m}-${day}`;
    }

    function setRangeAndSubmit(range) {
        const today = new Date();

        let from = '';
        let to = '';

        if (range === 'this_month') {
            from = fmtLocal(
                new Date(today.getFullYear(), today.getMonth(), 1)
            );

            to = fmtLocal(today);

        } else if (range === 'last_month') {
            from = fmtLocal(
                new Date(today.getFullYear(), today.getMonth() - 1, 1)
            );

            to = fmtLocal(
                new Date(today.getFullYear(), today.getMonth(), 0)
            );

        } else if (range === 'last_7') {
            const start = new Date(today);

            start.setDate(today.getDate() - 6);

            from = fmtLocal(start);
            to = fmtLocal(today);
        }

        document.getElementById('fromInput').value = from;
        document.getElementById('toInput').value = to;

        document.getElementById('historyFilterForm').requestSubmit();
    }

    /*
     * Print only the Daily Round history.
     *
     * The @media print CSS below hides everything except
     * #report-content. This means the browser's native print
     * dialog prints the delivery history and nothing else.
     */
    document.getElementById('printBtn')?.addEventListener('click', function () {
        window.print();
    });

    if (window.lucide) {
        lucide.createIcons();
    }
</script>

<style>
    /*
     * Browser print layout.
     *
     * Everything on the page is hidden first.
     * Only the Daily Round history is made visible.
     */
    @media print {

        @page {
            size: A4;
            margin: 0;
        }

        html,
        body {
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        body * {
            visibility: hidden !important;
        }

        #report-content,
        #report-content * {
            visibility: visible !important;
        }

        #report-content {
            display: block !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            overflow: visible !important;
            background: #ffffff !important;
        }

        /*
         * Keep the report's internal spacing.
         */
        #report-content > div:last-child {
            padding: 24px !important;
        }

        /*
         * Prevent table rows and totals from being
         * unnecessarily split across printed pages.
         */
        #report-content table {
            page-break-inside: auto;
        }

        #report-content tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        #report-content thead {
            display: table-header-group;
        }

        #report-content .rounded-control {
            break-inside: avoid;
        }

        /*
         * Avoid printing UI-only elements if they
         * happen to exist inside the application layout.
         */
        .print\:hidden {
            display: none !important;
        }
    }
</style>
@endpush
