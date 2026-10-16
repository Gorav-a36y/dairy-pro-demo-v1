@extends('layouts.app')
@section('title', 'Receipt ' . $sale->invoice_no)
@section('page-title', 'Receipt')

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
        foreach (preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
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

    $padR = fn ($s, $n) => $s . str_repeat(' ', max(0, $n - mb_strlen($s)));
    $padL = fn ($s, $n) => str_repeat(' ', max(0, $n - mb_strlen($s))) . $s;

    // Centered text (wraps if too long).
    $center = fn ($text) => collect($wrap($text, $width))
        ->map(fn ($l) => str_repeat(' ', intdiv(max(0, $width - mb_strlen($l)), 2)) . $l)
        ->implode("\n");

    // Label on the left, value on the right edge.
    $lr = fn ($l, $r) => $l . str_repeat(' ', max(1, $width - mb_strlen($l) - mb_strlen($r))) . $r;

    // "LABEL:     value" — long values wrap and line up neatly.
    $field = function ($label, $value) use ($wrap, $width) {
        $indent = 14;
        $out = [];
        foreach ($wrap($value, $width - $indent) as $i => $row) {
            $out[] = ($i === 0 ? str_pad($label, $indent) : str_repeat(' ', $indent)) . $row;
        }
        return implode("\n", $out);
    };

    $money = fn ($n) => $currency . ' ' . number_format($n, 0);

    // ---- Sale logic ----
    // A sale went on khata when it was not fully paid at the time of sale.
    $isWalkIn = is_null($sale->customer_id);
    $isKhata = round((float) $sale->total_amount - (float) $sale->paid_amount, 2) > 0;
    $newBalance = ($isKhata && $sale->customer) ? $sale->customer->currentBalance() : null;
    $previousBalance = $newBalance !== null ? $newBalance - ($sale->total_amount - $sale->paid_amount) : null;
    $paymentMethodName = \App\Models\PaymentMethod::where('code', $sale->payment_method)->value('name') ?? ucfirst($sale->payment_method);

    $localTime = $settings->localize($sale->created_at);

    $tendered  = $sale->amount_received !== null ? (float) $sale->amount_received : (float) $sale->paid_amount;
    $paidNow   = (float) $sale->paid_amount;
    $changeDue = (!$isKhata && $tendered > $sale->total_amount) ? $tendered - $sale->total_amount : 0;
    $onKhata   = max($sale->total_amount - $paidNow, 0);

    // ---- Build the receipt line by line ----
    $lines = [];
    $lines[] = $line;
    $lines[] = $center(mb_strtoupper($settings->dairy_name ?? 'DairyPro'));
    if ($settings->address) { $lines[] = $center($settings->address); }
    if ($settings->phone)   { $lines[] = $center('Phone: ' . $settings->phone); }
    $lines[] = $line;
    $lines[] = $field('RECEIPT NO:', $sale->invoice_no);
    $lines[] = $field('DATE:', $sale->sale_date->format('d M Y'));
    $lines[] = $field('TIME:', $localTime->format('h:i A'));
    $lines[] = $field('CUSTOMER:', $isWalkIn ? 'Walk-in Customer' : ($sale->customer->name ?? 'Customer'));
    if (!$isWalkIn && $sale->customer?->phone) { $lines[] = $field('CONTACT:', $sale->customer->phone); }
    $lines[] = $field('PAYMENT:', $paymentMethodName);
    $lines[] = $dash;

    // Items: long names wrap to the next line instead of being cut.
    $qtyW = 6; $priceW = 9; $amtW = 10;
    $nameW = $width - $qtyW - $priceW - $amtW;
    $lines[] = $padR('ITEM', $nameW) . $padL('QTY', $qtyW) . $padL('PRICE', $priceW) . $padL('AMOUNT', $amtW);
    $lines[] = $dash;
    foreach ($sale->items as $item) {
        $qty = rtrim(rtrim(number_format($item->quantity, 2, '.', ','), '0'), '.');
        foreach ($wrap($item->item_name, $nameW - 1) as $i => $part) {
            $lines[] = $i === 0
                ? $padR($part, $nameW) . $padL($qty, $qtyW) . $padL(number_format($item->unit_price, 0), $priceW) . $padL(number_format($item->subtotal, 0), $amtW)
                : $part;
        }
    }
    $lines[] = $dash;

    $lines[] = $lr('SUBTOTAL', $money($sale->subtotal));
    if ($sale->discount > 0) { $lines[] = $lr('DISCOUNT', '- ' . $money($sale->discount)); }
    $lines[] = $dash;
    $lines[] = $lr('TOTAL', $money($sale->total_amount));
    $lines[] = $dash;
    if ($paidNow > 0)   { $lines[] = $lr('PAID NOW', $money($paidNow)); }
    if ($changeDue > 0) { $lines[] = $lr('CHANGE RETURNED', $money($changeDue)); }
    if ($onKhata > 0)   { $lines[] = $lr('ON KHATA', $money($onKhata)); }

    if ($isKhata) {
        $lines[] = $line;
        $lines[] = $center('KHATA ACCOUNT SUMMARY');
        $lines[] = $dash;
        $lines[] = $lr('Previous Balance', $money($previousBalance));
        $lines[] = $lr('This Bill', $money($sale->total_amount - $sale->paid_amount));
        $lines[] = $dash;
        $lines[] = $lr('TOTAL PAYABLE', $money($newBalance));
    }

    $lines[] = $line;
    $lines[] = $center('Thank you for shopping with us!');
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
            position: absolute; left: 0; top: 0; width: 100%; margin: 0;
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
        <pre id="receipt" class="font-mono text-[12px] leading-relaxed whitespace-pre" style="color:#000;">{{ implode("\n", $lines) }}</pre>
    </div>

    <div class="flex flex-wrap justify-center gap-3 mt-6 print:hidden">
        <button onclick="window.print()" class="inline-flex items-center gap-2 whitespace-nowrap rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-5 py-3 hover:bg-surface-container-low transition">
            <i data-lucide="printer" class="h-4 w-4"></i> Print Receipt
        </button>
        <a href="{{ route('sales.pos') }}" class="inline-flex items-center gap-2 whitespace-nowrap rounded-control bg-forest-700 hover:bg-forest-800 text-white text-sm font-semibold px-5 py-3 transition">
            <i data-lucide="plus" class="h-4 w-4"></i> New Sale
        </a>
        <a href="{{ route('sales.history') }}" class="inline-flex items-center gap-2 whitespace-nowrap rounded-control border border-outline-variant bg-white text-ink-variant text-sm font-semibold px-5 py-3 hover:bg-surface-container-low transition">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Sales History
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
@endpush
