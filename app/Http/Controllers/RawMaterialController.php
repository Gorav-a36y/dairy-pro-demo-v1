<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RawMaterialController extends Controller
{
    private const MAX_DECIMAL = 9999999999.99;
    private const MILK_UNITS = ['Liter', 'Kilogram'];

    public function index(Request $request)
    {
        $rawMaterials = RawMaterial::query()
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(
                    'name',
                    'like',
                    '%' . $request->search . '%'
                )
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $settings = Setting::current();

        return view('raw-materials.index', compact('rawMaterials', 'settings'));
    }

    public function create()
    {
        $allRawMaterials = RawMaterial::select('id', 'name', 'stock_qty', 'unit')
            ->orderBy('name')
            ->get();
        $settings = Setting::current();

        return view('raw-materials.create', compact('allRawMaterials', 'settings'));
    }

    public function store(Request $request)
    {
        $this->normalizeName($request);

        $data = $request->validate([
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                $this->uniqueNameRule(),
            ],
            'unit' => [
                'required',
                'string',
                Rule::in(\App\Models\Product::UNITS),
            ],
            'is_milk' => ['sometimes', 'boolean'],
            'selling_price' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . self::MAX_DECIMAL,
            ],
            // Optional on create. Saved as 0 when left empty.
            'stock_qty' => [
                'nullable',
                'numeric',
                'min:0',
                'max:' . self::MAX_DECIMAL,
            ],
        ]);

        $data['stock_qty'] = $data['stock_qty'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_milk'] = $request->boolean('is_milk', false);

        if ($data['is_milk'] && !in_array($data['unit'], self::MILK_UNITS, true)) {
            throw ValidationException::withMessages([
                'is_milk' => 'Only raw materials measured in Liter or Kilogram can be marked as milk.',
            ]);
        }

        RawMaterial::create($data);

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Raw material added successfully.');
    }

    public function edit(RawMaterial $rawMaterial)
    {
        $allRawMaterials = RawMaterial::select('id', 'name', 'stock_qty', 'unit')
            ->where('id', '!=', $rawMaterial->id)
            ->orderBy('name')
            ->get();
        $settings = Setting::current();

        return view('raw-materials.edit', compact('rawMaterial', 'allRawMaterials', 'settings'));
    }

    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $this->normalizeName($request);

        // Unit, Is Milk and Stock are locked after creation.
        // The edit form never sends them (the fields are disabled).
        // If a request still sends a different value, reject it.
        $this->guardLockedFields($request, $rawMaterial);

        // Only these fields are saved. unit, is_milk and stock_qty are
        // left out on purpose, so they can never be changed from here.
        $data = $request->validate([
            'name' => [
                'bail',
                'required',
                'string',
                'max:255',
                $this->uniqueNameRule($rawMaterial->id),
            ],
            'selling_price' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . self::MAX_DECIMAL,
            ],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $rawMaterial->update($data);

        return redirect()
            ->route('raw-materials.index')
            ->with('success', 'Raw material updated successfully.');
    }

    public function toggleActive(RawMaterial $rawMaterial)
    {
        $rawMaterial->update(['is_active' => !$rawMaterial->is_active]);

        return back()->with('success', $rawMaterial->is_active
            ? "{$rawMaterial->name} marked active."
            : "{$rawMaterial->name} marked inactive."
        );
    }

    /** Trim the name safely (a non-text value is left for validation to reject). */
    protected function normalizeName(Request $request): void
    {
        $name = $request->input('name');

        $request->merge([
            'name' => is_string($name) ? trim($name) : $name,
        ]);
    }

    /**
     * Reject any attempt to change the unit, the "is milk" flag or the stock on update.
     */
    protected function guardLockedFields(Request $request, RawMaterial $rawMaterial): void
    {
        $errors = [];

        if ($request->has('unit') && $request->input('unit') !== $rawMaterial->unit) {
            $errors['unit'] = 'Unit cannot be changed after the raw material is created.';
        }

        if ($request->has('is_milk') && $request->boolean('is_milk') !== (bool) $rawMaterial->is_milk) {
            $errors['is_milk'] = 'The Milk setting cannot be changed after the raw material is created.';
        }

        if ($request->has('stock_qty')
            && abs((float) $request->input('stock_qty') - (float) $rawMaterial->stock_qty) >= 0.005) {
            $errors['stock_qty'] = 'Stock cannot be changed here. It updates automatically from milk collections.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Case-insensitive "name is unique" rule, since a plain unique() rule is case-sensitive on SQLite. */
    protected function uniqueNameRule(?int $ignoreId = null)
    {
        return function ($attribute, $value, $fail) use ($ignoreId) {
            if (!is_string($value)) {
                return;
            }

            $exists = RawMaterial::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($exists) {
                $fail('A raw material with this name already exists.');
            }
        };
    }
}
