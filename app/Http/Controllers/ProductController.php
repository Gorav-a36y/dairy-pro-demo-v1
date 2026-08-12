<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Http\Request;

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
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_qty' => 'nullable|numeric|min:0',
            'output_qty_per_batch' => 'nullable|numeric|min:0.01',
            'ingredients' => 'array',
            'ingredients.*.ingredient_id' => 'nullable|exists:ingredients,id',
            'ingredients.*.quantity_required' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'purchase_price' => $data['purchase_price'],
            'selling_price' => $data['selling_price'],
            'stock_qty' => $data['stock_qty'] ?? 0,
            'output_qty_per_batch' => $data['output_qty_per_batch'] ?? 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncRecipe($product, $request->input('ingredients', []));

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
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
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_qty' => 'nullable|numeric|min:0',
            'output_qty_per_batch' => 'nullable|numeric|min:0.01',
            'ingredients' => 'array',
        ]);

        $product->update([
            'name' => $data['name'],
            'unit' => $data['unit'],
            'purchase_price' => $data['purchase_price'],
            'selling_price' => $data['selling_price'],
            'stock_qty' => $data['stock_qty'] ?? $product->stock_qty,
            'output_qty_per_batch' => $data['output_qty_per_batch'] ?? $product->output_qty_per_batch,
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
