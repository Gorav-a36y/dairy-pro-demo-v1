<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailyRoundController extends Controller
{
    public function index()
    {
        $customers = Customer::where('is_daily_customer', true)
            ->orderBy('name')
            ->get()
            ->map(function ($customer) {
                $item = $customer->dailyItem();

                return [
                    'customer' => $customer,
                    'item' => $item,
                    'delivered_today' => $customer->hasDeliveryToday(),
                ];
            });

        return view('daily-round.index', compact('customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'deliveries' => 'nullable|array',
            'deliveries.*' => 'nullable|numeric|min:0',
        ]);

        if (empty($data['deliveries'])) {
            return back()->with('error', 'Select at least one customer to log.');
        }

        $loggedCount = 0;
        $skipped = [];

        DB::transaction(function () use ($data, &$loggedCount, &$skipped) {
            foreach ($data['deliveries'] as $customerId => $qty) {
                $qty = (float) $qty;
                if ($qty <= 0) {
                    continue;
                }

                $customer = Customer::find($customerId);
                if (! $customer || ! $customer->is_daily_customer) {
                    continue;
                }

                if ($customer->hasDeliveryToday()) {
                    $skipped[] = $customer->name . ' (already logged today)';
                    continue;
                }

                $item = $customer->dailyItem();
                if (! $item) {
                    $skipped[] = $customer->name . ' (no usual item set)';
                    continue;
                }

                if ($qty > (float) $item->stock_qty) {
                    $skipped[] = $customer->name . ' (not enough ' . $item->name . ' in stock)';
                    continue;
                }

                $total = round($qty * (float) $item->selling_price, 2);

                $sale = Sale::create([
                    'invoice_no' => 'INV-' . strtoupper(Str::random(8)),
                    'customer_id' => $customer->id,
                    'user_id' => Auth::id(),
                    'sale_date' => now()->toDateString(),
                    'subtotal' => $total,
                    'discount' => 0,
                    'total_amount' => $total,
                    'paid_amount' => 0,
                    // A daily-round delivery is never paid at the door — it
                    // always goes straight onto the customer's khata, so it
                    // can't be tagged "cash" like a completed counter sale.
                    'payment_method' => PaymentMethod::KHATA_CODE,
                    'source' => Sale::SOURCE_DAILY_ROUND,
                    'payment_status' => 'unpaid',
                ]);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_type' => $customer->daily_item_type,
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit' => $item->unit,
                    'quantity' => $qty,
                    'unit_price' => $item->selling_price,
                    'discount' => 0,
                    'subtotal' => $total,
                ]);

                $item->decrement('stock_qty', $qty);

                CustomerTransaction::create([
                    'customer_id' => $customer->id,
                    'type' => 'khata',
                    'amount' => $total,
                    'invoice_no' => $sale->invoice_no,
                    'notes' => 'Daily round delivery',
                    'transaction_date' => now()->toDateString(),
                ]);

                $loggedCount++;
            }
        });

        $message = "Logged {$loggedCount} deliveries.";
        if (! empty($skipped)) {
            $message .= ' Skipped: ' . implode(', ', $skipped);
        }

        return back()->with($loggedCount > 0 ? 'success' : 'error', $message);
    }

    public function history(Request $request)
    {
        $customers = Customer::where('is_daily_customer', true)->orderBy('name')->get();

        $selectedCustomer = null;
        $deliveries = collect();
        $summary = null;

        if ($request->filled('customer_id')) {
            $selectedCustomer = Customer::find($request->customer_id);

            if ($selectedCustomer) {
                $deliveries = Sale::with('items')
                    ->where('customer_id', $selectedCustomer->id)
                    ->where('source', Sale::SOURCE_DAILY_ROUND)
                    ->when($request->filled('from'), fn ($q) => $q->whereDate('sale_date', '>=', $request->from))
                    ->when($request->filled('to'), fn ($q) => $q->whereDate('sale_date', '<=', $request->to))
                    ->orderBy('sale_date')
                    ->orderBy('id')
                    ->get();

                $summary = [
                    'total_quantity' => $deliveries->flatMap(fn ($sale) => $sale->items)->sum('quantity'),
                    'total_amount'   => $deliveries->sum('total_amount'),
                    'total_paid'     => $deliveries->sum('paid_amount'),
                    'balance'        => $deliveries->sum('total_amount') - $deliveries->sum('paid_amount'),
                ];
            }
        }

        return view('daily-round.history', compact('customers', 'selectedCustomer', 'deliveries', 'summary'));
    }
}