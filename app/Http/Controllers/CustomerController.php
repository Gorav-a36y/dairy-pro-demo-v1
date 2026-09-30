<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::when(
            $request->search,
            fn ($q) => $q->where('name', 'like', "%{$request->search}%")
        )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        $dailyItems = $this->dailyItemOptions();
        $allCustomers = Customer::select('id', 'name')->orderBy('name')->get();

        return view('customers.create', compact('dailyItems', 'allCustomers'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Customer::create($data);

        return redirect()->route('customers.index')
            ->with('success', 'Customer added successfully.');
    }

    public function edit(Customer $customer)
    {
        $dailyItems = $this->dailyItemOptions($customer);
        $allCustomers = Customer::select('id', 'name')->where('id', '!=', $customer->id)->orderBy('name')->get();

        return view('customers.edit', compact('customer', 'dailyItems', 'allCustomers'));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request, $customer);

        $customer->update($data);

        return redirect()->route('customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    protected function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($customer) {
                    $exists = Customer::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))])
                        ->when($customer, fn ($q) => $q->where('id', '!=', $customer->id))
                        ->exists();
                    if ($exists) {
                        $fail('A customer with this name already exists.');
                    }
                },
            ],
            'phone' => [
                'required', 'string', 'max:30',
                Rule::unique('customers', 'phone')->ignore($customer?->id),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')->ignore($customer?->id),
            ],
            'address' => 'required|string|max:500',
            'daily_item_key' => 'nullable|string',
            'daily_quantity' => 'nullable|numeric|min:0.01',
        ]);

        $data['name'] = trim($data['name']);   // ← add here

        if (Supplier::where('phone', $data['phone'])->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered as a supplier. A customer and a supplier cannot share the same phone number.',
            ]);
        }

        $isDaily = $request->boolean('is_daily_customer');
        $data['is_daily_customer'] = $isDaily;

        if ($isDaily && ($data['daily_item_key'] ?? null)) {
            [$type, $id] = array_pad(explode(':', $data['daily_item_key'], 2), 2, null);

            if (in_array($type, ['product', 'raw_material']) && is_numeric($id)) {
                $data['daily_item_type'] = $type;
                $data['daily_item_id'] = (int) $id;
            } else {
                $data['daily_item_type'] = null;
                $data['daily_item_id'] = null;
                $data['daily_quantity'] = null;
            }
        } else {
            $data['daily_item_type'] = null;
            $data['daily_item_id'] = null;
            $data['daily_quantity'] = null;
        }

        unset($data['daily_item_key']);

        return $data;
    }

    protected function dailyItemOptions(?Customer $customer = null): array
    {
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => 'product:' . $p->id,
                'label' => $p->name,
                'sublabel' => 'Product · ' . $p->unit,
            ]);

        $rawMaterials = RawMaterial::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($i) => [
                'id' => 'raw_material:' . $i->id,
                'label' => $i->name,
                'sublabel' => 'Raw Material · ' . $i->unit,
            ]);

        $items = $products->concat($rawMaterials);

        if ($customer && $customer->daily_item_type && $customer->daily_item_id) {
            $currentKey = $customer->daily_item_type . ':' . $customer->daily_item_id;
            $alreadyThere = $items->contains(fn ($i) => $i['id'] === $currentKey);

            if (! $alreadyThere) {
                $current = $customer->dailyItem();
                if ($current) {
                    $items->push([
                        'id' => $currentKey,
                        'label' => $current->name . ' (inactive)',
                        'sublabel' => ($customer->daily_item_type === 'product' ? 'Product' : 'Raw Material') . ' · ' . $current->unit,
                    ]);
                }
            }
        }

        return $items->values()->toArray();
    }
}