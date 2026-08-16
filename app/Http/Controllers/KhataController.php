<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Http\Request;

class KhataController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'customer');

        /* ── Customers: balance = khata − payment (single query) ── */
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

        /* ── Suppliers: balance = opening + purchase − payment (single query) ── */
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
                $s->balance = round(
                    (float) ($s->opening_balance ?? 0)
                    + (float) $s->total_purchase
                    - (float) $s->total_payment,
                    2
                );
                return $s;
            });

        $selectedCustomer = null;
        $selectedSupplier = null;

        if ($tab === 'customer' && $request->id) {
            $selectedCustomer = Customer::with(['transactions' => function ($q) {
                $q->orderBy('transaction_date', 'asc')->orderBy('id', 'asc');
            }])->find($request->id);

            if ($selectedCustomer) {
                $selectedCustomer->balance = $customers->firstWhere('id', $selectedCustomer->id)->balance ?? 0;
            }
        } elseif ($tab === 'supplier' && $request->id) {
            $selectedSupplier = Supplier::with(['transactions' => function ($q) {
                $q->orderBy('transaction_date', 'asc')->orderBy('id', 'asc');
            }])->find($request->id);

            if ($selectedSupplier) {
                $selectedSupplier->balance = $suppliers->firstWhere('id', $selectedSupplier->id)->balance ?? 0;
            }
        }

        return view('khata.index', compact(
            'tab', 'customers', 'suppliers', 'selectedCustomer', 'selectedSupplier'
        ));
    }

    public function storeCustomerTransaction(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount'      => 'required|numeric|min:0.01',
            'notes'       => 'nullable|string|max:500',
        ]);

        CustomerTransaction::create([
            'customer_id'      => $data['customer_id'],
            'type'             => 'payment',
            'amount'           => $data['amount'],
            'notes'            => $data['notes'],
            'transaction_date' => now()->toDateString(),
        ]);

        return redirect()->route('khata.index', ['tab' => 'customer', 'id' => $data['customer_id']])
            ->with('success', 'Payment recorded successfully.');
    }

    public function storeSupplierTransaction(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount'      => 'required|numeric|min:0.01',
            'notes'       => 'nullable|string|max:500',
        ]);

        SupplierTransaction::create([
            'supplier_id'      => $data['supplier_id'],
            'type'             => 'payment',
            'amount'           => $data['amount'],
            'notes'            => $data['notes'],
            'transaction_date' => now()->toDateString(),
        ]);

        return redirect()->route('khata.index', ['tab' => 'supplier', 'id' => $data['supplier_id']])
            ->with('success', 'Payment recorded successfully.');
    }
}