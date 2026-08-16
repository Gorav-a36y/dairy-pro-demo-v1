<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        $dailyItems = $this->dailyItemOptions();

        return view('customers.create', compact('dailyItems'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Customer::create($data);

        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }

    public function edit(Customer $customer)
    {
        $dailyItems = $this->dailyItemOptions();

        return view('customers.edit', compact('customer', 'dailyItems'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request);

        $customer->update($data);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return back()->with('success', 'Customer removed.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'daily_item_key' => 'nullable|string',
            'daily_quantity' => 'nullable|numeric|min:0.01',
        ]);

        $isDaily = $request->boolean('is_daily_customer');
        $data['is_daily_customer'] = $isDaily;

        if ($isDaily && $data['daily_item_key'] ?? null) {
            [$type, $id] = array_pad(explode(':', $data['daily_item_key'], 2), 2, null);
            $data['daily_item_type'] = $type;
            $data['daily_item_id'] = $id;
        } else {
            $data['daily_item_type'] = null;
            $data['daily_item_id'] = null;
            $data['daily_quantity'] = null;
        }

        unset($data['daily_item_key']);

        return $data;
    }

    protected function dailyItemOptions(): array
    {
        $products = Product::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($p) => ['id' => 'product:' . $p->id, 'label' => $p->name, 'sublabel' => 'Product · ' . $p->unit]);

        $ingredients = Ingredient::orderBy('name')->get()
            ->map(fn ($i) => ['id' => 'ingredient:' . $i->id, 'label' => $i->name, 'sublabel' => 'Raw Material · ' . $i->unit]);

        return $products->concat($ingredients)->values()->toArray();
    }
}