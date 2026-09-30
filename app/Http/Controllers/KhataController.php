<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KhataController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'customer');

        $customers = Customer::orderBy('name')
            ->select('customers.*')
            ->selectRaw('(
                SELECT COALESCE(SUM(amount),0) FROM customer_transactions
                WHERE customer_transactions.customer_id = customers.id AND type = ?
            ) as total_khata', ['khata'])
            ->selectRaw('(
                SELECT COALESCE(SUM(amount),0) FROM customer_transactions
                WHERE customer_transactions.customer_id = customers.id AND type = ?
            ) as total_payment', ['payment'])
            ->get()
            ->map(function ($c) {
                $c->balance = round((float) $c->total_khata - (float) $c->total_payment, 2);
                return $c;
            });

        $suppliers = Supplier::orderBy('name')
            ->select('suppliers.*')
            ->selectRaw('(
                SELECT COALESCE(SUM(amount),0) FROM supplier_transactions
                WHERE supplier_transactions.supplier_id = suppliers.id AND type = ?
            ) as total_purchase', ['purchase'])
            ->selectRaw('(
                SELECT COALESCE(SUM(amount),0) FROM supplier_transactions
                WHERE supplier_transactions.supplier_id = suppliers.id AND type = ?
            ) as total_payment', ['payment'])
            ->get()
            ->map(function ($s) {
                $s->balance = round((float) $s->total_purchase - (float) $s->total_payment, 2);
                return $s;
            });

        $selectedCustomer = null;
        $selectedSupplier = null;

        if ($tab === 'customer' && $request->id) {
            $selectedCustomer = Customer::with(['transactions' => function ($q) use ($request) {
                $q->when(
                        $request->filled('from'),
                        fn ($qq) => $qq->whereDate('transaction_date', '>=', $request->from)
                    )
                    ->when(
                        $request->filled('to'),
                        fn ($qq) => $qq->whereDate('transaction_date', '<=', $request->to)
                    )
                    ->when(
                        $request->filled('invoice'),
                        fn ($qq) => $qq->where('invoice_no', 'like', '%' . $request->invoice . '%')
                    )
                    ->orderBy('transaction_date', 'asc')
                    ->orderBy('id', 'asc');
            }])->find($request->id);

            if ($selectedCustomer) {
                $selectedCustomer->balance = $customers->firstWhere('id', $selectedCustomer->id)->balance ?? 0;
            }
        } elseif ($tab === 'supplier' && $request->id) {
            $selectedSupplier = Supplier::with(['transactions' => function ($q) use ($request) {
                $q->when(
                        $request->filled('from'),
                        fn ($qq) => $qq->whereDate('transaction_date', '>=', $request->from)
                    )
                    ->when(
                        $request->filled('to'),
                        fn ($qq) => $qq->whereDate('transaction_date', '<=', $request->to)
                    )
                    ->when(
                        $request->filled('invoice'),
                        fn ($qq) => $qq->where('invoice_no', 'like', '%' . $request->invoice . '%')
                    )
                    ->orderBy('transaction_date', 'asc')
                    ->orderBy('id', 'asc');
            }])->find($request->id);

            if ($selectedSupplier) {
                $selectedSupplier->balance = $suppliers->firstWhere('id', $selectedSupplier->id)->balance ?? 0;
            }
        }

        return view('khata.index', compact(
            'tab', 'customers', 'suppliers', 'selectedCustomer', 'selectedSupplier'
        ));
    }

    /* ───────────────────── Customer Payment ───────────────────── */
    public function storeCustomerTransaction(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount'      => 'required|numeric|min:0.01',
            'notes'       => 'nullable|string|max:500',
            'action'      => 'nullable|in:save,save_print',
        ]);

        return DB::transaction(function () use ($data) {
            // Lock the customer row so two simultaneous payments can't both
            // read "balance is fine" before either one saves.
            $customer = Customer::where('id', $data['customer_id'])->lockForUpdate()->firstOrFail();
            $currentBalance = $customer->currentBalance();

            if (round((float) $data['amount'], 2) > round($currentBalance, 2) + 0.01) {
                return back()
                    ->with('error', 'Customer remaining balance is only ' . number_format($currentBalance, 0) . '. You cannot record a payment of ' . number_format($data['amount'], 0) . '.')
                    ->withInput();
            }

            $transaction = CustomerTransaction::create([
                'customer_id'      => $data['customer_id'],
                'type'             => 'payment',
                'amount'           => $data['amount'],
                'invoice_no'       => InvoiceNumberGenerator::next('CUS'),
                'notes'            => $data['notes'] ?: 'Payment Received',
                'transaction_date' => now()->toDateString(),
            ]);

            if (($data['action'] ?? 'save') !== 'save_print') {
                return redirect()->route('khata.index', ['tab' => 'customer', 'id' => $customer->id])
                    ->with('success', 'Payment recorded.');
            }

            return redirect()->route('khata.customer.receipt', [
                'transaction' => $transaction->id,
                'print'       => 1,
            ]);
        });
    }

    public function customerPaymentReceipt(CustomerTransaction $transaction)
    {
        $transaction->load('customer');

        $newBalance      = $transaction->customer->currentBalance();
        $previousBalance = round($newBalance + (float) $transaction->amount, 2);

        return view('khata.customer-payment-receipt', compact(
            'transaction', 'newBalance', 'previousBalance'
        ));
    }

    /* ───────────────────── Supplier Payment ───────────────────── */
    public function storeSupplierTransaction(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount'      => 'required|numeric|min:0.01',
            'notes'       => 'nullable|string|max:500',
            'action'      => 'nullable|in:save,save_print',
        ]);

        return DB::transaction(function () use ($data) {
            $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();
            $currentBalance = $supplier->currentBalance();

            if (round((float) $data['amount'], 2) > round($currentBalance, 2) + 0.01) {
                return back()
                    ->with('error', 'Supplier remaining balance is only ' . number_format($currentBalance, 0) . '. You cannot record a payment of ' . number_format($data['amount'], 0) . '.')
                    ->withInput();
            }

            $transaction = SupplierTransaction::create([
                'supplier_id'      => $data['supplier_id'],
                'type'             => 'payment',
                'amount'           => $data['amount'],
                'invoice_no'       => InvoiceNumberGenerator::next('SUP'),
                'notes'            => $data['notes'] ?: 'Payment Made',
                'transaction_date' => now()->toDateString(),
            ]);

            if (($data['action'] ?? 'save') !== 'save_print') {
                return redirect()->route('khata.index', ['tab' => 'supplier', 'id' => $supplier->id])
                    ->with('success', 'Payment recorded.');
            }

            return redirect()->route('khata.supplier.receipt', [
                'transaction' => $transaction->id,
                'print'       => 1,
            ]);
        });
    }

    public function supplierPaymentReceipt(SupplierTransaction $transaction)
    {
        $transaction->load('supplier');

        $newBalance      = $transaction->supplier->currentBalance();
        $previousBalance = round($newBalance + (float) $transaction->amount, 2);

        return view('khata.supplier-payment-receipt', compact(
            'transaction', 'newBalance', 'previousBalance'
        ));
    }

    public function exportCsv(Request $request)
    {
        $tab = $request->get('tab', 'customer');
        $id = $request->get('id');
        $from = $request->get('from');
        $to = $request->get('to');
        $invoice = $request->get('invoice');

        if ($tab === 'customer') {
            $entity = Customer::find($id);
            if (!$entity) {
                return redirect()->route('khata.index')->with('error', 'Customer not found.');
            }

            $transactions = $entity->transactions()
                ->when(!empty($from), fn ($q) => $q->whereDate('transaction_date', '>=', $from))
                ->when(!empty($to), fn ($q) => $q->whereDate('transaction_date', '<=', $to))
                ->when(!empty($invoice), fn ($q) => $q->where('invoice_no', 'like', "%{$invoice}%"))
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $filename = 'customer-khata-' . $entity->name . '-' . now()->format('Y-m-d-H-i-s') . '.csv';
        } elseif ($tab === 'supplier') {
            $entity = Supplier::find($id);
            if (!$entity) {
                return redirect()->route('khata.index')->with('error', 'Supplier not found.');
            }

            $transactions = $entity->transactions()
                ->when(!empty($from), fn ($q) => $q->whereDate('transaction_date', '>=', $from))
                ->when(!empty($to), fn ($q) => $q->whereDate('transaction_date', '<=', $to))
                ->when(!empty($invoice), fn ($q) => $q->where('invoice_no', 'like', "%{$invoice}%"))
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $filename = 'supplier-khata-' . $entity->name . '-' . now()->format('Y-m-d-H-i-s') . '.csv';
        } else {
            return redirect()->route('khata.index')->with('error', 'Unknown ledger.');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($transactions, $tab) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Particulars', 'Invoice', 'Note', 'Amount']);

            foreach ($transactions as $tx) {
                if ($tab === 'customer') {
                    $particulars = $tx->type === 'khata' ? 'Credit Sale' : 'Payment Received';
                    $typeIcon = $tx->type === 'khata' ? '+' : '-';
                } else {
                    $particulars = $tx->type === 'purchase' ? 'Milk Purchase' : 'Payment Made';
                    $typeIcon = $tx->type === 'purchase' ? '+' : '-';
                }

                fputcsv($file, [
                    $tx->transaction_date->format('M j, Y'),
                    $particulars,
                    $tx->invoice_no ?? '',
                    $tx->notes ?? '',
                    $typeIcon . ' ' . number_format($tx->amount, 0),
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}