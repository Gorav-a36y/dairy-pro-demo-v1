<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Waste;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WasteController extends Controller
{
    public function index(Request $request)
    {
        $wastes = Waste::when(
                $request->filled('from'),
                fn ($q) => $q->whereDate('wasted_at', '>=', $request->from)
            )
            ->when(
                $request->filled('to'),
                fn ($q) => $q->whereDate('wasted_at', '<=', $request->to)
            )
            ->orderByDesc('wasted_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $todayCount = Waste::whereDate('wasted_at', today())->count();

        $monthCount = Waste::whereMonth('wasted_at', now()->month)
            ->whereYear('wasted_at', now()->year)
            ->count();

        $monthItems = Waste::whereMonth('wasted_at', now()->month)
            ->whereYear('wasted_at', now()->year)
            ->get(['item_type', 'item_id'])
            ->unique(fn ($w) => $w->item_type . '-' . $w->item_id)
            ->count();

        $items = $this->combinedItemOptions();

        return view('waste.index', compact(
            'wastes', 'todayCount', 'monthCount', 'monthItems', 'items'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_key' => 'required|string',
            'quantity' => 'required|numeric|min:0.01',
            'note' => 'required|string|max:500',
        ], [], [
            'item_key' => 'Raw Material / Product',
            'quantity' => 'Waste Quantity',
            'note' => 'Note',
        ]);

        [$type, $id] = $this->parseItemKey($data['item_key']);

        $model = $type === Waste::TYPE_RAW_MATERIAL
            ? RawMaterial::find($id)
            : Product::find($id);

        if (!$model) {
            return back()->withInput()->withErrors([
                'item_key' => 'Selected item could not be found.',
            ]);
        }

        if ($data['quantity'] > (float) $model->stock_qty) {
            return back()->withInput()->withErrors([
                'quantity' => 'Waste quantity cannot be greater than current stock (' . number_format($model->stock_qty, 2) . ' ' . $model->unit . ').',
            ]);
        }

        DB::transaction(function () use ($data, $type, $model) {
            Waste::create([
                'item_type' => $type,
                'item_id' => $model->id,
                'item_name' => $model->name,
                'unit' => $model->unit,
                'quantity' => $data['quantity'],
                'note' => $data['note'],
                'wasted_at' => now()->toDateString(),
            ]);

            $model->decrement('stock_qty', $data['quantity']);
        });

        return back()->with('success', 'Waste recorded and stock updated.');
    }

    public function exportCsv(Request $request)
    {
        $wastes = Waste::when(
                !empty($request->get('from')),
                fn ($q) => $q->whereDate('wasted_at', '>=', $request->get('from'))
            )
            ->when(
                !empty($request->get('to')),
                fn ($q) => $q->whereDate('wasted_at', '<=', $request->get('to'))
            )
            ->orderByDesc('wasted_at')
            ->orderByDesc('id')
            ->get();

        $filename = 'waste-history-' . now()->format('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($wastes) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Type', 'Item', 'Quantity', 'Unit', 'Note']);

            foreach ($wastes as $waste) {
                fputcsv($file, [
                    $waste->wasted_at->format('M j, Y'),
                    $waste->isRawMaterial() ? 'Raw Material' : 'Product',
                    $waste->item_name,
                    number_format($waste->quantity, 2),
                    $waste->unit,
                    $waste->note,
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    private function combinedItemOptions()
    {
        $rawMaterials = RawMaterial::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id' => Waste::TYPE_RAW_MATERIAL . ':' . $r->id,
                'label' => $r->name,
                'sublabel' => 'Raw Material • Stock: ' . number_format($r->stock_qty, 2) . ' ' . $r->unit,
                'unit' => $r->unit,
                'stock' => (float) $r->stock_qty,
                'type' => 'Raw Material',
            ]);

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => Waste::TYPE_PRODUCT . ':' . $p->id,
                'label' => $p->name,
                'sublabel' => 'Product • Stock: ' . number_format($p->stock_qty, 2) . ' ' . $p->unit,
                'unit' => $p->unit,
                'stock' => (float) $p->stock_qty,
                'type' => 'Product',
            ]);

        return $rawMaterials->concat($products)->sortBy('label')->values();
    }

    private function parseItemKey(string $key): array
    {
        $parts = explode(':', $key, 2);

        return [$parts[0] ?? '', $parts[1] ?? 0];
    }
}