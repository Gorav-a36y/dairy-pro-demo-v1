<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CustomerTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Services\InvoiceNumberGenerator;

class SaleController extends Controller
{
    /**
     * POS screen.
     */
    public function pos()
    {
        $customers = Customer::orderBy('name')
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'label' => $customer->name,
                    'sublabel' => $customer->phone,
                    'phone' => $customer->phone,
                    'balance' => $customer->currentBalance(),
                ];
            });

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($product) => [
                'id' => 'product:' . $product->id,
                'label' => $product->name,
                'sublabel' => 'Product · ' . $product->unit,
                'unit' => $product->unit,
                'price' => (float) $product->selling_price,
                'stock' => (float) $product->stock_qty,
            ]);

        $rawMaterials = RawMaterial::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($rawMaterial) => [
                'id' => 'raw_material:' . $rawMaterial->id,
                'label' => $rawMaterial->name,
                'sublabel' => 'Raw Material · ' . $rawMaterial->unit,
                'unit' => $rawMaterial->unit,
                'price' => (float) $rawMaterial->selling_price,
                'stock' => (float) $rawMaterial->stock_qty,
            ]);

        $sellableItems = $products
            ->concat($rawMaterials)
            ->values();

        $paymentMethods = PaymentMethod::active()->ordered()->get();

        return view('sales.pos', compact('customers', 'sellableItems', 'paymentMethods'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'sale_date' => 'required|date',
            'payment_method' => 'required|in:' . implode(',', PaymentMethod::activeCodes()),
            'amount_received' => 'required|numeric|min:0',
            'action' => 'required|in:save,save_print',
            'items' => 'required|array|min:1',
            'items.*.item_key' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.discount' => 'nullable|numeric|min:0',
        ], [], [
            'customer_id' => 'Customer',
            'sale_date' => 'Sale Date',
            'payment_method' => 'Payment Method',
            'amount_received' => 'Amount Received',
        ]);

        $resolved = [];
        $subtotal = 0;
        $totalDiscount = 0;

        foreach ($data['items'] as $row) {
            [$type, $id] = array_pad(
                explode(':', $row['item_key'], 2),
                2,
                null
            );

            $model = match ($type) {
                'product' => Product::whereKey($id)
                    ->where('is_active', true)
                    ->first(),

                'raw_material' => RawMaterial::whereKey($id)
                    ->where('is_active', true)
                    ->first(),

                default => null,
            };

            if (! $model) {
                return back()
                    ->withInput()
                    ->with('error', 'One of the selected items is invalid or no longer exists.');
            }

            $qty = (float) $row['quantity'];

            if ($qty > (float) $model->stock_qty) {
                return back()
                    ->withInput()
                    ->with(
                        'error',
                        "Not enough stock for {$model->name}. Available: {$model->stock_qty} {$model->unit}."
                    );
            }

            $lineGross = round($qty * (float) $model->selling_price, 2);
            $lineDiscount = min((float) ($row['discount'] ?? 0), $lineGross);
            $lineSubtotal = round($lineGross - $lineDiscount, 2);

            $subtotal += $lineGross;
            $totalDiscount += $lineDiscount;

            $resolved[] = [
                'type' => $type,
                'model' => $model,
                'qty' => $qty,
                'discount' => $lineDiscount,
                'subtotal' => $lineSubtotal,
            ];
        }

        $total = round($subtotal - $totalDiscount, 2);

        $isKhata = $data['payment_method'] === PaymentMethod::KHATA_CODE;
        $isPartialKhata = $data['payment_method'] === PaymentMethod::PARTIAL_KHATA_CODE;

        if (($isKhata || $isPartialKhata) && empty($data['customer_id'])) {
            return back()
                ->withInput()
                ->with('error', 'Khata and Partial Khata sales must be linked to a registered customer.');
        }

        if ($isKhata) {
            // Fully unpaid by definition — whatever was posted for
            // amount_received is ignored, not just clamped.
            $amountReceived = 0.0;
            $amountTendered = null;
        } elseif ($isPartialKhata) {
            $amountReceived = round((float) $data['amount_received'], 2);

            if ($amountReceived > $total) {
                return back()
                    ->withInput()
                    ->with('error', 'Paid amount cannot be greater than the total amount.');
            }

            if ($amountReceived >= $total) {
                return back()
                    ->withInput()
                    ->with('error', 'Paid amount cannot equal the total amount for Partial Khata. If the full amount is being paid, select Cash (or another payment method) instead.');
            }

            $amountTendered = null;
        } else {
            // Cash or any other active (non-khata) method — must at least
            // cover the total. Anything handed over above the total is
            // change: it's given back to the customer, not extra revenue,
            // so paid_amount stays at the total while amount_tendered keeps
            // the raw cash-in-hand figure for the receipt to show.
            $amountTendered = round((float) $data['amount_received'], 2);

            if ($amountTendered + 0.01 < $total) {
                return back()
                    ->withInput()
                    ->with('error', 'Amount received cannot be less than the total amount for this payment method.');
            }

            $amountReceived = $total; // avoid rounding dust leaving a stray balance
        }

        $remaining = round($total - $amountReceived, 2);
        $paymentStatus = $remaining > 0 ? 'unpaid' : 'paid';
        $paymentMethod = $data['payment_method'];

        $sale = DB::transaction(function () use ($data, $resolved, $subtotal, $totalDiscount, $total, $amountReceived, $amountTendered, $remaining, $paymentStatus, $paymentMethod) {
            $sale = Sale::create([
                'invoice_no' => InvoiceNumberGenerator::next('INV'),
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => Auth::id(),
                'sale_date' => $data['sale_date'],
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'total_amount' => $total,
                'paid_amount' => $amountReceived,
                'amount_tendered' => $amountTendered,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
            ]);

            foreach ($resolved as $item) {
                $model = $item['model'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_type' => $item['type'],
                    'item_id' => $model->id,
                    'item_name' => $model->name,
                    'unit' => $model->unit,
                    'quantity' => $item['qty'],
                    'unit_price' => $model->selling_price,
                    'discount' => $item['discount'],
                    'subtotal' => $item['subtotal'],
                ]);

                $model->decrement('stock_qty', $item['qty']);
            }

            if ($remaining > 0) {
                CustomerTransaction::create([
                    'customer_id' => $data['customer_id'],
                    'sale_id'     => $sale->id, // ← added
                    'type'        => 'khata',
                    'amount'      => $remaining,
                    'invoice_no'  => $sale->invoice_no,
                    'notes'       => $amountReceived > 0 ? 'Partial sale on khata' : 'Sale on khata',
                    'transaction_date' => $data['sale_date'],
                ]);
            }

            return $sale;
        });

        if ($data['action'] === 'save_print') {
            return redirect()
                ->to(route('sales.show', $sale) . '?print=1')
                ->with('success', "Sale recorded: {$sale->invoice_no}");
        }

        return redirect()
            ->route('sales.pos')
            ->with('success', "Sale recorded: {$sale->invoice_no}");
    }

    public function history(Request $request)
    {
        $sales = Sale::with('customer')
            ->when(
                $request->search,
                fn ($q) => $q->where(
                    'invoice_no',
                    'like',
                    "%{$request->search}%"
                )
            )
            ->when(
                $request->from,
                fn ($q) => $q->whereDate(
                    'sale_date',
                    '>=',
                    $request->from
                )
            )
            ->when(
                $request->to,
                fn ($q) => $q->whereDate(
                    'sale_date',
                    '<=',
                    $request->to
                )
            )
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

    public function exportCsv(Request $request)
    {
        $sales = Sale::with('customer')
            ->when(
                !empty($request->get('search')),
                fn ($q) => $q->where(
                    'invoice_no',
                    'like',
                    "%{$request->get('search')}%"
                )
            )
            ->when(
                !empty($request->get('from')),
                fn ($q) => $q->whereDate(
                    'sale_date',
                    '>=',
                    $request->get('from')
                )
            )
            ->when(
                !empty($request->get('to')),
                fn ($q) => $q->whereDate(
                    'sale_date',
                    '<=',
                    $request->get('to')
                )
            )
            ->latest('sale_date')
            ->latest('id')
            ->get();

        $filename = 'sales-history-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Invoice', 'Customer', 'Date', 'Payment Method', 'Status', 'Amount']);

            foreach ($sales as $sale) {
                fputcsv($file, [
                    $sale->invoice_no,
                    $sale->customer->name ?? 'Walk-in Customer',
                    $sale->sale_date->format('M j, Y'),
                    $sale->payment_method,
                    $sale->payment_status,
                    number_format($sale->total_amount, 0),
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}