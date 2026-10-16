<?php

namespace App\Http\Controllers;

use App\Models\MilkCollection;
use App\Models\PaymentMethod;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\InvoiceNumberGenerator;

class MilkCollectionController extends Controller
{
    /** Largest value that fits in a decimal(12,2) column. */
    private const MAX_AMOUNT = '9999999999.99';

    public function index(Request $request)
    {
        $collections = MilkCollection::with('supplier', 'rawMaterial')
            ->when(
                $request->filled('from'),
                fn ($q) => $q->whereDate('collected_at', '>=', $request->from)
            )
            ->when(
                $request->filled('to'),
                fn ($q) => $q->whereDate('collected_at', '<=', $request->to)
            )
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $todayQty = MilkCollection::whereHas('rawMaterial', fn ($q) => $q->where('is_milk', true))
            ->whereDate('collected_at', now()->toDateString())
            ->sum('quantity');

        $monthQty = MilkCollection::whereHas('rawMaterial', fn ($q) => $q->where('is_milk', true))
            ->whereMonth('collected_at', now()->month)
            ->whereYear('collected_at', now()->year)
            ->sum('quantity');

        $monthSpend = MilkCollection::whereHas('rawMaterial', fn ($q) => $q->where('is_milk', true))
            ->whereMonth('collected_at', now()->month)
            ->whereYear('collected_at', now()->year)
            ->sum('total_amount');

        // Suppliers no longer have an is_active column.
        $suppliers = Supplier::orderBy('name')
            ->get()
            ->map(fn ($supplier) => [
                'id' => $supplier->id,
                'label' => $supplier->name,
                'sublabel' => $supplier->phone,
            ]);

        $rawMaterials = RawMaterial::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($rawMaterial) => [
                'id' => $rawMaterial->id,
                'label' => $rawMaterial->name,
                'sublabel' => $rawMaterial->unit,
                'unit' => $rawMaterial->unit,
                'is_milk' => (bool) $rawMaterial->is_milk,
            ]);

        $paymentMethods = PaymentMethod::active()->ordered()->get();

