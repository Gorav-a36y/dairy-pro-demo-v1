<?php

namespace App\Http\Controllers;

use App\Models\MilkCollection;
use App\Models\PaymentMethod;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\InvoiceNumberGenerator;

class MilkCollectionController extends Controller
{
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

        $suppliers = Supplier::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($supplier) => [
                'id' => $supplier->id,
                'label' => $supplier->name,
                'sublabel' => $supplier->phone,
            ]);

        $rawMaterials = RawMaterial::orderBy('name')
            ->get()
            ->map(fn ($rawMaterial) => [
                'id' => $rawMaterial->id,
                'label' => $rawMaterial->name,
                'sublabel' => $rawMaterial->unit,
                'unit' => $rawMaterial->unit,
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
            'raw_material_id' => 'required|exists:raw_materials,id',
            'quantity' => 'required|numeric|min:0.01',
            'purchase_price' => 'required|numeric|min:0',
            'fat' => 'nullable|numeric|min:0|max:100',
            'snf' => 'nullable|numeric|min:0|max:100',
            'shift' => 'required|in:' . implode(',', MilkCollection::SHIFTS),
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:' . implode(',', PaymentMethod::activeCodes()),
            'notes' => 'nullable|string|max:500',
            'print' => 'nullable|boolean',
        ], [], [
            'supplier_id' => 'Supplier',
            'raw_material_id' => 'Raw Material',
            'quantity' => 'Quantity',
            'purchase_price' => 'Purchase Price',
            'paid_amount' => 'Paid Amount',
            'payment_method' => 'Payment Method',
        ]);

        $rawMaterial = RawMaterial::findOrFail($data['raw_material_id']);

        $totalAmount = round($data['quantity'] * $data['purchase_price'], 2);
        $submittedPaidAmount = round($data['paid_amount'] ?? 0, 2);

        $isKhata = $data['payment_method'] === PaymentMethod::KHATA_CODE;
        $isPartialKhata = $data['payment_method'] === PaymentMethod::PARTIAL_KHATA_CODE;

        if ($isKhata) {
            $paidAmount = 0.0;
            $amountGiven = null;
            $returnAmount = 0.0;
        } elseif ($isPartialKhata) {
            if ($submittedPaidAmount > $totalAmount) {
                return back()->withInput()->withErrors([
                    'paid_amount' => 'Paid amount cannot be greater than the total amount.',
                ]);
            }

            if ($submittedPaidAmount >= $totalAmount) {
                return back()->withInput()->withErrors([
                    'paid_amount' => 'Paid amount cannot equal the total amount for Partial Khata. If the full amount is being paid, select Cash (or another payment method) instead.',
                ]);
            }

            $paidAmount = $submittedPaidAmount;
            $amountGiven = null;
            $returnAmount = 0.0;
        } else {
            // Cash or any other active method — must at least cover the bill.
            // amount_given keeps the real cash handed over; paid_amount stays at
            // the total so balanceDue() and Khata logic never see a false credit;
            // return_amount is the change given back.
            if ($submittedPaidAmount + 0.01 < $totalAmount) {
                return back()->withInput()->withErrors([
                    'paid_amount' => 'Amount given cannot be less than the total amount for this payment method.',
                ]);
            }

            $amountGiven = $submittedPaidAmount;
            $paidAmount = $totalAmount;
            $returnAmount = round($amountGiven - $totalAmount, 2);
        }

        $paymentMethodName = PaymentMethod::where('code', $data['payment_method'])->value('name') ?? ucfirst($data['payment_method']);

        // Only trust fat/snf if the raw material is actually a liquid unit —
        // this blocks a bypassed frontend from attaching fake milk-quality data to eggs etc.
        $isLiquid = MilkCollection::isLiquidUnit($rawMaterial->unit);
        $fat = $isLiquid ? ($data['fat'] ?? null) : null;
        $snf = $isLiquid ? ($data['snf'] ?? null) : null;
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

            $rawMaterial->increment('stock_qty', $data['quantity']);
            $rawMaterial->update(['cost_per_unit' => $data['purchase_price']]);

            $remaining = round($totalAmount - $paidAmount, 2);

            if ($remaining > 0) {
                SupplierTransaction::create([
                    'supplier_id'        => $data['supplier_id'],
                    'milk_collection_id' => $collection->id,
                    'type'               => 'purchase',
                    'amount'             => $remaining,
                    'invoice_no'         => $purchaseInvoiceNo,
                    'reference'          => 'Milk Collection #' . $collection->id,
                    'notes'              => $rawMaterial->name . ' — ' . $data['quantity'] . ' ' . $rawMaterial->unit,
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
        fputcsv($file, ['Supplier', 'Raw Material', 'Date & Time', 'Shift', 'Quantity', 'Fat (%)', 'SNF (%)', 'TS (%)', 'Paid', 'Total']);

        foreach ($collections as $collection) {
            $fat = $collection->fat !== null ? number_format($collection->fat, 2) : '—';
            $snf = $collection->snf !== null ? number_format($collection->snf, 2) : '—';
            $ts  = $collection->total_solids !== null ? number_format($collection->total_solids, 2) : '—';

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