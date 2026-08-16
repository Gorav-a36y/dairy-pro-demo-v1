<?php

namespace App\Http\Controllers;

use App\Models\BatchProduction;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('ingredients')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $ingredients = Ingredient::orderBy('name')->get();

        return view('products.create', compact('ingredients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|in:' . implode(',', Product::UNITS),
            'selling_price' => 'required|numeric|min:0',
            'output_qty_per_batch' => 'required|numeric|min:0.01',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'ingredients.*.quantity_required' => 'required|numeric|min:0.01',
        ]);

        // Only keep rows where both fields were actually filled in.
        $recipeRows = collect($data['ingredients'])->filter(
            fn ($row) => ! empty($row['ingredient_id']) && ! empty($row['quantity_required'])
        )->values();

        if ($recipeRows->isEmpty()) {
            return back()->withInput()->with('error', 'Add at least one raw material to the recipe.');
        }

        // Check raw material stock is sufficient to produce the initial batch right now.
        foreach ($recipeRows as $row) {
            $ingredient = Ingredient::find($row['ingredient_id']);
            if ($ingredient && (float) $row['quantity_required'] > (float) $ingredient->stock_qty) {
                return back()->withInput()->with('error', "Not enough {$ingredient->name} in stock. Needed: {$row['quantity_required']} {$ingredient->unit}, Available: {$ingredient->stock_qty} {$ingredient->unit}.");
            }
        }

        $product = DB::transaction(function () use ($data, $recipeRows) {
            $product = Product::create([
                'name' => $data['name'],
                'unit' => $data['unit'],
                'selling_price' => $data['selling_price'],
                'stock_qty' => 0,
                'output_qty_per_batch' => $data['output_qty_per_batch'],
                'is_active' => true,
            ]);

            $sync = [];
            $productionCost = 0.0;

            foreach ($recipeRows as $row) {
                $ingredient = Ingredient::findOrFail($row['ingredient_id']);
                $qty = (float) $row['quantity_required'];

                $sync[$ingredient->id] = ['quantity_required' => $qty];
                $productionCost += $qty * (float) $ingredient->cost_per_unit;

                $ingredient->decrement('stock_qty', $qty);
            }

            $product->ingredients()->sync($sync);

            $outputQty = (float) $data['output_qty_per_batch'];
            $costPerUnit = $outputQty > 0 ? round($productionCost / $outputQty, 2) : 0;

            $product->update(['stock_qty' => $outputQty]);

            BatchProduction::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'multiplier' => 1,
                'output_qty' => $outputQty,
                'batch_cost' => round($productionCost, 2),
                'cost_per_unit' => $costPerUnit,
                'status' => 'completed',
                'notes' => 'Initial production at product creation',
            ]);

            return $product;
        });

        return redirect()->route('products.index')->with('success', "Product created — produced {$product->stock_qty} {$product->unit} of {$product->name}.");
    }

    public function edit(Product $product)
    {
        $product->load('ingredients');
        $ingredients = Ingredient::orderBy('name')->get();

        return view('products.edit', compact('product', 'ingredients'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|in:' . implode(',', Product::UNITS),
            'selling_price' => 'required|numeric|min:0',
            'stock_qty' => 'nullable|numeric|min:0',
            'output_qty_per_batch' => 'required|numeric|min:0.01',
            'ingredients' => 'array',
        ]);

        $product->update([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'selling_price' => $data['selling_price'],
            'stock_qty' => $data['stock_qty'] ?? $product->stock_qty,
            'output_qty_per_batch' => $data['output_qty_per_batch'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncRecipe($product, $request->input('ingredients', []));

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    protected function syncRecipe(Product $product, array $rows): void
    {
        $sync = [];
        foreach ($rows as $row) {
            if (empty($row['ingredient_id']) || empty($row['quantity_required'])) {
                continue;
            }
            $sync[$row['ingredient_id']] = ['quantity_required' => $row['quantity_required']];
        }
        $product->ingredients()->sync($sync);
    }
}
