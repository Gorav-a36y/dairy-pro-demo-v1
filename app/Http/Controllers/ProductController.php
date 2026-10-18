<?php

namespace App\Http\Controllers;

use App\Models\BatchProduction;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Products and Batch Production now live on one page: manage the
     * product list (add/edit/activate) up top, run a production batch for
     * any active product in the middle, and see recent batch history below.
     */
    public function index(Request $request)
    {
        $products = Product::with('rawMaterials')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Active products with their recipe loaded — used by the "Run a
        // Production Batch" cards further down the page.
        $activeProducts = Product::with('rawMaterials')->where('is_active', true)->orderBy('name')->get();

        $settings = Setting::current();

        $batchHistory = BatchProduction::with('product')
            ->when(
                !empty($request->get('batch_from')),
                function ($q) use ($request, $settings) {
                    [$from] = $settings->businessDayBounds(
                        \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $request->get('batch_from'), $settings->timezone())
                    );

                    return $q->where('created_at', '>=', $from);
                }
            )
            ->when(
                !empty($request->get('batch_to')),
                function ($q) use ($request, $settings) {
                    [, $to] = $settings->businessDayBounds(
                        \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $request->get('batch_to'), $settings->timezone())
                    );

                    return $q->where('created_at', '<=', $to);
                }
            )
            ->latest()
            ->take(10)
            ->get();

        return view('products.index', compact('products', 'activeProducts', 'batchHistory'));
    }

    public function create()
    {
        $rawMaterials = RawMaterial::where('is_active', true)->orderBy('name')->get();
        $settings = Setting::current();
        $allProducts = Product::select('id', 'name')->orderBy('name')->get();

        return view('products.create', compact('rawMaterials', 'settings', 'allProducts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule()],
            'unit' => 'required|string|in:' . implode(',', Product::UNITS),
            'selling_price' => 'required|numeric|min:0.01|max:9999999999.99',
            'output_qty_per_batch' => 'required|numeric|min:0.01|max:9999999999.99',
            'raw_materials' => 'required|array|min:1',
            'raw_materials.*.raw_material_id' => 'required|exists:raw_materials,id|distinct',
            'raw_materials.*.quantity_required' => 'required|numeric|min:0.01|max:9999999999.99',
            'is_active' => 'nullable|boolean',
        ]);

        $data['name'] = trim($data['name']);

        $recipeRows = collect($data['raw_materials'])->filter(
            fn ($row) => !empty($row['raw_material_id']) && !empty($row['quantity_required'])
        )->values();

        if ($recipeRows->isEmpty()) {
            return back()->withInput()->with('error', 'Add at least one raw material to the recipe.');
        }

        $product = DB::transaction(function () use ($data, $recipeRows, $request) {
            $product = Product::create([
                'name' => $data['name'],
                'unit' => $data['unit'],
                'selling_price' => $data['selling_price'],
                'stock_qty' => 0,
                'output_qty_per_batch' => $data['output_qty_per_batch'],
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncRecipe($product, $recipeRows->all());

            return $product;
        });

        return redirect()->route('products.index')->with('success', "Product \"{$product->name}\" created. Run your first batch from the Batch Production page.");
    }

    public function edit(Product $product)
    {
        $product->load('rawMaterials');

        $rawMaterials = RawMaterial::where('is_active', true)
            ->orWhereIn('id', $product->rawMaterials->pluck('id'))
            ->orderBy('name')
            ->get();

        $settings = Setting::current();
        $allProducts = Product::select('id', 'name')->where('id', '!=', $product->id)->orderBy('name')->get();

        return view('products.edit', compact('product', 'rawMaterials', 'settings', 'allProducts'));
    }

    public function update(Request $request, Product $product)
    {
        // 'unit' and 'stock_qty' are deliberately NOT validated or saved here:
        // the unit is locked after creation, and stock only changes through
        // Batch Production / sales / waste. Anything sent for them is ignored.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule($product->id)],
            'selling_price' => 'required|numeric|min:0.01|max:9999999999.99',
            'output_qty_per_batch' => 'required|numeric|min:0.01|max:9999999999.99',
            'raw_materials' => 'required|array|min:1',
            'raw_materials.*.raw_material_id' => 'required|exists:raw_materials,id|distinct',
            'raw_materials.*.quantity_required' => 'required|numeric|min:0.01|max:9999999999.99',
            'is_active' => 'nullable|boolean',
        ]);

        $data['name'] = trim($data['name']);

        $recipeRows = collect($data['raw_materials'])->filter(
            fn ($row) => !empty($row['raw_material_id']) && !empty($row['quantity_required'])
        )->values();

        if ($recipeRows->isEmpty()) {
            return back()->withInput()->with('error', 'Add at least one raw material to the recipe.');
        }

        DB::transaction(function () use ($product, $data, $recipeRows, $request) {
            $product->update([
                'name' => $data['name'],
                'selling_price' => $data['selling_price'],
                'output_qty_per_batch' => $data['output_qty_per_batch'],
                // Optional field: if it is not sent, keep the current status.
                'is_active' => $request->boolean('is_active', $product->is_active),
            ]);

            $this->syncRecipe($product, $recipeRows->all());
        });

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);

        return back()->with('success', $product->is_active
            ? "{$product->name} marked active."
            : "{$product->name} marked inactive."
        );
    }

    /** Case-insensitive "name is unique" rule, since a plain unique() rule is case-sensitive on SQLite. */
    protected function uniqueNameRule(?int $ignoreId = null)
    {
        return function ($attribute, $value, $fail) use ($ignoreId) {
            $exists = Product::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($value))])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($exists) {
                $fail('A product with this name already exists.');
            }
        };
    }

    protected function syncRecipe(Product $product, array $rows): void
    {
        $sync = [];
        foreach ($rows as $row) {
            if (empty($row['raw_material_id']) || empty($row['quantity_required'])) {
                continue;
            }
            $sync[$row['raw_material_id']] = ['quantity_required' => (float) $row['quantity_required']];
        }

        $product->rawMaterials()->sync($sync);
    }
}
