<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
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
            $balance = $c->currentBalance();
            return ['id' => $c->id, 'label' => $c->name, 'sublabel' => $c->phone, 'balance' => $balance];
        });

        $products = Product::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->name, 'sublabel' => $p->unit, 'unit' => $p->unit, 'price' => (float) $p->selling_price, 'stock' => (float) $p->stock_qty]);

        return view('sales.pos', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'sale_date' => 'required|date',
            'discount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:' . implode(',', Sale::PAYMENT_METHODS),
            'is_paid' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $isPaid = $request->boolean('is_paid', true);

        if (! $isPaid && empty($data['customer_id'])) {
            return back()->withInput()->with('error', 'Khata (unpaid) sales must be linked to a registered customer, not a walk-in customer.');
        }

        foreach ($data['items'] as $row) {
            $product = Product::find($row['product_id']);
            if ($product && (float) $row['quantity'] > (float) $product->stock_qty) {
                return back()->withInput()->with('error', "Not enough stock for {$product->name}. Available: {$product->stock_qty} {$product->unit}.");
            }
        }

        $sale = DB::transaction(function () use ($data, $isPaid) {
            $subtotal = 0;
            $items = [];

            foreach ($data['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $qty = (float) $row['quantity'];
                $lineSubtotal = round($qty * (float) $product->selling_price, 2);
                $subtotal += $lineSubtotal;
                $items[] = ['product' => $product, 'qty' => $qty, 'price' => $product->selling_price, 'subtotal' => $lineSubtotal];
            }

            $discount = $data['discount'] ?? 0;
            $total = max($subtotal - $discount, 0);

            $sale = Sale::create([
                'invoice_no' => 'INV-' . strtoupper(Str::random(8)),
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => Auth::id(),
                'sale_date' => $data['sale_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $total,
                'paid_amount' => $isPaid ? $total : 0,
                'payment_method' => $data['payment_method'],
                'payment_status' => $isPaid ? 'paid' : 'unpaid',
            ]);

            foreach ($items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['qty'],
                    'unit_price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ]);

                $item['product']->decrement('stock_qty', $item['qty']);
            }

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
        $sale->load('items.product', 'customer');

        return view('sales.show', compact('sale'));
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return back()->with('success', 'Sale deleted.');
    }
}
