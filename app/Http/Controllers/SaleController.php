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

class SaleController extends Controller
{
    /** POS screen — build and submit a new sale. */
    public function pos()
    {
        $customers = Customer::orderBy('name')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'label' => $c->name,
                'sublabel' => $c->phone,
                'phone' => $c->phone,
                'balance' => $c->currentBalance(),
            ];
        });

        $products = Product::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($p) => [
                'id' => 'product:' . $p->id, 'label' => $p->name, 'sublabel' => 'Product · ' . $p->unit,
                'unit' => $p->unit, 'price' => (float) $p->selling_price, 'stock' => (float) $p->stock_qty,
            ]);

        $ingredients = Ingredient::orderBy('name')->get()
            ->map(fn ($i) => [
                'id' => 'ingredient:' . $i->id, 'label' => $i->name, 'sublabel' => 'Raw Material · ' . $i->unit,
                'unit' => $i->unit, 'price' => (float) $i->selling_price, 'stock' => (float) $i->stock_qty,
            ]);

        $sellableItems = $products->concat($ingredients)->values();

        return view('sales.pos', compact('customers', 'sellableItems'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'sale_date' => 'required|date',
            'payment_method' => 'nullable|in:' . implode(',', Sale::PAYMENT_METHODS),
            'is_paid' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.item_key' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $isPaid = $request->boolean('is_paid', true);

        if (! $isPaid && empty($data['customer_id'])) {
            return back()->withInput()->with('error', 'Khata (unpaid) sales must be linked to a registered customer, not a walk-in customer.');
        }

        // Resolve every line to its real model up front, and make sure we never oversell stock.
        $resolved = [];
        foreach ($data['items'] as $row) {
            [$type, $id] = array_pad(explode(':', $row['item_key'], 2), 2, null);
            $model = $type === 'product' ? Product::find($id) : ($type === 'ingredient' ? Ingredient::find($id) : null);

            if (! $model) {
                return back()->withInput()->with('error', 'One of the selected items no longer exists. Please re-select it.');
            }

            $qty = (float) $row['quantity'];
            if ($qty > (float) $model->stock_qty) {
                return back()->withInput()->with('error', "Not enough stock for {$model->name}. Available: {$model->stock_qty} {$model->unit}.");
            }

            $resolved[] = ['type' => $type, 'model' => $model, 'qty' => $qty, 'discount' => (float) ($row['discount'] ?? 0)];
        }

        $sale = DB::transaction(function () use ($data, $resolved, $isPaid) {
            $subtotal = 0;
            $totalDiscount = 0;

            $sale = Sale::create([
                'invoice_no' => 'INV-' . strtoupper(Str::random(8)),
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => Auth::id(),
                'sale_date' => $data['sale_date'],
                'subtotal' => 0,
                'discount' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'payment_method' => $isPaid ? ($data['payment_method'] ?? 'cash') : 'cash',
                'payment_status' => $isPaid ? 'paid' : 'unpaid',
            ]);

            foreach ($resolved as $item) {
                $model = $item['model'];
                $lineGross = round($item['qty'] * (float) $model->selling_price, 2);
                $lineDiscount = min($item['discount'], $lineGross);
                $lineSubtotal = round($lineGross - $lineDiscount, 2);

                $subtotal += $lineGross;
                $totalDiscount += $lineDiscount;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_type' => $item['type'],
                    'item_id' => $model->id,
                    'item_name' => $model->name,
                    'unit' => $model->unit,
                    'quantity' => $item['qty'],
                    'unit_price' => $model->selling_price,
                    'discount' => $lineDiscount,
                    'subtotal' => $lineSubtotal,
                ]);

                $model->decrement('stock_qty', $item['qty']);
            }

            $total = round($subtotal - $totalDiscount, 2);

            $sale->update([
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'total_amount' => $total,
                'paid_amount' => $isPaid ? $total : 0,
            ]);

            if (! $isPaid) {
                CustomerTransaction::create([
                    'customer_id' => $data['customer_id'],
                    'type' => 'khata',
                    'amount' => $total,
                    'invoice_no' => $sale->invoice_no,
                    'notes' => 'Sale on khata',
                    'transaction_date' => $data['sale_date'],
                ]);
            }

            return $sale;
        });

        return redirect()->route('sales.show', $sale)->with('success', "Sale recorded: {$sale->invoice_no}");
    }

    /** Sales History — read-only list of past invoices. */
    public function history(Request $request)
    {
        $sales = Sale::with('customer')
            ->when($request->search, fn ($q) => $q->where('invoice_no', 'like', "%{$request->search}%"))
            ->when($request->from, fn ($q) => $q->whereDate('sale_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('sale_date', '<=', $request->to))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('sales.history', compact('sales'));
    }

    public function show(Sale $sale)
    {
        $sale->load('items', 'customer');

        return view('sales.show', compact('sale'));
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return back()->with('success', 'Sale deleted.');
    }
}
