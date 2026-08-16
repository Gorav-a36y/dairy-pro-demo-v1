<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Ingredient;
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
            'deliveries' => 'required|array',
            'deliveries.*' => 'nullable|numeric|min:0',
        ]);

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
                    'payment_method' => 'cash',
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
}