<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CustomerTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $activePaymentCodes = $paymentMethods->pluck('code')->all();
        $defaultPaymentMethod = $paymentMethods->firstWhere('code', 'cash')?->code
            ?? $paymentMethods->first()?->code;
        // Restore the cashier's cart and selections after a validation failure.
        $oldItems = collect(old('items', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => [
                'key' => (string) ($row['item_key'] ?? ''),
                'quantity' => (float) ($row['quantity'] ?? 1),
                'discount' => (float) ($row['discount'] ?? 0),
            ])
            ->filter(fn ($item) => $item['key'] !== '')
            ->values();
        $customerIds = $customers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $oldCustomerId = old('customer_id');
        $selectedCustomerId = $oldCustomerId !== null
            && in_array((string) $oldCustomerId, $customerIds, true)
                ? (string) $oldCustomerId
                : '';
        $oldPaymentMethod = old('payment_method');
        $selectedPaymentMethod = in_array($oldPaymentMethod, $activePaymentCodes, true)
            ? $oldPaymentMethod
            : $defaultPaymentMethod;
        $oldAmountReceived = old('amount_received');
        $hasOldAmountReceived = $oldAmountReceived !== null;

        // A fresh one-time token for every screen load (never reused from old input).
        $submitToken = (string) Str::uuid();

        // no-store: the Back button can never show an old POS page with an
        // already-used token; the browser loads a fresh one (empty cart, new token).
        return response()->view('sales.pos', compact(
            'customers',
            'sellableItems',
            'paymentMethods',
            'oldItems',
            'selectedCustomerId',
            'selectedPaymentMethod',
            'oldAmountReceived',
            'hasOldAmountReceived',
            'submitToken'
        ))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'submit_token' => 'required|uuid',
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'required|in:' . implode(',', PaymentMethod::activeCodes()),
            'amount_received' => 'required|numeric|min:0|max:99999999.99',
            'action' => 'required|in:save,save_print',
            'items' => 'required|array|min:1|max:200',
            'items.*.item_key' => 'required|string|max:50',
            'items.*.quantity' => 'required|numeric|min:0.01|max:9999999.99',
            'items.*.discount' => 'nullable|numeric|min:0|max:99999999.99',
        ], [], [
            'customer_id' => 'Customer',
            'payment_method' => 'Payment Method',
            'amount_received' => 'Amount Received',
        ]);

        // Same screen already saved this sale (double click, double Enter,
        // retry, Back button + resubmit)? Do NOT create another one — just
        // take the cashier to the sale that already exists.
        $alreadySaved = Sale::where('submit_token', $data['submit_token'])->first();
        if ($alreadySaved) {
            return $this->redirectAfterSave($alreadySaved, $data['action'], true);
        }

        // The sale date is always today on the server, in the timezone chosen
        // in Settings. It is not taken from the browser.
        $saleDate = $this->shopToday();

        $resolved = [];
        $requested = []; // total quantity per item (same item on two rows adds up)
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
            // Whole quantity is stored with 2 decimals, so round it first.
            $qty = round((float) $row['quantity'], 2);
            $key = "{$type}:{$model->id}";
            $requested[$key] = round(($requested[$key] ?? 0) + $qty, 2);
            if ($requested[$key] > (float) $model->stock_qty) {
                return back()
                    ->withInput()
                    ->with(
                        'error',
                        "Not enough stock for {$model->name}. Available: {$model->stock_qty} {$model->unit}."
                    );
            }
            $lineGross = round($qty * (float) $model->selling_price, 2);
            $lineDiscount = round((float) ($row['discount'] ?? 0), 2);
            if ($lineGross <= 0) {
                return back()
                    ->withInput()
                    ->with('error', "The sale amount for {$model->name} must be greater than zero.");
            }
            if ($lineDiscount >= $lineGross) {
                return back()
                    ->withInput()
                    ->with('error', "Discount for {$model->name} must be less than its line amount.");
            }
            $lineSubtotal = round($lineGross - $lineDiscount, 2);
            $subtotal = round($subtotal + $lineGross, 2);
            $totalDiscount = round($totalDiscount + $lineDiscount, 2);
            $resolved[] = [
                'type' => $type,
                'model' => $model,
                'qty' => $qty,
                'discount' => $lineDiscount,
                'subtotal' => $lineSubtotal,
            ];
        }
        $total = round($subtotal - $totalDiscount, 2);
        // Defense in depth: a sale must never be saved with a zero or negative total.
        if ($total <= 0 || $totalDiscount >= $subtotal) {
            return back()
                ->withInput()
                ->with('error', 'The sale total must be greater than zero. Reduce the discount or check the item prices.');
        }
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
            $paidAmount = 0.0;
            $cashReceived = null;
        } elseif ($isPartialKhata) {
            $paidAmount = round((float) $data['amount_received'], 2);
            if ($paidAmount <= 0) {
                return back()
                    ->withInput()
                    ->with('error', 'Partial Khata requires a paid amount greater than zero. For a fully unpaid sale, select Khata.');
            }
            if ($paidAmount >= $total) {
                return back()
                    ->withInput()
                    ->with('error', 'Paid amount must be less than the total for Partial Khata. If the full amount is being paid, select Cash (or another payment method) instead.');
            }
            $cashReceived = null;
        } else {
            // Cash or any other active (non-khata) method — must at least
            // cover the total. Anything handed over above the total is
            // change: it's given back to the customer, not extra revenue,
            // so paid_amount stays at the total while amount_received keeps
            // the raw cash-in-hand figure for the receipt to show.
            $cashReceived = round((float) $data['amount_received'], 2);
            if ($cashReceived < $total) {
                return back()
                    ->withInput()
                    ->with('error', 'Amount received cannot be less than the total amount for this payment method.');
            }
            $paidAmount = $total; // avoid rounding dust leaving a stray balance
        }
        $remaining = round($total - $paidAmount, 2);
        $paymentMethod = $data['payment_method'];
        $submitToken = $data['submit_token'];
        $duplicate = false;

        try {
            $sale = DB::transaction(function () use ($data, $resolved, $requested, $subtotal, $totalDiscount, $total, $paidAmount, $cashReceived, $remaining, $paymentMethod, $saleDate, $submitToken, &$duplicate) {
                // Lock every item and check stock again. Another sale may have
                // used the stock after the check above.
                $locked = [];
                foreach ($resolved as $item) {
                    $key = $item['type'] . ':' . $item['model']->id;
                    if (! isset($locked[$key])) {
                        $locked[$key] = $item['model']->newQuery()
                            ->whereKey($item['model']->id)
                            ->lockForUpdate()
                            ->first();
                    }
                }

                // Two identical requests at the same moment queue up on the
                // locks above. When the second one gets here, the first has
                // already saved — so return that sale instead of failing on
                // stock or creating a copy.
                $existing = Sale::where('submit_token', $submitToken)->first();
                if ($existing) {
                    $duplicate = true;
                    return $existing;
                }

                foreach ($requested as $key => $needed) {
                    $fresh = $locked[$key] ?? null;
                    if (! $fresh || ! $fresh->is_active) {
                        throw ValidationException::withMessages([
                            'items' => 'One of the selected items is invalid or no longer exists.',
                        ]);
                    }
                    if ($needed > (float) $fresh->stock_qty) {
                        throw ValidationException::withMessages([
                            'items' => "Not enough stock for {$fresh->name}. Available: {$fresh->stock_qty} {$fresh->unit}.",
                        ]);
                    }
                }

                $sale = new Sale([
                    'invoice_no' => InvoiceNumberGenerator::next('INV'),
                    'customer_id' => $data['customer_id'] ?? null,
                    'user_id' => Auth::id(),
                    'sale_date' => $saleDate,
                    'subtotal' => $subtotal,
                    'discount' => $totalDiscount,
                    'total_amount' => $total,
                    'paid_amount' => $paidAmount,
                    'amount_received' => $cashReceived,
                    'payment_method' => $paymentMethod,
                ]);
                // Set directly so you don't have to add it to Sale::$fillable.
                $sale->submit_token = $submitToken;
                $sale->save();

                foreach ($resolved as $item) {
                    $model = $item['model'];
                    $key = $item['type'] . ':' . $model->id;
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
                    $locked[$key]->decrement('stock_qty', $item['qty']);
                }
                if ($remaining > 0) {
                    CustomerTransaction::create([
                        'customer_id' => $data['customer_id'],
                        'sale_id'     => $sale->id,
                        'type'        => CustomerTransaction::TYPE_KHATA,
                        'amount'      => $remaining,
                        'invoice_no'  => $sale->invoice_no,
                        'notes'       => $paidAmount > 0 ? 'Partial sale on khata' : 'Sale on khata',
                        'transaction_date' => $saleDate,
                    ]);
                }
                return $sale;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Last safety net: the database itself refused a second sale with
            // the same token. Show the sale that was saved first.
            $sale = Sale::where('submit_token', $submitToken)->first();
            if (! $sale) {
                throw $e; // a different unique rule failed (e.g. invoice number) — don't hide it
            }
            $duplicate = true;
        }

        return $this->redirectAfterSave($sale, $data['action'], $duplicate);
    }

    /**
     * Today's date (Y-m-d) in the timezone saved in Settings — the same
     * $settings the layout shares with every view (the POS clock uses it too).
     * Falls back to the app timezone only if Settings is not available.
     */
    private function shopToday(): string
    {
        $settings = view()->shared('settings');

        return ($settings ?: \App\Models\Setting::current())->now()->format('Y-m-d');
    }

    /**
     * Same destination for a new sale and for a repeated click, so
     * "Save & Print" still opens the print view even if the second
     * request is the one the browser ends up showing.
     */
    private function redirectAfterSave(Sale $sale, string $action, bool $duplicate = false)
    {
        $message = $duplicate
            ? "Sale already recorded: {$sale->invoice_no}. The repeated click was ignored."
            : "Sale recorded: {$sale->invoice_no}";

        if ($action === 'save_print') {
            return redirect()
                ->to(route('sales.show', $sale) . '?print=1')
                ->with('success', $message);
        }

        return redirect()
            ->route('sales.pos')
            ->with('success', $message);
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
        $filename = 'sales-history-' . \App\Models\Setting::current()->now()->format('Y-m-d-H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];
        // Show the payment method's name ("Partial Khata"), not its code ("partial_khata").
        $methodNames = PaymentMethod::pluck('name', 'code')->all();

        $callback = function () use ($sales, $methodNames) {
            $file = fopen('php://output', 'w');
            // BOM: lets Excel read Urdu / any UTF-8 text (customer names) correctly
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Invoice', 'Customer', 'Date', 'Payment Method', 'Amount']);
            foreach ($sales as $sale) {
                fputcsv($file, [
                    $this->csvSafe($sale->invoice_no),
                    $this->csvSafe($sale->customer->name ?? 'Walk-in Customer'),
                    $sale->sale_date->format('M j, Y'),
                    $this->csvSafe($methodNames[$sale->payment_method] ?? $sale->payment_method),
                    // plain number with 2 decimals (no thousands comma, no lost paisa)
                    number_format((float) $sale->total_amount, 2, '.', ''),
                ]);
            }
            fclose($file);
        };
        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Stop "CSV injection": a customer named =HYPERLINK(...) would run as a
     * formula when the file is opened in Excel. A leading ' makes it plain text.
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
