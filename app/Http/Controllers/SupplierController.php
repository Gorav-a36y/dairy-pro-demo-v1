<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Supplier;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Suppliers are create-only on purpose.
 * Name and phone are not editable after the supplier is created,
 * because milk collections and khata records depend on them.
 */
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

        $settings = Setting::current();

        return view('suppliers.index', compact('suppliers', 'settings'));
    }

    public function create()
    {
        $allSuppliers = Supplier::select('id', 'name')->orderBy('name')->get();

        return view('suppliers.create', compact('allSuppliers'));
    }

    public function store(Request $request)
    {
        $this->cleanInput($request);

        $data = $request->validate([
            'name' => ['bail', 'required', 'string', 'max:255', $this->uniqueNameRule()],
            'phone' => [
                'bail', 'required', 'string', 'max:30',
                'regex:/^\d{7,15}$/',
                Rule::unique('suppliers', 'phone'),
            ],
        ], [
            'phone.regex'  => 'Enter a valid phone number (7 to 15 digits). Spaces, dashes and +92 are fine.',
            'phone.unique' => 'A supplier with this phone number already exists.',
        ]);

        try {
            Supplier::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Database-level protection (two requests saving the same phone at once).
            throw ValidationException::withMessages([
                'phone' => 'A supplier with this phone number already exists.',
            ]);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier added successfully.');
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

    /** Case-insensitive "name is unique" rule, since a plain unique() rule is case-sensitive on SQLite. */
    protected function uniqueNameRule()
    {
        return function ($attribute, $value, $fail) {
            if (!is_string($value)) {
                return;
            }

            $exists = Supplier::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))])->exists();
            if ($exists) {
                $fail('A supplier with this name already exists.');
            }
        };
    }
}