        return view('milk-collections.index', compact(
            'collections',
            'todayQty',
            'monthQty',
            'monthSpend',
            'suppliers',
            'rawMaterials',
            'paymentMethods'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'raw_material_id' => [
                'required',
                Rule::exists('raw_materials', 'id')->where('is_active', true),
            ],
            'quantity' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:' . self::MAX_AMOUNT],
            // A collection with no purchase cost is not a valid purchase. It
            // would create a zero total and could incorrectly pass payment
            // validation when cash given is also zero.
            'purchase_price' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:' . self::MAX_AMOUNT],
            'fat' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'snf' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'shift' => ['required', Rule::in(MilkCollection::SHIFTS)],
            'paid_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:' . self::MAX_AMOUNT],
            // Only ACTIVE payment methods are accepted, matched by their unique code.
            'payment_method' => ['required', 'string', Rule::exists('payment_methods', 'code')->where('is_active', true)],
            'notes' => 'nullable|string|max:500',
            'print' => 'nullable|boolean',
        ], [
            'payment_method.exists' => 'The selected payment method is not available.',
            'paid_amount.required' => 'Cash given is required. Enter 0 for Khata.',
            'paid_amount.min' => 'Cash given cannot be negative.',
            'paid_amount.decimal' => 'Cash given can have at most 2 decimal places.',
            'quantity.decimal' => 'Quantity can have at most 2 decimal places.',
            'purchase_price.decimal' => 'Purchase price can have at most 2 decimal places.',
        ], [
            'supplier_id' => 'Supplier',
            'raw_material_id' => 'Raw Material',
            'quantity' => 'Quantity',
            'purchase_price' => 'Purchase Price',
            'paid_amount' => 'Cash Given',
            'payment_method' => 'Payment Method',
        ]);

        $rawMaterial = RawMaterial::findOrFail($data['raw_material_id']);

        // Money is compared in whole cents so there is no floating-point drift.
        $totalAmount = round((float) $data['quantity'] * (float) $data['purchase_price'], 2);

        if ($totalAmount <= 0) {
            return back()->withInput()->withErrors([
                'purchase_price' => 'Purchase price must produce a total greater than 0.',
            ]);
        }

        if ($totalAmount > (float) self::MAX_AMOUNT) {
            return back()->withInput()->withErrors([
                'purchase_price' => 'The total amount is too large.',
            ]);
        }

        $totalCents = (int) round($totalAmount * 100);
        $submittedCents = (int) round((float) $data['paid_amount'] * 100);
        $submittedPaidAmount = $submittedCents / 100;

        $isKhata = $data['payment_method'] === PaymentMethod::KHATA_CODE;
        $isPartialKhata = $data['payment_method'] === PaymentMethod::PARTIAL_KHATA_CODE;

        $reject = fn (string $message) => back()->withInput()->withErrors(['paid_amount' => $message]);

        if ($isKhata) {
            // Khata: nothing is paid now.
            if ($submittedCents !== 0) {
                return $reject('Cash given must be 0 for Khata.');
            }

            $paidAmount = 0.0;
            $amountGiven = null;
            $returnAmount = 0.0;
        } elseif ($isPartialKhata) {
            // Partial Khata: more than 0 and strictly less than the total.
            if ($submittedCents <= 0) {
                return $reject('Cash given must be greater than 0 for Partial Khata. If nothing is being paid, select Khata instead.');
            }

            if ($submittedCents >= $totalCents) {
                return $reject('Cash given must be less than the total amount for Partial Khata. If the full amount is being paid, select Cash (or another payment method) instead.');
            }

            $paidAmount = $submittedPaidAmount;
            // Partial Khata still includes an immediate cash payment. Keep it
            // separately from paid_amount so history and receipts can show the
            // actual cash handed over.
            $amountGiven = $submittedPaidAmount;
            $returnAmount = 0.0;
        } else {
            // Cash or any other active method — must cover the whole bill.
            // amount_given keeps the real cash handed over; paid_amount stays at
            // the total so balanceDue() and Khata logic never see a false credit;
            // return_amount is the change given back.
            if ($submittedCents < $totalCents) {
                return $reject('Cash given cannot be less than the total amount for this payment method.');
            }

            $amountGiven = $submittedPaidAmount;
            $paidAmount = $totalAmount;
            $returnAmount = round($amountGiven - $totalAmount, 2);
        }

        $paymentMethodName = PaymentMethod::where('code', $data['payment_method'])->value('name') ?? ucfirst($data['payment_method']);

        // Fat / SNF / Total Solids are only kept when the raw material is milk.
        // This blocks a bypassed frontend from attaching milk-quality data to eggs etc.
        // All three are optional, even for milk.
        $isMilk = (bool) $rawMaterial->is_milk;
        $fat = $isMilk ? ($data['fat'] ?? null) : null;
        $snf = $isMilk ? ($data['snf'] ?? null) : null;
        $totalSolids = ($fat !== null && $snf !== null) ? round($fat + $snf, 2) : null;

        $collection = DB::transaction(function () use ($data, $rawMaterial, $totalAmount, $paidAmount, $amountGiven, $returnAmount, $fat, $snf, $totalSolids, $paymentMethodName) {

            $purchaseInvoiceNo = InvoiceNumberGenerator::next('SUP');

            $collection = MilkCollection::create([
                'supplier_id'      => $data['supplier_id'],
                'raw_material_id'  => $rawMaterial->id,
                'invoice_no'       => $purchaseInvoiceNo,
                'quantity'         => $data['quantity'],
                'unit'             => $rawMaterial->unit,
                'purchase_price'   => $data['purchase_price'],
                'total_amount'     => $totalAmount,
                'paid_amount'      => $paidAmount,
                'amount_given'     => $amountGiven,
                'return_amount'    => $returnAmount,
                'payment_method'   => $data['payment_method'],
                'fat'              => $fat,
                'snf'              => $snf,
                'total_solids'     => $totalSolids,
                'shift'            => $data['shift'],
                'collected_at'     => now(),
                'notes'            => $data['notes'] ?? null,
            ]);

            // Inventory: stock goes up by the collected quantity.
            // (raw_materials has no cost_per_unit column — cost comes from
            // RawMaterial::currentCostPerUnit(), calculated from collections.)
            $rawMaterial->increment('stock_qty', $data['quantity']);

            $remaining = round($totalAmount - $paidAmount, 2);

            if ($remaining > 0) {
                SupplierTransaction::create([
                    'supplier_id'        => $data['supplier_id'],
                    'milk_collection_id' => $collection->id,
                    'type'               => 'purchase',
                    'amount'             => $remaining,
                    'invoice_no'         => $purchaseInvoiceNo,
                    'reference'          => 'Milk Collection #' . $collection->id,
                    'notes'              => $rawMaterial->name . ' - ' . $data['quantity'] . ' ' . $rawMaterial->unit,
                    'transaction_date'   => now()->toDateString(),
                ]);
            }

            return $collection;
        });

        if ($request->boolean('print')) {
            return redirect()->route('milk-collections.receipt', $collection);
        }

        return back()->with(
            'success',
            'Milk collection recorded and raw material stock updated.'
        );
    }

    public function receipt(MilkCollection $milkCollection)
    {
        $milkCollection->load('supplier', 'rawMaterial');
        $settings = \App\Models\Setting::first() ?? (object) ['currency' => 'Rs.', 'dairy_name' => 'DairyPro'];

        return view('milk-collections.receipt', compact('milkCollection', 'settings'));
    }

    public function exportCsv(Request $request)
    {
        $collections = MilkCollection::with('supplier', 'rawMaterial')
            ->when(
                !empty($request->get('from')),
                fn ($q) => $q->whereDate('collected_at', '>=', $request->get('from'))
            )
            ->when(
                !empty($request->get('to')),
                fn ($q) => $q->whereDate('collected_at', '<=', $request->get('to'))
            )
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->get();

        $filename = 'milk-collections-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($collections) {
            $file = fopen('php://output', 'w');
            // Tell Excel and other spreadsheet applications that this is UTF-8.
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Supplier', 'Raw Material', 'Date & Time', 'Shift', 'Quantity', 'Fat (%)', 'SNF (%)', 'TS (%)', 'Paid', 'Total']);

            foreach ($collections as $collection) {
                // Keep quality columns as plain numeric strings so Excel can
                // read them as numbers. Empty means the value was not recorded.
                $fat = $collection->fat !== null ? number_format((float) $collection->fat, 2, '.', '') : '';
                $snf = $collection->snf !== null ? number_format((float) $collection->snf, 2, '.', '') : '';
                $ts  = $collection->total_solids !== null ? number_format((float) $collection->total_solids, 2, '.', '') : '';

                fputcsv($file, [
                    $collection->supplier->name ?? 'Unknown',
                    $collection->rawMaterial->name ?? 'Unknown',
                    $collection->collected_at->format('M j, Y g:i A'),
                    ucfirst($collection->shift),
                    number_format($collection->quantity, 2),
                    $fat,
                    $snf,
                    $ts,
                    number_format($collection->paid_amount, 0),
                    number_format($collection->total_amount, 0),
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
