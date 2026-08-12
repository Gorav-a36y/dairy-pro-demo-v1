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

        $customers = Customer::orderBy('name')->get()->map(function ($c) {
            $c->balance = $c->currentBalance();
            return $c;
        });

        $suppliers = Supplier::orderBy('name')->get()->map(function ($s) {
            $s->balance = $s->currentBalance();
            return $s;
        });

        $selectedCustomer = null;
        $selectedSupplier = null;

        if ($tab === 'customer' && $request->id) {
            $selectedCustomer = Customer::with(['transactions'])->find($request->id);
        } elseif ($tab === 'supplier' && $request->id) {
            $selectedSupplier = Supplier::with(['transactions'])->find($request->id);
        }

        return view('khata.index', compact('tab', 'customers', 'suppliers', 'selectedCustomer', 'selectedSupplier'));
    }

    public function storeCustomerTransaction(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:khata,payment',
            'amount' => 'required|numeric|min:0.01',
            'invoice_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'transaction_date' => 'required|date',
        ]);

        CustomerTransaction::create($data);

        return redirect()->route('khata.index', ['tab' => 'customer', 'id' => $data['customer_id']])
            ->with('success', 'Transaction added to customer khata.');
    }

    public function storeSupplierTransaction(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'type' => 'required|in:purchase,payment',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'transaction_date' => 'required|date',
        ]);

        SupplierTransaction::create($data);

        return redirect()->route('khata.index', ['tab' => 'supplier', 'id' => $data['supplier_id']])
            ->with('success', 'Transaction added to supplier khata.');
    }
}
