@extends('layouts.app')
@section('title', 'Payment Receipt')
@section('page-title', 'Payment Receipt')

@php
    // ---- Thermal receipt settings (48 characters wide, black & white only) ----
    $width    = 48;
    $line     = str_repeat('=', $width);
    $dash     = str_repeat('-', $width);
    $currency = $settings->currency ?? 'Rs.';

    // Wrap text into lines of max $w characters (safe for Urdu / any language).
    $wrap = function ($text, $w) {
        $lines = [];
        $cur = '';
        foreach (preg_split('/\s+/u', trim((string) $text)) as $word) {
            while (mb_strlen($word) > $w) {
                if ($cur !== '') { $lines[] = $cur; $cur = ''; }
                $lines[] = mb_substr($word, 0, $w);
                $word = mb_substr($word, $w);
            }
            if ($cur === '') {
                $cur = $word;
            } elseif (mb_strlen($cur) + 1 + mb_strlen($word) <= $w) {
                $cur .= ' ' . $word;
            } else {
                $lines[] = $cur;
                $cur = $word;
            }
        }
        if ($cur !== '' || empty($lines)) { $lines[] = $cur; }
        return $lines;
    };

    // Centered text (wraps if too long).
    $center = fn ($text) => collect($wrap($text, $width))
        ->map(fn ($l) => str_repeat(' ', intdiv(max(0, $width - mb_strlen($l)), 2)) . $l)
        ->implode("\n");

    // Label on the left, value on the right edge.
    $lr = fn ($l, $r) => $l . str_repeat(' ', max(1, $width - mb_strlen($l) - mb_strlen($r))) . $r;

    // "LABEL:     value" — long values wrap and line up neatly.
    $field = function ($label, $value) use ($wrap, $width) {
        $indent = 12;
        $out = [];
        foreach ($wrap($value, $width - $indent) as $i => $row) {
            $out[] = ($i === 0 ? str_pad($label, $indent) : str_repeat(' ', $indent)) . $row;
        }
        return implode("\n", $out);
    };

    $money   = fn ($n) => $currency . ' ' . number_format($n, 0);
    $cleared = abs($newBalance) < 0.005;

    // ---- Build the receipt line by line ----
    $lines = [];
    $lines[] = $line;
    $lines[] = $center($settings->dairy_name ?? 'DairyPro');
    if ($settings->address) { $lines[] = $center($settings->address); }
    if ($settings->phone)   { $lines[] = $center('Phone: ' . $settings->phone); }
    $lines[] = $line;
    $lines[] = $center('CUSTOMER PAYMENT RECEIPT');
    $lines[] = $dash;
    $lines[] = $field('RECEIPT #:', 'CUS-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT));
    $lines[] = $field('DATE:', $transaction->transaction_date->format('d M Y'));
    $lines[] = $field('TIME:', $settings->localize($transaction->created_at)->format('g:i A'));
    $lines[] = $field('CUSTOMER:', $transaction->customer->name ?? 'Unknown Customer');
    if ($transaction->customer?->phone) {
        $lines[] = $field('PHONE:', $transaction->customer->phone);
    }
    $lines[] = $dash;
    $lines[] = $lr('Previous Balance', $money($previousBalance));
    $lines[] = $lr('Payment Received', '- ' . $money($transaction->amount));
    $lines[] = $dash;
    $lines[] = $lr('NEW BALANCE', $money($newBalance));
    if ($cleared) { $lines[] = $center('** ACCOUNT CLEARED **'); }
    if ($transaction->notes) {
        $lines[] = $dash;
        $lines[] = $field('NOTE:', $transaction->notes);
    }
    $lines[] = $line;
    $lines[] = $center('Thank you!');
    $lines[] = $dash;
    $lines[] = $center('Software by GoravAI');
    $lines[] = $center('WhatsApp: +92 331 2726787');
    $lines[] = $line;
@endphp

@section('content')
<style>
    /* Print ONLY the receipt: pure black on white, no menus, no shadows. */
    @media print {
        @page { margin: 0.4cm; size: auto; }
        body { background: #fff !important; }
        body * { visibility: hidden; }
        .receipt-paper, .receipt-paper * { visibility: visible; }
        .receipt-paper {
            position: absolute; left: 0; top: 0; width: 100%;
            border: 0 !important; box-shadow: none !important;
            padding: 0 !important; background: #fff !important;
        }
        .receipt-paper pre { color: #000 !important; }
    }
</style>

<div class="max-w-md mx-auto">
    <p class="print:hidden text-center text-sm text-ink-variant mb-3">
        Receipt preview. It prints in black and white for thermal printers.
    </p>

    <div class="receipt-paper bg-white border border-outline-variant rounded-lg shadow-sm p-5 overflow-x-auto flex justify-center">
        <pre class="font-mono text-[12px] leading-relaxed whitespace-pre" style="color:#000;">{{ implode("\n", $lines) }}</pre>
    </div>

    <div class="flex flex-wrap justify-center gap-3 mt-6 print:hidden">
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 transition">
            <i data-lucide="printer" class="h-4 w-4"></i> Print Receipt
        </button>
        <a href="{{ route('khata.index', ['tab' => 'customer', 'id' => $transaction->customer_id]) }}" class="inline-flex items-center gap-2 rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-5 py-3 hover:bg-surface-container-low transition">
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