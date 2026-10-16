<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->cleanInput($request);

        $data = $this->validated($request);

        try {
            DB::transaction(function () use ($data) {
                $dailyRows = $data['daily_rows'];
                unset($data['daily_rows']);

                $customer = Customer::create($data);
                $customer->syncDailyItems($dailyRows);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Database-level protection (two requests saving the same phone at once).
            throw ValidationException::withMessages([
                'phone' => 'A customer with this phone number already exists.',
            ]);
        }

        return redirect()->route('customers.index')
            ->with('success', 'Customer added successfully.');
    }

    public function edit(Customer $customer)
    {
        $dailyItems   = $this->dailyItemOptions($customer);
        $allCustomers = Customer::select('id', 'name')->where('id', '!=', $customer->id)->orderBy('name')->get();

        // Old input (after validation error) wins over saved rows.
        $customerDailyItems = old('daily_items');

        if ($customerDailyItems === null) {
            $customerDailyItems = $customer->dailyItems->map(fn ($di) => [
                'item_key' => $di->item_key,
                'quantity' => (string) $di->quantity,
            ])->values()->all();
        }

        // Always render at least one (possibly empty) row.
        if (count($customerDailyItems) === 0) {
            $customerDailyItems = [['item_key' => '', 'quantity' => '']];
        }

        return view('customers.edit', compact(
            'customer', 'dailyItems', 'allCustomers', 'customerDailyItems'
        ));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->cleanInput($request);

        if ($request->has('name') && $request->input('name') !== $customer->name) {
            throw ValidationException::withMessages([
                'name' => 'The customer name cannot be changed after the customer is created.',
            ]);
        }

        $data = $this->validated($request, $customer);

        try {
            DB::transaction(function () use ($data, $customer) {
                $dailyRows = $data['daily_rows'];
                unset($data['daily_rows']);

                $customer->update($data);
                $customer->syncDailyItems($dailyRows);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'phone' => 'A customer with this phone number already exists.',
            ]);
        }

        return redirect()->route('customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Trim the name and save the phone in one standard form
     * (0300-1234567 and 03001234567 become the same number).
     * A non-text value is left for validation to reject.
     */
    protected function cleanInput(Request $request): void
    {
        $clean = [];

        foreach (['name', 'phone'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            if (! is_string($value)) {
                $clean[$field] = $value;
                continue;
            }

            $clean[$field] = $field === 'phone'
                ? PhoneNumber::normalize($value)
                : trim($value);
        }

        $request->merge($clean);
    }

    protected function validated(Request $request, ?Customer $customer = null): array
    {
        $rules = [
            'phone' => [
                'bail', 'required', 'string', 'max:30',
                'regex:/^\d{7,15}$/',
                Rule::unique('customers', 'phone')->ignore($customer?->id),
            ],
            'is_daily_customer' => 'boolean',

            'daily_items'            => 'nullable|array',
            'daily_items.*.item_key' => ['nullable', 'string'],
            'daily_items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
        ];

        if (! $customer) {
            $rules['name'] = [
                'bail', 'required', 'string', 'max:255',
                function ($attribute, $value, $fail) {
                    if (! is_string($value)) {
                        return;
                    }

                    $exists = Customer::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))])->exists();
                    if ($exists) {
                        $fail('A customer with this name already exists.');
                    }
                },
            ];
        }

        $data = $request->validate($rules, [
            'phone.regex'                => 'Enter a valid phone number (7 to 15 digits). Spaces, dashes and +92 are fine.',
            'phone.unique'               => 'A customer with this phone number already exists.',
            'daily_items.*.quantity.min' => 'Daily quantity must be at least 0.01.',
        ]);

        if (! $customer) {
            $data['name'] = trim($data['name']);
        }

        $isDaily = $request->boolean('is_daily_customer');
        $data['is_daily_customer'] = $isDaily;

        $dailyRows = [];

        if ($isDaily) {
            // Drop fully-empty rows (user added a row but left it blank).
            $rows = collect($data['daily_items'] ?? [])
                ->filter(function ($row) {
                    $hasKey  = trim((string) ($row['item_key'] ?? '')) !== '';
                    $hasQty  = trim((string) ($row['quantity'] ?? '')) !== '';

                    return $hasKey || $hasQty;
                })
                ->values()
                ->all();

            if (count($rows) < 1) {
                throw ValidationException::withMessages([
                    'daily_items' => 'Add at least one daily item for a daily customer.',
                ]);
            }

            $seen = [];

            foreach ($rows as $index => $row) {
                $itemKey = trim((string) ($row['item_key'] ?? ''));
                $qty     = $row['quantity'] ?? null;

                if ($itemKey === '') {
                    throw ValidationException::withMessages([
                        "daily_items.{$index}.item_key" => 'Select an item for every filled-in row.',
                    ]);
                }

                if ($qty === null || $qty === '') {
                    throw ValidationException::withMessages([
                        "daily_items.{$index}.quantity" => 'Enter a quantity for every selected item.',
                    ]);
                }

                [$type, $id] = array_pad(explode(':', $itemKey, 2), 2, null);

                $itemExists = match ($type) {
                    'product'      => is_numeric($id) && Product::whereKey($id)->exists(),
                    'raw_material' => is_numeric($id) && RawMaterial::whereKey($id)->exists(),
                    default        => false,
                };

                if (! $itemExists) {
                    throw ValidationException::withMessages([
                        "daily_items.{$index}.item_key" => 'The selected item is invalid or no longer exists.',
                    ]);
                }

                $key = "{$type}:{$id}";

                if (in_array($key, $seen, true)) {
                    throw ValidationException::withMessages([
                        "daily_items.{$index}.item_key" => 'The same item appears twice. Please remove the duplicate row.',
                    ]);
                }

                $seen[] = $key;

                $dailyRows[] = [
                    'item_type' => $type,
                    'item_id'   => (int) $id,
                    'quantity'  => $qty,
                ];
            }
        }

        $data['daily_rows'] = $dailyRows;
        unset($data['daily_items']);

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

        // On edit: make sure this customer's saved (possibly now-inactive)
        // items still appear in the dropdown so the value doesn't get lost.
        if ($customer) {
            foreach ($customer->dailyItems as $di) {
                $currentKey = $di->item_key;

                if (! $items->contains(fn ($i) => $i['id'] === $currentKey)) {
                    $current = $di->item();

                    if ($current) {
                        $items->push([
                            'id' => $currentKey,
                            'label' => $current->name . ' (inactive)',
                            'sublabel' => ($di->itemType() === 'product' ? 'Product' : 'Raw Material') . ' · ' . $current->unit,
                        ]);
                    }
                }
            }
        }

        return $items->values()->toArray();
    }
}
