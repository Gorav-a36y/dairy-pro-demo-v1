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
    private const DUPLICATE_NAME_MESSAGE = 'A supplier with this name already exists.';
    private const DUPLICATE_PHONE_MESSAGE = 'A supplier with this phone number already exists.';

    public function index(Request $request)
    {
        // Search must be plain text. Anything else (for example ?search[]=x
        // in a changed URL) is ignored instead of crashing the page.
        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';

        $suppliers = Supplier::query()
            ->when($search !== '', function ($q) use ($search) {
                // Treat \ % and _ as normal characters, not LIKE wildcards.
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                $q->whereRaw("name LIKE ? ESCAPE '\\'", ['%' . $escaped . '%']);
            })
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
            'phone.unique' => self::DUPLICATE_PHONE_MESSAGE,
        ]);

        try {
            Supplier::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Database-level protection: two saves arrived at the same moment
            // and the unique index stopped one of them. Say which value clashed.
            $errors = [];

            if (Supplier::where('phone', $data['phone'])->exists()) {
                $errors['phone'] = self::DUPLICATE_PHONE_MESSAGE;
            }

            if (Supplier::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
                $errors['name'] = self::DUPLICATE_NAME_MESSAGE;
            }

            throw ValidationException::withMessages(
                $errors ?: ['name' => 'This supplier could not be saved. Please try again.']
            );
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier added successfully.');
    }

    /**
     * Clean the name (trim both ends and turn any run of spaces inside it
     * into one space, so "Ali  Khan" becomes "Ali Khan") and save the phone
     * in one standard form (0300-1234567 and 03001234567 become the same
     * number). A non-text value is left for validation to reject.
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
                : trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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
                $fail(self::DUPLICATE_NAME_MESSAGE);
            }
        };
    }
}
