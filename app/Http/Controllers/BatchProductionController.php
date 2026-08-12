<?php

namespace App\Http\Controllers;

use App\Models\BatchProduction;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BatchProductionController extends Controller
{
    public function index()
    {
        $products = Product::with('ingredients')->where('is_active', true)->orderBy('name')->get();
        $history = BatchProduction::with('product')->latest()->take(10)->get();

        return view('batches.index', compact('products', 'history'));
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'multiplier' => 'required|numeric|min:0.1',
            'notes' => 'nullable|string|max:500',
        ]);

        $product->load('ingredients');

        if ($product->ingredients->isEmpty()) {
            return back()->with('error', 'This product has no recipe. Add required ingredients first.');
        }

        // Check stock sufficiency
        foreach ($product->ingredients as $ingredient) {
            $needed = (float) $ingredient->pivot->quantity_required * (float) $data['multiplier'];
            if ($needed > (float) $ingredient->stock_qty) {
                return back()->with('error', "Not enough {$ingredient->name} in stock. Needed: {$needed} {$ingredient->unit}, Available: {$ingredient->stock_qty} {$ingredient->unit}.");
            }
        }

        $batch = DB::transaction(function () use ($product, $data) {
            $batchCost = 0.0;

            foreach ($product->ingredients as $ingredient) {
                $needed = round((float) $ingredient->pivot->quantity_required * (float) $data['multiplier'], 2);
                $batchCost += $needed * (float) $ingredient->cost_per_unit;

                $ingredient->decrement('stock_qty', $needed);

                StockMovement::create([
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'direction' => 'out',
                    'quantity' => $needed,
                    'reason' => "Used in batch production of {$product->name}",
                ]);
            }

            $outputQty = round((float) $product->output_qty_per_batch * (float) $data['multiplier'], 2);
            $costPerUnit = $outputQty > 0 ? round($batchCost / $outputQty, 2) : 0;

            $product->increment('stock_qty', $outputQty);

            StockMovement::create([
                'item_type' => 'product',
                'item_id' => $product->id,
                'direction' => 'in',
                'quantity' => $outputQty,
                'reason' => 'Batch production output',
            ]);

            return BatchProduction::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'multiplier' => $data['multiplier'],
                'output_qty' => $outputQty,
                'batch_cost' => round($batchCost, 2),
                'cost_per_unit' => $costPerUnit,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->route('batches.index')
            ->with('success', "Batch complete: produced {$batch->output_qty} {$product->unit} of {$product->name} for Rs. {$batch->batch_cost}.");
    }
}
