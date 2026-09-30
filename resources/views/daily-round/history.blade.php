@extends('layouts.app')
@section('title', 'Daily Round History')
@section('page-title', 'Customer Delivery History')

@php
    $customerOptions = $customers->map(fn ($c) => [
        'id' => $c->id,
        'label' => $c->name,
        'sublabel' => $c->phone,
    ]);
@endphp

@section('content')
<div class="space-y-6">

    {{-- Customer + date picker --}}
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6">
        <form method="GET" id="historyFilterForm" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div class="sm:col-span-2">
                <x-searchable-select name="customer_id" :options="$customerOptions" label="Customer"
                                      placeholder="Search customer..." :selected="request('customer_id')" required />
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">From</label>
                <input type="date" name="from" id="fromInput" value="{{ request('from') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">To</label>
                <input type="date" name="to" id="toInput" value="{{ request('to') }}"
                       class="w-full rounded-control border border-outline-variant px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700">
            </div>

            <div class="sm:col-span-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 transition">
                    <i data-lucide="search" class="h-4 w-4"></i> View History
                </button>
                <div class="h-6 w-px bg-outline-variant"></div>
                <button type="button" onclick="setRangeAndSubmit('this_month')"
                        class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-3 hover:bg-surface-container-low transition">
                    <i data-lucide="calendar" class="h-4 w-4"></i> This Month
                </button>
                <button type="button" onclick="setRangeAndSubmit('last_month')"
                        class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-3 hover:bg-surface-container-low transition">
                    <i data-lucide="calendar-clock" class="h-4 w-4"></i> Last Month
                </button>
            </div>
        </form>
    </div>

    @if($selectedCustomer)
        @if($deliveries->isEmpty())
            <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-10 text-center text-outline">
                No deliveries found for {{ $selectedCustomer->name }} in this date range.
            </div>
        @else
            {{-- Action buttons --}}
            <div class="flex items-center gap-3">
                <button type="button" id="downloadBtn"
                        class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition">
                    <i data-lucide="download" class="h-4 w-4"></i> Download PDF
                </button>
                <button type="button" id="shareBtn"
                        class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition">
                    <i data-lucide="share-2" class="h-4 w-4"></i> Share
                </button>
                <span id="pdfStatus" class="text-xs text-outline"></span>
            </div>

            {{-- The report itself — this exact block is what gets turned into a PDF --}}
            <div id="report-content" class="bg-white rounded-bento border border-outline-variant shadow-bento p-8 max-w-2xl">
                <div class="text-center mb-6">
                    <h2 class="font-serif text-xl font-bold text-ink">{{ $settings->dairy_name ?? 'DairyPro' }}</h2>
                    @if($settings->address)
                        <p class="text-xs text-outline mt-1">{{ $settings->address }}</p>
                    @endif
                    @if($settings->phone)
                        <p class="text-xs text-outline">Phone: {{ $settings->phone }}</p>
                    @endif
                </div>

                <div class="border-t border-b border-outline-variant py-3 mb-4 text-center">
                    <p class="font-serif text-lg font-semibold text-ink">Delivery History</p>
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm mb-6">
                    <p class="text-outline">Customer:</p>
                    <p class="font-semibold text-ink text-right">{{ $selectedCustomer->name }}</p>
                    @if($selectedCustomer->phone)
                        <p class="text-outline">Phone:</p>
                        <p class="text-ink text-right">{{ $selectedCustomer->phone }}</p>
                    @endif
                    <p class="text-outline">Period:</p>
                    <p class="text-ink text-right">
                        {{ request('from') ? \Carbon\Carbon::parse(request('from'))->format('M j, Y') : 'Start' }}
                        —
                        {{ request('to') ? \Carbon\Carbon::parse(request('to'))->format('M j, Y') : 'Today' }}
                    </p>
                </div>

                <table class="w-full text-sm mb-6">
                    <thead>
                        <tr class="border-b-2 border-outline-variant text-left text-outline text-[11px] uppercase">
                            <th class="py-2">Date</th>
                            <th class="py-2">Item</th>
                            <th class="py-2 text-right">Qty</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @foreach($deliveries as $sale)
                            @foreach($sale->items as $item)
                                <tr>
                                    <td class="py-2 text-ink-variant">{{ $sale->sale_date->format('M j, Y') }}</td>
                                    <td class="py-2 text-ink-variant">{{ $item->item_name }}</td>
                                    <td class="py-2 text-right tabular text-ink-variant">{{ number_format($item->quantity, 2) }} {{ $item->unit }}</td>
                                    <td class="py-2 text-right tabular font-semibold text-ink">{{ $settings->currency }} {{ number_format($item->subtotal, 0) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>

                <div class="border-t-2 border-outline-variant pt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-outline">Total Quantity:</span>
                        <span class="font-semibold text-ink">{{ number_format($summary['total_quantity'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-outline">Total Amount:</span>
                        <span class="font-semibold text-ink">{{ $settings->currency }} {{ number_format($summary['total_amount'], 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-outline">Paid:</span>
                        <span class="font-semibold text-ink">{{ $settings->currency }} {{ number_format($summary['total_paid'], 0) }}</span>
                    </div>
                    <div class="flex justify-between text-base pt-1.5 border-t border-outline-variant">
                        <span class="font-semibold text-ink">Balance Owed:</span>
                        <span class="font-bold text-clay">{{ $settings->currency }} {{ number_format($summary['balance'], 0) }}</span>
                    </div>
                </div>

                <p class="text-center text-[11px] text-outline mt-6 pt-4 border-t border-outline-variant">
                    Software by GoravAI
                </p>
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    function setRangeAndSubmit(range) {
        const today = new Date();
        let from, to;

        if (range === 'this_month') {
            from = new Date(today.getFullYear(), today.getMonth(), 1);
            to = today;
        } else { // last_month
            from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            to = new Date(today.getFullYear(), today.getMonth(), 0);
        }

        const fmt = (d) => d.toISOString().split('T')[0];
        document.getElementById('fromInput').value = fmt(from);
        document.getElementById('toInput').value = fmt(to);
        document.getElementById('historyFilterForm').requestSubmit();
    }

    async function buildPdfBlob() {
        const el = document.getElementById('report-content');
        const canvas = await html2canvas(el, { scale: 2, backgroundColor: '#ffffff' });
        const imgData = canvas.toDataURL('image/png');

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'pt', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const imgHeight = (canvas.height * pageWidth) / canvas.width;

        pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, imgHeight);
        return pdf.output('blob');
    }

    function customerFileName() {
        const name = @json($selectedCustomer->name ?? 'customer').replace(/[^a-z0-9]+/gi, '-');
        return `${name}-delivery-history.pdf`;
    }

    document.getElementById('downloadBtn')?.addEventListener('click', async function () {
        const status = document.getElementById('pdfStatus');
        status.textContent = 'Generating...';
        try {
            const blob = await buildPdfBlob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = customerFileName();
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            status.textContent = '';
        } catch (e) {
            status.textContent = 'Failed to generate PDF.';
            console.error(e);
        }
    });

    document.getElementById('shareBtn')?.addEventListener('click', async function () {
        const status = document.getElementById('pdfStatus');
        status.textContent = 'Preparing...';
        try {
            const blob = await buildPdfBlob();
            const file = new File([blob], customerFileName(), { type: 'application/pdf' });

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title: 'Delivery History' });
                status.textContent = '';
            } else {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = customerFileName();
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                status.textContent = 'Sharing not supported here — downloaded instead.';
            }
        } catch (e) {
            if (e.name !== 'AbortError') {
                status.textContent = 'Failed to share.';
                console.error(e);
            } else {
                status.textContent = '';
            }
        }
    });
</script>
@endpush