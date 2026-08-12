@extends('layouts.app')
@section('title', 'Invoice ' . $sale->invoice_no)
@section('page-title', 'Invoice')

@php
    $width = 48;
    $line = str_repeat('=', $width);
    $dash = str_repeat('-', $width);
    $currency = $settings->currency ?? 'Rs.';

    $center = fn ($text) => str_pad($text, $width, ' ', STR_PAD_BOTH);
    $col = fn ($item, $qty, $price, $subtotal) =>
        str_pad($item, 16) . str_pad($qty, 8, ' ', STR_PAD_LEFT) .
        str_pad($price, 15, ' ', STR_PAD_LEFT) . str_pad($subtotal, 9, ' ', STR_PAD_LEFT);
    $totalsRow = fn ($label, $value) => str_pad('', 23) . str_pad($label, 16) . str_pad($value, 9, ' ', STR_PAD_LEFT);
@endphp

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-bento border border-outline-variant shadow-bento p-6 sm:p-8 overflow-x-auto">
        <pre class="font-mono text-[12.5px] sm:text-sm leading-relaxed text-ink whitespace-pre">{{ $line }}
{{ $center($settings->dairy_name ?? 'DairyPro') }}
{{ $center('Powered by GoravAI') }}
{{ $line }}
INVOICE #:  {{ $sale->invoice_no }}
DATE:       {{ $sale->sale_date->format('F j, Y') }}
METHOD:     {{ ucfirst($sale->payment_method) }}
STATUS:     {{ ucfirst($sale->payment_status) }}
BILLED TO:  {{ $sale->customer->name ?? 'Walk-in Customer' }}
{{ $dash }}
{{ $col('ITEM', 'QTY', 'UNIT PRICE', 'SUBTOTAL') }}
{{ $dash }}
@foreach($sale->items as $item)
{{ $col($item->product->name ?? 'Deleted product', number_format($item->quantity, 1), $currency . ' ' . number_format($item->unit_price, 0), $currency . ' ' . number_format($item->subtotal, 0)) }}
@endforeach
{{ $dash }}
{{ $totalsRow('Subtotal:', $currency . ' ' . number_format($sale->subtotal, 0)) }}
@if($sale->discount > 0)
{{ $totalsRow('Discount:', '-' . $currency . ' ' . number_format($sale->discount, 0)) }}
@endif
{{ $totalsRow('Total:', $currency . ' ' . number_format($sale->total_amount, 0)) }}
{{ $totalsRow('Paid:', $currency . ' ' . number_format($sale->paid_amount, 0)) }}
{{ str_pad('', 23) . str_repeat('-', 25) }}
{{ $totalsRow('Balance:', $currency . ' ' . number_format($sale->total_amount - $sale->paid_amount, 0)) }}
{{ $line }}
{{ $center('Thank you for shopping!') }}
{{ $line }}</pre>
    </div>

    <div class="flex justify-center gap-3 mt-6 print:hidden">
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition">
            <i data-lucide="printer" class="h-4 w-4"></i> Print
        </button>
        <a href="{{ route('sales.history') }}" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Sales History
        </a>
    </div>
</div>
@endsection
