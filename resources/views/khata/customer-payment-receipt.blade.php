@extends('layouts.app')
@section('title', 'Payment Receipt')
@section('page-title', 'Payment Receipt')

@php
    $width = 48;
    $line = str_repeat('=', $width);
    $dash = str_repeat('-', $width);
    $currency = $settings->currency ?? 'Rs.';

    $center = fn ($text) => str_pad($text, $width, ' ', STR_PAD_BOTH);
    $totalsRow = fn ($label, $value) =>
        str_pad('', 23) . str_pad($label, 16) . str_pad($value, 9, ' ', STR_PAD_LEFT);
@endphp

@section('content')
<style>
    @media print {
        @page { margin: 0.4cm; size: auto; }
        body { background: #fff !important; }
    }
</style>

<div class="max-w-[340px] mx-auto flex justify-center">
    <pre class="font-mono text-[12px] leading-relaxed text-ink whitespace-pre">{{ $line }}
{{ $center($settings->dairy_name ?? 'DairyPro') }}
@if($settings->address)
{{ $center($settings->address) }}
@endif
@if($settings->phone)
{{ $center('Phone: ' . $settings->phone) }}
@endif
{{ $line }}
{{ $center('CUSTOMER PAYMENT RECEIPT') }}
{{ $dash }}
RECEIPT #:  CUS-{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}
DATE:       {{ $transaction->transaction_date->format('F j, Y') }} {{ $settings->localize($transaction->created_at)->format('g:i A') }}
CUSTOMER:   {{ $transaction->customer->name ?? 'Unknown Customer' }}
@if($transaction->customer?->phone)
PHONE:      {{ $transaction->customer->phone }}
@endif
{{ $dash }}
{{ $totalsRow('Previous Balance:', $currency . ' ' . number_format($previousBalance, 0)) }}
{{ $totalsRow('Payment Received:', '-' . $currency . ' ' . number_format($transaction->amount, 0)) }}
{{ str_pad('', 23) . str_repeat('-', 25) }}
{{ $totalsRow('New Balance:', $currency . ' ' . number_format($newBalance, 0)) }}
@if($transaction->notes)
{{ $dash }}
NOTE:       {{ $transaction->notes }}
@endif
{{ $line }}
{{ $center('Thank you!') }}
{{ $dash }}
{{ $center('Software Provided By: GoravAI') }}
@if($settings->phone)
{{ $center('Support WhatsApp: ' . $settings->phone) }}
@endif
{{ $line }}</pre>
    </div>

    <div class="flex justify-center gap-3 mt-6 print:hidden">
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition">
            <i data-lucide="printer" class="h-4 w-4"></i> Print Receipt
        </button>
        <a href="{{ route('khata.index', ['tab' => 'customer', 'id' => $transaction->customer_id]) }}" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Khata
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    @if(request('print'))
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    @endif
</script>
@endpush
