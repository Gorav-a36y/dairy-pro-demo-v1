@extends('layouts.app')
@section('title', 'Receipt ' . $sale->invoice_no)
@section('page-title', 'Receipt')

@php
    $width = 48;
    $line = str_repeat('=', $width);
    $dash = str_repeat('-', $width);
    $currency = $settings->currency ?? 'Rs.';

    $center = fn ($text) => str_pad($text, $width, ' ', STR_PAD_BOTH);

    // Label column (15) + right-aligned value (33) = 48.
    $row = fn ($label, $value) => str_pad($label, 15) . str_pad($value, 33, ' ', STR_PAD_LEFT);

    // ITEM | QTY | PRICE | AMOUNT — 20 + 7 + 11 + 10 = 48 (no SR column)
    $itemRow = fn ($item, $qty, $rate, $amount) =>
        str_pad(mb_substr($item, 0, 20), 20) .
        str_pad($qty, 7, ' ', STR_PAD_LEFT) .
        str_pad($rate, 11, ' ', STR_PAD_LEFT) .
        str_pad($amount, 10, ' ', STR_PAD_LEFT);

    // Indented rows for the khata summary block only.
    $totalsRow = fn ($label, $value) => str_pad('', 15) . str_pad($label, 17) . str_pad($value, 16, ' ', STR_PAD_LEFT);

    $isWalkIn = is_null($sale->customer_id);
    $isKhata = $sale->payment_status === 'unpaid';
    $newBalance = ($isKhata && $sale->customer) ? $sale->customer->currentBalance() : null;
    $previousBalance = $newBalance !== null ? $newBalance - $sale->total_amount : null;
    $paymentMethodName = \App\Models\PaymentMethod::where('code', $sale->payment_method)->value('name') ?? ucfirst($sale->payment_method);

    $localTime = $settings->localize($sale->created_at);

    // Money facts, kept simple for the receipt reader.
    $tendered  = $sale->amount_tendered !== null ? (float) $sale->amount_tendered : (float) $sale->paid_amount;
    $paidNow   = (float) $sale->paid_amount;
    $changeDue = (!$isKhata && $tendered > $sale->total_amount) ? $tendered - $sale->total_amount : 0;
    $onKhata   = max($sale->total_amount - $paidNow, 0);

    $statusText = match ($sale->payment_status) {
        'paid'    => 'PAID',
        'partial' => 'PARTIALLY PAID',
        default   => 'UNPAID (KHATA)',
    };
@endphp

@section('content')
<style>
    /* Print ONLY the receipt — hides the sidebar, header, footer,
       action buttons and anything else around it. */
    @media print {
        @page { margin: 0.4cm; size: auto; }
        body { background: #fff !important; }
        body * { visibility: hidden; }
        #receipt, #receipt * { visibility: visible; }
        #receipt { position: absolute; left: 0; top: 0; width: 100%; margin: 0; }
    }
</style>

<div class="max-w-[340px] mx-auto flex justify-center">
    <pre id="receipt" class="font-mono text-[12px] leading-relaxed text-ink whitespace-pre">{{ $line }}
{{ $center(strtoupper($settings->dairy_name ?? 'DairyPro')) }}
@if($settings->address)
{{ $center($settings->address) }}
@endif
@if($settings->phone)
{{ $center('Phone: ' . $settings->phone) }}
@endif
{{ $line }}
{{ $row('RECEIPT NO', $sale->invoice_no) }}
{{ $row('DATE & TIME', $sale->sale_date->format('d M Y') . ' ' . $localTime->format('h:i A')) }}
{{ $row('CUSTOMER', $isWalkIn ? 'Walk-in Customer' : $sale->customer->name) }}
@if(!$isWalkIn && $sale->customer->phone)
{{ $row('CONTACT', $sale->customer->phone) }}
@endif
{{ $row('PAYMENT MODE', $paymentMethodName) }}
{{ $row('STATUS', $statusText) }}
{{ $dash }}
{{ $itemRow('ITEM', 'QTY', 'PRICE', 'AMOUNT') }}
{{ $dash }}
@foreach($sale->items as $item)
{{ $itemRow($item->item_name, number_format($item->quantity, 1), number_format($item->unit_price, 0), number_format($item->subtotal, 0)) }}
@endforeach
{{ $dash }}
{{ $row('SUBTOTAL', $currency . ' ' . number_format($sale->subtotal, 0)) }}
@if($sale->discount > 0)
{{ $row('DISCOUNT', '-' . $currency . ' ' . number_format($sale->discount, 0)) }}
@endif
{{ $dash }}
{{ $row('TOTAL', $currency . ' ' . number_format($sale->total_amount, 0)) }}
{{ $dash }}
@if($paidNow > 0)
{{ $row('PAID NOW', $currency . ' ' . number_format($paidNow, 0)) }}
@endif
@if($changeDue > 0)
{{ $row('CHANGE RETURNED', $currency . ' ' . number_format($changeDue, 0)) }}
@endif
@if($onKhata > 0)
{{ $row('ON KHATA', $currency . ' ' . number_format($onKhata, 0)) }}
@endif
@if($isKhata)
{{ $line }}
{{ $center('KHATA ACCOUNT SUMMARY') }}
{{ $dash }}
{{ $totalsRow('Previous Balance', $currency . ' ' . number_format($previousBalance, 0)) }}
{{ $totalsRow('This Bill', $currency . ' ' . number_format($sale->total_amount, 0)) }}
{{ str_pad('', 15) . str_repeat('-', 33) }}
{{ $totalsRow('Total Payable', $currency . ' ' . number_format($newBalance, 0)) }}
@endif
{{ $line }}
{{ $center('Thank you for shopping with us!') }}
{{ $dash }}
{{ $center('Software by: GoravAI') }}
{{ $line }}</pre>
    </div>

    <div class="flex justify-center gap-3 mt-6 print:hidden">
        <button onclick="window.print()" class="inline-flex items-center gap-2 whitespace-nowrap rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-3 hover:bg-surface-container-low transition">
            <i data-lucide="printer" class="h-4 w-4"></i> Print Receipt
        </button>
        <a href="{{ route('sales.history') }}" class="inline-flex items-center gap-2 whitespace-nowrap rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-3 shadow-bento-sm transition">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Sales History
        </a>
        <a href="{{ route('sales.pos') }}" class="inline-flex items-center gap-2 whitespace-nowrap rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-3 hover:bg-surface-container-low transition">
            <i data-lucide="plus" class="h-4 w-4"></i> New Sale
        </a>
    </div>
@endsection

@push('scripts')
<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
@endpush