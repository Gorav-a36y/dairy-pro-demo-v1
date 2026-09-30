<?php

namespace App\Http\Controllers;

use App\Models\BatchProduction;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchProductionController extends Controller
{
    // NOTE: index() was removed — Batch Production is no longer a separate
    // page. Running a batch and viewing recent batch history both now live
    // on the Products page (see ProductController::index and
    // resources/views/products/index.blade.php). This controller keeps only
    // the actions that page's forms/links still call.

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'multiplier' => 'required|numeric|min:0.1',
            'notes' => 'nullable|string|max:500',
        ]);

        // FIXED: was ->load('ingredients')
        $product->load('rawMaterials');

        if ($product->rawMaterials->isEmpty()) {
            return back()->with('error', 'This product has no recipe. Add required raw materials first.');
        }

        // Quick check up front for a fast, friendly error message
        foreach ($product->rawMaterials as $rawMaterial) {
            $needed = (float) $rawMaterial->pivot->quantity_required * (float) $data['multiplier'];
            if ($needed > (float) $rawMaterial->stock_qty) {
                return back()->with('error', "Not enough {$rawMaterial->name} in stock. Needed: {$needed} {$rawMaterial->unit}, Available: {$rawMaterial->stock_qty} {$rawMaterial->unit}.");
            }
        }

        try {
            $batch = DB::transaction(function () use ($product, $data) {
                $batchCost = 0.0;

                // Re-fetch with a lock inside the transaction so two simultaneous
                // batch runs can't both pass the stock check and oversell
                $lockedMaterials = RawMaterial::whereIn('id', $product->rawMaterials->pluck('id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($product->rawMaterials as $rawMaterial) {
                    $locked = $lockedMaterials[$rawMaterial->id];
                    $needed = round((float) $rawMaterial->pivot->quantity_required * (float) $data['multiplier'], 2);

                    if ($needed > (float) $locked->stock_qty) {
                        throw new \RuntimeException("Not enough {$locked->name} in stock.");
                    }

                    // FIXED: was (float) $ingredient->cost_per_unit
                    $batchCost += $needed * $locked->currentCostPerUnit();

                    $locked->decrement('stock_qty', $needed);

                    StockMovement::create([
                        'item_type' => 'raw_material', // FIXED: was 'ingredient'
                        'item_id' => $locked->id,
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
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('products.index')
            ->with('success', "Batch complete: produced {$batch->output_qty} {$product->unit} of {$product->name} for Rs. {$batch->batch_cost}.");
    }

    public function exportCsv(Request $request)
    {
        $batches = BatchProduction::with('product')
            ->when(
                !empty($request->get('from')),
                fn ($q) => $q->whereDate('created_at', '>=', $request->get('from'))
            )
            ->when(
                !empty($request->get('to')),
                fn ($q) => $q->whereDate('created_at', '<=', $request->get('to'))
            )
            ->latest()
            ->get();

        $filename = 'batch-production-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($batches) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Product', 'Multiplier', 'Output', 'Batch Cost', 'Cost / Unit', 'Date']);

            foreach ($batches as $batch) {
                fputcsv($file, [
                    $batch->product->name ?? 'Deleted product',
                    $batch->multiplier . 'x',
                    number_format($batch->output_qty, 1),
                    number_format($batch->batch_cost, 0),
                    number_format($batch->cost_per_unit, 2),
                    $batch->created_at->format('M j, g:i A'),
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}