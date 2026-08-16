<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $ingredients = Ingredient::when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('ingredients.index', compact('ingredients'));
    }

    public function create()
    {
        $allIngredients = Ingredient::select('id', 'name', 'stock_qty', 'unit')
            ->orderBy('name')
            ->get();

        return view('ingredients.create', compact('allIngredients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|in:' . implode(',', \App\Models\Product::UNITS),
            'selling_price' => 'required|numeric|min:0',
            'stock_qty' => 'required|numeric|min:0',
        ]);

        Ingredient::create($data);

        return redirect()->route('ingredients.index')->with('success', 'Raw material added successfully.');
    }

    public function edit(Ingredient $ingredient)
    {
        $allIngredients = Ingredient::select('id', 'name', 'stock_qty', 'unit')
            ->where('id', '!=', $ingredient->id)
            ->orderBy('name')
            ->get();

        return view('ingredients.edit', compact('ingredient', 'allIngredients'));
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|in:' . implode(',', \App\Models\Product::UNITS),
            'selling_price' => 'required|numeric|min:0',
            'stock_qty' => 'required|numeric|min:0',
        ]);

        $ingredient->update($data);

        return redirect()->route('ingredients.index')->with('success', 'Raw material updated successfully.');
    }

    public function destroy(Ingredient $ingredient)
    {
        $ingredient->delete();

        return back()->with('success', 'Raw material removed.');
    }
}