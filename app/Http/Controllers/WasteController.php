<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Waste;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'item_key'  => 'required|string',
            'quantity'  => 'required|numeric|min:0.01',
            'wasted_at' => 'required|date|before_or_equal:today',
            'note'      => 'nullable|string|max:500',
        ], [
            'wasted_at.before_or_equal' => 'The waste date cannot be in the future.',
        ], [
            'item_key'  => 'Raw Material / Product',
            'quantity'  => 'Waste Quantity',
            'wasted_at' => 'Date',
            'note'      => 'Note',
        ]);

        // Empty or spaces-only note is saved as NULL
        $note = isset($data['note']) ? trim($data['note']) : null;
        $note = ($note === '') ? null : $note;

        // Stock is stored with 2 decimals, so round the quantity first.
        $qty = round((float) $data['quantity'], 2);

        [$type, $id] = $this->parseItemKey($data['item_key']);

        // Only these two types are allowed. Anything else is rejected.
        $modelClass = match ($type) {
            Waste::TYPE_RAW_MATERIAL => RawMaterial::class,
            Waste::TYPE_PRODUCT      => Product::class,
            default                  => null,
        };

        if (! $modelClass || ! is_numeric($id)) {
            throw ValidationException::withMessages([
                'item_key' => 'Selected item could not be found.',
            ]);
        }

        DB::transaction(function () use ($data, $qty, $note, $type, $id, $modelClass) {
            // Lock the item and check stock again, so two entries saved at the
            // same moment can never push stock below zero.
            $model = $modelClass::whereKey($id)->lockForUpdate()->first();

            if (! $model) {
                throw ValidationException::withMessages([
                    'item_key' => 'Selected item could not be found.',
                ]);
            }

            if ($qty > (float) $model->stock_qty) {
                throw ValidationException::withMessages([
                    'quantity' => 'Waste quantity cannot be greater than current stock (' . number_format($model->stock_qty, 2) . ' ' . $model->unit . ').',
                ]);
            }

            Waste::create([
                'item_type' => $type,
                'item_id'   => $model->id,
                'item_name' => $model->name,
                'unit'      => $model->unit,
                'quantity'  => $qty,
                'note'      => $note,
                'wasted_at' => $data['wasted_at'],
            ]);

            $model->decrement('stock_qty', $qty);
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
                    $waste->note ?? '',
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
