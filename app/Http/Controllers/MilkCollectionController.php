<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\MilkCollection;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MilkCollectionController extends Controller
{
    public function index(Request $request)
    {
        $collections = MilkCollection::with('supplier', 'ingredient')
            ->when($request->from, fn ($q) => $q->whereDate('collected_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('collected_at', '<=', $request->to))
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $todayQty = MilkCollection::whereDate('collected_at', now()->toDateString())->sum('quantity');
        $monthQty = MilkCollection::whereMonth('collected_at', now()->month)->whereYear('collected_at', now()->year)->sum('quantity');
        $monthSpend = MilkCollection::whereMonth('collected_at', now()->month)->whereYear('collected_at', now()->year)->sum('total_amount');

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($s) => ['id' => $s->id, 'label' => $s->name, 'sublabel' => $s->phone]);

        $ingredients = Ingredient::orderBy('name')->get()
            ->map(fn ($i) => ['id' => $i->id, 'label' => $i->name, 'sublabel' => $i->unit, 'unit' => $i->unit]);

        return view('milk-collections.index', compact('collections', 'todayQty', 'monthQty', 'monthSpend', 'suppliers', 'ingredients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'purchase_price' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:' . implode(',', MilkCollection::PAYMENT_METHODS),
            'notes' => 'nullable|string|max:500',
        ]);

        $ingredient = Ingredient::findOrFail($data['ingredient_id']);
        $totalAmount = round($data['quantity'] * $data['purchase_price'], 2);
        $paidAmount = $data['paid_amount'] ?? 0;

        DB::transaction(function () use ($data, $ingredient, $totalAmount, $paidAmount) {
            $collection = MilkCollection::create([
                'supplier_id' => $data['supplier_id'],
                'ingredient_id' => $ingredient->id,
                'quantity' => $data['quantity'],
                'unit' => $ingredient->unit,
                'purchase_price' => $data['purchase_price'],
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'payment_method' => $data['payment_method'],
                'collected_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Raw material stock goes up by the quantity collected, and its cost
            // basis is refreshed to this latest purchase price (used for production costing).
            $ingredient->increment('stock_qty', $data['quantity']);
            $ingredient->update(['cost_per_unit' => $data['purchase_price']]);

            SupplierTransaction::create([
                'supplier_id' => $data['supplier_id'],
                'type' => 'purchase',
                'amount' => $totalAmount,
                'reference' => 'Milk Collection #' . $collection->id,
                'notes' => $ingredient->name . ' — ' . $data['quantity'] . ' ' . $ingredient->unit,
                'transaction_date' => now()->toDateString(),
            ]);

            if ($paidAmount > 0) {
                SupplierTransaction::create([
                    'supplier_id' => $data['supplier_id'],
                    'type' => 'payment',
                    'amount' => $paidAmount,
                    'reference' => 'Milk Collection #' . $collection->id,
                    'notes' => 'Paid via ' . ucfirst($data['payment_method']),
                    'transaction_date' => now()->toDateString(),
                ]);
            }
        });

        return back()->with('success', 'Milk collection recorded and raw material stock updated.');
    }

    public function destroy(MilkCollection $milkCollection)
    {
        $milkCollection->delete();

        return back()->with('success', 'Entry removed.');
    }
}
