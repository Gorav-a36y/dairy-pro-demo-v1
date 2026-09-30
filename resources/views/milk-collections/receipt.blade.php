@extends('layouts.app')
@section('title', 'Milk Collection Receipt')
@section('page-title', 'Receipt')

@php
    $width = 48;
    $line = str_repeat('=', $width);      // heavy line — top/bottom and section breaks
    $thin = str_repeat('-', $width);      // light line — inside a section
    $currency = $settings->currency ?? 'Rs.';

    $center = fn ($text) => str_pad($text, $width, ' ', STR_PAD_BOTH);
    $row = fn ($label, $value) => str_pad($label, 23) . str_pad($value, $width - 23, ' ', STR_PAD_LEFT);

    $total  = (float) $milkCollection->total_amount;
    $paid   = (float) $milkCollection->paid_amount;
    $given  = $milkCollection->amount_given !== null ? (float) $milkCollection->amount_given : null;
    $return = (float) $milkCollection->return_amount;
    $isKhata = $paid < $total;

    $newBalance = $milkCollection->supplier->currentBalance();
    $previousBalance = $newBalance - $total + $paid;
    $paymentMethodName = \App\Models\PaymentMethod::where('code', $milkCollection->payment_method)->value('name') ?? ucfirst($milkCollection->payment_method);
    $localTime = $settings->localize($milkCollection->collected_at);

    // Wrap the note so it never breaks the receipt's fixed width — each
    // wrapped line is padded to 3 lines max, then trimmed with "..." so a
    // very long note can't push the receipt out of shape either.
    $noteLines = [];
    if ($milkCollection->notes) {
        $wrapped = wordwrap($milkCollection->notes, $width, "\n", true);
        $noteLines = explode("\n", $wrapped);
        if (count($noteLines) > 3) {
            $noteLines = array_slice($noteLines, 0, 3);
            $noteLines[2] = rtrim(substr($noteLines[2], 0, $width - 3)) . '...';
        }
    }
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
{{ $center('MILK COLLECTION RECEIPT') }}
{{ $thin }}
Invoice:    {{ $milkCollection->invoice_no ?? '—' }}
Date:       {{ $localTime->format('F j, Y g:i A') }}
Supplier:   {{ $milkCollection->supplier->name }}
@if($milkCollection->supplier->phone)
Phone:      {{ $milkCollection->supplier->phone }}
@endif
Method:     {{ $paymentMethodName }}
{{ $thin }}
Item:       {{ $milkCollection->rawMaterial->name }}
Quantity:   {{ number_format($milkCollection->quantity, 2) }} {{ $milkCollection->unit }}
Rate:       {{ $currency }} {{ number_format($milkCollection->purchase_price, 2) }} / {{ $milkCollection->unit }}
{{ $thin }}
{{ $row('Total Amount:', $currency . ' ' . number_format($total, 0)) }}
{{ $row('Amount Paid:', $currency . ' ' . number_format($paid, 0)) }}
@if($given !== null && $return > 0)
{{ $row('Cash Given:', $currency . ' ' . number_format($given, 0)) }}
{{ $row('Return Given:', $currency . ' ' . number_format($return, 0)) }}
@endif
@if($isKhata)
{{ $line }}
{{ $center('SUPPLIER KHATA ACCOUNT') }}
{{ $thin }}
{{ $row('Previous Balance:', $currency . ' ' . number_format($previousBalance, 0)) }}
{{ $row('This Purchase:', '+' . $currency . ' ' . number_format($total, 0)) }}
@if($paid > 0)
{{ $row('Paid Now:', '-' . $currency . ' ' . number_format($paid, 0)) }}
@endif
{{ $thin }}
{{ $row('Balance Owed:', $currency . ' ' . number_format($newBalance, 0)) }}
@else
{{ $thin }}
{{ $row('Status:', 'PAID IN FULL') }}
@endif
@if(count($noteLines) > 0)
{{ $line }}
NOTE:
@foreach($noteLines as $noteLine)
{{ $noteLine }}
@endforeach
@endif
{{ $line }}
{{ $center('Thank you!') }}
{{ $thin }}
{{ $center('Software by GoravAI') }}
@if($settings->phone)
{{ $center('WhatsApp: ' . $settings->phone) }}
@endif
{{ $line }}</pre>
</div>

<div class="flex justify-center gap-3 mt-6 print:hidden">
    <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-control border border-outline-variant text-ink-variant text-sm font-semibold px-4 py-2.5 hover:bg-surface-container-low transition">
        <i data-lucide="printer" class="h-4 w-4"></i> Print Receipt
    </button>
    <a href="{{ route('milk-collections.index') }}" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-4 py-2.5 transition">
        <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Milk Collection
    </a>
</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
@endpush