<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->get()
            ->map(function ($supplier) {
                $supplier->balance = $supplier->currentBalance();
                return $supplier;
            });

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        $allSuppliers = Supplier::select('id', 'name')->orderBy('name')->get();

        return view('suppliers.create', compact('allSuppliers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule()],
            'phone' => [
                'required', 'string', 'max:30',
                Rule::unique('suppliers', 'phone'),
            ],
            'address' => 'required|string|max:500',
        ]);

        $data['name'] = trim($data['name']);   // ← add here

        $this->guardAgainstCustomerDuplicate($data['phone']);

        Supplier::create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier added successfully.');
    }

    public function edit(Supplier $supplier)
    {
        $allSuppliers = Supplier::select('id', 'name')->where('id', '!=', $supplier->id)->orderBy('name')->get();

        return view('suppliers.edit', compact('supplier', 'allSuppliers'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule($supplier->id)],
            'phone' => [
                'required', 'string', 'max:30',
                Rule::unique('suppliers', 'phone')->ignore($supplier->id),
            ],
            'address' => 'required|string|max:500',
        ]);

        $data['name'] = trim($data['name']);   // ← add here

        $this->guardAgainstCustomerDuplicate($data['phone']);

        $supplier->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    /**
     * Cross-module guard: this exact phone number is already registered as
     * a Customer — almost always the same real person entered twice by
     * mistake into two different ledgers.
     */
    protected function guardAgainstCustomerDuplicate(string $phone): void
    {
        if (Customer::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered as a customer. A supplier and a customer cannot share the same phone number.',
            ]);
        }
    }

    /** Case-insensitive "name is unique" rule, since a plain unique() rule is case-sensitive on SQLite. */
    protected function uniqueNameRule(?int $ignoreId = null)
    {
        return function ($attribute, $value, $fail) use ($ignoreId) {
            $exists = Supplier::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($exists) {
                $fail('A supplier with this name already exists.');
            }
        };
    }
}