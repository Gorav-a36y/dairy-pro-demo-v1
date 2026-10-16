<?php

namespace App\Http\Controllers;

use App\Models\BatchProduction;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchProductionController extends Controller
{
    /** Largest value that fits in the decimal(12,2) stock / output columns. */
    private const MAX_AMOUNT = 9999999999.99;

    // NOTE: index() was removed — Batch Production is no longer a separate
    // page. Running a batch and viewing recent batch history both now live
    // on the Products page (see ProductController::index and
    // resources/views/products/index.blade.php). This controller keeps only
    // the actions that page's forms/links still call.

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'multiplier' => 'required|numeric|min:0.1|max:99999.99',
        ]);

        $multiplier = round((float) $data['multiplier'], 2);

        if (!$product->is_active) {
            return back()->with('error', "{$product->name} is inactive. Turn it on before running a batch.");
        }

        try {
            $batch = DB::transaction(function () use ($product, $multiplier) {
                // Read the recipe inside the transaction so we never use a stale one.
                $recipe = $product->rawMaterials()->get();

                if ($recipe->isEmpty()) {
                    throw new \RuntimeException('This product has no recipe. Add required raw materials first.');
                }

                // Lock the raw materials so two simultaneous batch runs can't
                // both pass the stock check and use the same stock twice.
                $materials = RawMaterial::whereIn('id', $recipe->pluck('id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // 1) Check everything first. Nothing is written until all checks pass.
                $lines = [];
                foreach ($recipe as $item) {
                    $material = $materials[$item->id];
                    $needed = round((float) $item->pivot->quantity_required * $multiplier, 2);

                    if ($needed <= 0 || $needed > self::MAX_AMOUNT) {
                        throw new \RuntimeException("This multiplier is too small or too large for {$material->name}.");
                    }

                    if ($needed > (float) $material->stock_qty) {
                        throw new \RuntimeException("Not enough {$material->name} in stock. Needed: {$needed} {$material->unit}, Available: {$material->stock_qty} {$material->unit}.");
                    }

                    $lines[] = [$material, $needed];
                }

                $outputQty = round((float) $product->output_qty_per_batch * $multiplier, 2);

                if ($outputQty <= 0 || $outputQty > self::MAX_AMOUNT) {
                    throw new \RuntimeException('This multiplier makes an output that is too small or too large.');
                }

                // 2) Deduct the raw materials.
                foreach ($lines as [$material, $needed]) {
                    $material->decrement('stock_qty', $needed);
                }

                // 3) Add the finished product to stock.
                $product->increment('stock_qty', $outputQty);

                return BatchProduction::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'multiplier' => $multiplier,
                    'output_qty' => $outputQty,
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('products.index')
            ->with('success', "Batch complete: produced {$batch->output_qty} {$product->unit} of {$product->name}.");
    }

    public function exportCsv(Request $request)
    {
        $settings = Setting::current();

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

        $callback = function () use ($batches, $settings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Product', 'Multiplier', 'Output', 'Date']);

            // Plain numbers (no thousands commas) so spreadsheets can calculate with them.
            foreach ($batches as $batch) {
                fputcsv($file, [
                    $batch->product->name ?? 'Deleted product',
                    (float) $batch->multiplier,
                    (float) $batch->output_qty,
                    $settings->localize($batch->created_at)->format('M j, g:i A'),
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
