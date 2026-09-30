<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RawMaterialController extends Controller
{
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

        $settings = \App\Models\Setting::current();

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
        $request->merge([
            'name' => trim($request->input('name', '')),
        ]);

        $data = $request->validate([
            'name' => [
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
            'selling_price' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'stock_qty' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_milk'] = $request->boolean('is_milk', false);

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
        $request->merge([
            'name' => trim($request->input('name', '')),
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                $this->uniqueNameRule($rawMaterial->id),
            ],
            'unit' => [
                'required',
                'string',
                Rule::in(\App\Models\Product::UNITS),
            ],
            'selling_price' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_milk'] = $request->boolean('is_milk', false);

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

    /** Case-insensitive "name is unique" rule, since a plain unique() rule is case-sensitive on SQLite. */
    protected function uniqueNameRule(?int $ignoreId = null)
    {
        return function ($attribute, $value, $fail) use ($ignoreId) {
            $exists = RawMaterial::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($exists) {
                $fail('A raw material with this name already exists.');
            }
        };
    }
}