<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        // A fresh one-time token for every screen load. The payment form sends
        // it back and the database accepts each token only once, so a double
        // click / double Enter / retry can never record the payment twice.
        $submitToken = (string) Str::uuid();

        // no-store: the Back button can never show an old page with a used token.
        return response()->view('khata.index', compact(
            'tab', 'customers', 'suppliers', 'selectedCustomer', 'selectedSupplier', 'submitToken'
        ))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /* ───────────────────── Customer Payment ───────────────────── */
    public function storeCustomerTransaction(Request $request)
    {
        $data = $request->validate([
            'submit_token' => 'required|uuid',
            'customer_id'  => 'required|exists:customers,id',
            'amount'       => 'required|numeric|min:0.01|max:99999999.99',
            'notes'        => 'nullable|string|max:500',
            'action'       => 'nullable|in:save,save_print',
        ]);

        $action = $data['action'] ?? 'save';
        $amount = round((float) $data['amount'], 2);

        // This screen already saved this payment (double click, retry, Back +
        // resubmit)? Do NOT record it again — just show the one that exists.
        $already = CustomerTransaction::where('submit_token', $data['submit_token'])->first();
        if ($already) {
            return $this->customerPaymentRedirect($already, $action, true);
        }

        try {
            return DB::transaction(function () use ($data, $action, $amount) {
                // Lock the customer row so two simultaneous payments can't both
                // read "balance is fine" before either one saves.
                $customer = Customer::where('id', $data['customer_id'])->lockForUpdate()->firstOrFail();

                // Two identical requests queue up on the lock above. The second
                // one gets here after the first has saved — return that payment.
                $existing = CustomerTransaction::where('submit_token', $data['submit_token'])->first();
                if ($existing) {
                    return $this->customerPaymentRedirect($existing, $action, true);
                }

                $currentBalance = $customer->currentBalance();

                if ($amount > round($currentBalance, 2) + 0.01) {
                    return back()
                        ->with('error', 'Customer remaining balance is only ' . $this->money($currentBalance) . '. You cannot record a payment of ' . $this->money($amount) . '.')
                        ->withInput();
                }

                $transaction = new CustomerTransaction([
                    'customer_id'      => $data['customer_id'],
                    'type'             => CustomerTransaction::TYPE_PAYMENT,
                    'amount'           => $amount,
                    'invoice_no'       => InvoiceNumberGenerator::next('CUS'),
                    'notes'            => ($data['notes'] ?? null) ?: 'Payment Received',
                    'transaction_date' => $this->shopToday(),
                ]);
                // Set directly so submit_token does not need to be in $fillable.
                $transaction->submit_token = $data['submit_token'];
                $transaction->save();

                return $this->customerPaymentRedirect($transaction, $action);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Last safety net: the database refused a second payment with the same token.
            $transaction = CustomerTransaction::where('submit_token', $data['submit_token'])->first();
            if (! $transaction) {
                throw $e; // a different unique rule failed — don't hide it
            }

            return $this->customerPaymentRedirect($transaction, $action, true);
        }
    }

    private function customerPaymentRedirect(CustomerTransaction $transaction, string $action, bool $duplicate = false)
    {
        if ($action === 'save_print') {
            return redirect()->route('khata.customer.receipt', [
                'transaction' => $transaction->id,
                'print'       => 1,
            ]);
        }

        return redirect()->route('khata.index', ['tab' => 'customer', 'id' => $transaction->customer_id])
            ->with('success', $duplicate ? 'Payment already recorded. The repeated click was ignored.' : 'Payment recorded.');
    }

    public function customerPaymentReceipt(CustomerTransaction $transaction)
    {
        $transaction->load('customer');

        // Balance as it was right after THIS payment (not today's balance),
        // so an old receipt prints the same numbers every time.
        $newBalance      = $this->customerBalanceAsOf($transaction);
        $previousBalance = round($newBalance + (float) $transaction->amount, 2);

        return view('khata.customer-payment-receipt', compact(
            'transaction', 'newBalance', 'previousBalance'
        ));
    }

    /* ───────────────────── Supplier Payment ───────────────────── */
    public function storeSupplierTransaction(Request $request)
    {
        $data = $request->validate([
            'submit_token' => 'required|uuid',
            'supplier_id'  => 'required|exists:suppliers,id',
            'amount'       => 'required|numeric|min:0.01|max:99999999.99',
            'notes'        => 'nullable|string|max:500',
            'action'       => 'nullable|in:save,save_print',
        ]);

        $action = $data['action'] ?? 'save';
        $amount = round((float) $data['amount'], 2);

        $already = SupplierTransaction::where('submit_token', $data['submit_token'])->first();
        if ($already) {
            return $this->supplierPaymentRedirect($already, $action, true);
        }

        try {
            return DB::transaction(function () use ($data, $action, $amount) {
                $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();

                $existing = SupplierTransaction::where('submit_token', $data['submit_token'])->first();
                if ($existing) {
                    return $this->supplierPaymentRedirect($existing, $action, true);
                }

                $currentBalance = $supplier->currentBalance();

                if ($amount > round($currentBalance, 2) + 0.01) {
                    return back()
                        ->with('error', 'Supplier remaining balance is only ' . $this->money($currentBalance) . '. You cannot record a payment of ' . $this->money($amount) . '.')
                        ->withInput();
                }

                $transaction = new SupplierTransaction([
                    'supplier_id'      => $data['supplier_id'],
                    'type'             => SupplierTransaction::TYPE_PAYMENT,
                    'amount'           => $amount,
                    'invoice_no'       => InvoiceNumberGenerator::next('SUP'),
                    'notes'            => ($data['notes'] ?? null) ?: 'Payment Made',
                    'transaction_date' => $this->shopToday(),
                ]);
                $transaction->submit_token = $data['submit_token'];
                $transaction->save();

                return $this->supplierPaymentRedirect($transaction, $action);
            });
        } catch (UniqueConstraintViolationException $e) {
            $transaction = SupplierTransaction::where('submit_token', $data['submit_token'])->first();
            if (! $transaction) {
                throw $e;
            }

            return $this->supplierPaymentRedirect($transaction, $action, true);
        }
    }

    private function supplierPaymentRedirect(SupplierTransaction $transaction, string $action, bool $duplicate = false)
    {
        if ($action === 'save_print') {
            return redirect()->route('khata.supplier.receipt', [
                'transaction' => $transaction->id,
                'print'       => 1,
            ]);
        }

        return redirect()->route('khata.index', ['tab' => 'supplier', 'id' => $transaction->supplier_id])
            ->with('success', $duplicate ? 'Payment already recorded. The repeated click was ignored.' : 'Payment recorded.');
    }

    /**
     * Today's date (Y-m-d) in the timezone saved in Settings (same $settings the
     * layout shares with every view). Falls back to the app timezone only if
     * Settings is not available.
     */
    private function shopToday(): string
    {
        $settings = view()->shared('settings');

        return $settings
            ? $settings->localize(now())->format('Y-m-d')
            : now()->format('Y-m-d');
    }

    /** 1500 -> "1500", 1500.4 -> "1500.40" (never hide the paisa in a message). */
    private function money($value): string
    {
        $n = round((float) $value, 2);

        return fmod($n, 1.0) == 0.0 ? number_format($n, 0, '.', '') : number_format($n, 2, '.', '');
    }

    public function supplierPaymentReceipt(SupplierTransaction $transaction)
    {
        $transaction->load('supplier');

        $newBalance      = $this->supplierBalanceAsOf($transaction);
        $previousBalance = round($newBalance + (float) $transaction->amount, 2);

        return view('khata.supplier-payment-receipt', compact(
            'transaction', 'newBalance', 'previousBalance'
        ));
    }

    /**
     * Customer balance right after the given transaction.
     * Uses the same order as the ledger: date first, then id.
     */
    protected function customerBalanceAsOf(CustomerTransaction $transaction): float
    {
        $date = $transaction->transaction_date->toDateString();

        $upToHere = CustomerTransaction::where('customer_id', $transaction->customer_id)
            ->where(function ($q) use ($date, $transaction) {
                $q->whereDate('transaction_date', '<', $date)
                    ->orWhere(function ($q2) use ($date, $transaction) {
                        $q2->whereDate('transaction_date', $date)
                            ->where('id', '<=', $transaction->id);
                    });
            });

        $khata    = (float) (clone $upToHere)->where('type', CustomerTransaction::TYPE_KHATA)->sum('amount');
        $payments = (float) (clone $upToHere)->where('type', CustomerTransaction::TYPE_PAYMENT)->sum('amount');

        return round($khata - $payments, 2);
    }

    /** Supplier balance right after the given transaction (same rule as above). */
    protected function supplierBalanceAsOf(SupplierTransaction $transaction): float
    {
        $date = $transaction->transaction_date->toDateString();

        $upToHere = SupplierTransaction::where('supplier_id', $transaction->supplier_id)
            ->where(function ($q) use ($date, $transaction) {
                $q->whereDate('transaction_date', '<', $date)
                    ->orWhere(function ($q2) use ($date, $transaction) {
                        $q2->whereDate('transaction_date', $date)
                            ->where('id', '<=', $transaction->id);
                    });
            });

        $purchases = (float) (clone $upToHere)->where('type', SupplierTransaction::TYPE_PURCHASE)->sum('amount');
        $payments  = (float) (clone $upToHere)->where('type', SupplierTransaction::TYPE_PAYMENT)->sum('amount');

        return round($purchases - $payments, 2);
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

            $filename = 'customer-khata-' . (Str::slug($entity->name) ?: 'ledger') . '-' . now()->format('Y-m-d-H-i-s') . '.csv';
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

            $filename = 'supplier-khata-' . (Str::slug($entity->name) ?: 'ledger') . '-' . now()->format('Y-m-d-H-i-s') . '.csv';
        } else {
            return redirect()->route('khata.index')->with('error', 'Unknown ledger.');
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        // Customer sales on khata: if a row has no invoice number of its own,
        // use the invoice number of the sale it came from.
        $saleInvoices = [];
        if ($tab === 'customer') {
            $saleIds = $transactions->pluck('sale_id')->filter()->unique()->all();
            if (! empty($saleIds)) {
                $saleInvoices = Sale::whereIn('id', $saleIds)->pluck('invoice_no', 'id')->all();
            }
        }

        $callback = function () use ($transactions, $tab, $saleInvoices) {
            $file = fopen('php://output', 'w');
            // BOM: lets Excel read Urdu / any UTF-8 text correctly
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Date', 'Particulars', 'Invoice', 'Note', 'Amount']);

            foreach ($transactions as $tx) {
                if ($tab === 'customer') {
                    $particulars = $tx->type === 'khata' ? 'Credit Sale' : 'Payment Received';
                    $invoiceNo = $tx->invoice_no ?: ($saleInvoices[$tx->sale_id] ?? '');
                } else {
                    $particulars = $tx->type === 'purchase' ? 'Milk Purchase' : 'Payment Made';
                    $invoiceNo = $tx->invoice_no ?? '';
                }

                fputcsv($file, [
                    $tx->transaction_date->format('M j, Y'),
                    $particulars,
                    $this->csvSafe($invoiceNo),
                    $this->csvSafe($tx->notes ?? ''),
                    // plain number: no + / - sign (Particulars already says what it is)
                    number_format((float) $tx->amount, 2, '.', ''),
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Stop "CSV injection": a note typed as =HYPERLINK(...) would run as a
     * formula when the file is opened in Excel. A leading ' makes it plain text.
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}