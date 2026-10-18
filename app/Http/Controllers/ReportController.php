<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MilkCollection;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Worker;
use App\Models\Waste;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $fromInput = $data['from'] ?? null;
        $toInput = $data['to'] ?? null;

        /* ── Date range ── */
        $settings = Setting::current();
        $businessNow = $settings->now();
        $businessTimezone = $settings->timezone();
        $from = $fromInput
            ? Carbon::createFromFormat('Y-m-d', $fromInput, $businessTimezone)->startOfDay()
            : $businessNow->copy()->startOfMonth()->startOfDay();
        $to = $toInput
            ? Carbon::createFromFormat('Y-m-d', $toInput, $businessTimezone)->endOfDay()
            : $businessNow->copy()->endOfMonth()->endOfDay();
        $storageFrom = $from->copy()->setTimezone(config('app.timezone'));
        $storageTo = $to->copy()->setTimezone(config('app.timezone'));

        /* ── Sales ── */
        $sales = Sale::whereBetween('sale_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get();
        $totalRevenue   = (float) $sales->sum('total_amount');
        $totalInvoices  = $sales->count();
        $avgInvoiceValue = $totalInvoices ? round($totalRevenue / $totalInvoices, 2) : 0;

        // A sale counts as credit when it was not fully paid at the time of sale.
        $isCredit     = fn ($sale) => round((float) $sale->total_amount - (float) $sale->paid_amount, 2) > 0;
        $cashSales    = (float) $sales->reject($isCredit)->sum('total_amount');
        $creditSales  = (float) $sales->filter($isCredit)->sum('total_amount');

        /* ── Payment method breakdown ── */
        $paymentMethods = $sales->groupBy('payment_method')
            ->map(fn ($group) => (float) $group->sum('total_amount'))
            ->sortDesc();

        /* ── Daily revenue trend with invoice count ──
           Built from every day in the range (not just days that have a sale
           row) so the chart always has a full, visible x-axis — a month
           with zero sales used to produce an empty labels array, which made
           Chart.js render nothing at all instead of a flat/empty line. */
        $salesByDay = DB::table('sales')
            ->select(
                DB::raw('DATE(sale_date) as day'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as invoices')
            )
            ->whereBetween('sale_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $dailyTrend = collect();
        for ($cursor = $from->copy()->startOfDay(); $cursor->lte($to); $cursor->addDay()) {
            $day = $cursor->format('Y-m-d');
            $row = $salesByDay->get($day);

            $dailyTrend->push((object) [
                'day' => $day,
                'total' => $row ? (float) $row->total : 0.0,
                'invoices' => $row ? (int) $row->invoices : 0,
            ]);
        }

        /* ── Milk Collections ── */
        $milkCollections = MilkCollection::whereHas('rawMaterial', fn ($q) => $q->where('is_milk', true))
            ->whereBetween('collected_at', [$storageFrom, $storageTo])
            ->get();
        $totalMilkLiters = (float) $milkCollections->sum('quantity');
        $avgFat          = $milkCollections->count() ? round((float) $milkCollections->avg('fat'), 2) : 0;
        $avgSnf          = $milkCollections->count() ? round((float) $milkCollections->avg('snf'), 2) : 0;
        $totalMilkCost   = (float) $milkCollections->sum('total_amount');
        $totalMilkPaid   = (float) $milkCollections->sum('paid_amount');
        $milkBalanceDue  = round($totalMilkCost - $totalMilkPaid, 2);

        // ── Milk Waste ──
        $milkWasted = (float) DB::table('wastes')
            ->join('raw_materials', 'raw_materials.id', '=', 'wastes.item_id')
            ->where('wastes.item_type', Waste::TYPE_RAW_MATERIAL)
            ->where('raw_materials.is_milk', true)
            ->whereBetween('wastes.wasted_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->sum('wastes.quantity');

        $netMilk = round($totalMilkLiters - $milkWasted, 2);

        /* ── Daily milk trend with averages per day ── */
        $dailyMilk = DB::table('milk_collections')
            ->join('raw_materials', 'raw_materials.id', '=', 'milk_collections.raw_material_id')
            ->where('raw_materials.is_milk', true)
            ->select(
                DB::raw('DATE(milk_collections.collected_at) as day'),
                DB::raw('SUM(milk_collections.quantity) as liters'),
                DB::raw('SUM(milk_collections.total_amount) as cost'),
                DB::raw('AVG(milk_collections.fat) as avg_fat'),
                DB::raw('AVG(milk_collections.snf) as avg_snf')
            )
            ->whereBetween('milk_collections.collected_at', [$storageFrom, $storageTo])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        /* ── Gross Profit ──
           Include every raw-material collection, not only milk collections. */
        $totalRawMaterialCost = (float) MilkCollection::whereBetween('collected_at', [$storageFrom, $storageTo])
            ->sum('total_amount');
        $grossProfit = round($totalRevenue - $totalRawMaterialCost, 2);

        /* ── Product Performance ── */
        $productPerformance = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sale_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->select(
                'sale_items.item_name as name',
                DB::raw('SUM(sale_items.quantity) as qty'),
                DB::raw('SUM(sale_items.subtotal) as revenue')
            )
            ->groupBy('sale_items.item_name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        /* ── Top Suppliers by milk volume ── */
        $topSuppliers = DB::table('milk_collections')
            ->join('suppliers', 'suppliers.id', '=', 'milk_collections.supplier_id')
            ->join('raw_materials', 'raw_materials.id', '=', 'milk_collections.raw_material_id')
            ->where('raw_materials.is_milk', true)
            ->whereBetween('milk_collections.collected_at', [$storageFrom, $storageTo])
            ->select(
                'suppliers.name',
                DB::raw('SUM(milk_collections.quantity) as total_qty'),
                DB::raw('SUM(milk_collections.total_amount) as total_cost'),
                DB::raw('AVG(milk_collections.fat) as avg_fat')
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        /* ── Khata Health ── */
        $totalCustomerOutstanding = (float) Customer::sum('balance');
        $overdueCustomers = Customer::where('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit(5)
            ->get(['name', 'phone', 'balance']);

        /* ── Supplier Health ── */
        $totalSupplierOwed = (float) Supplier::sum('balance');
        $overdueSuppliers = Supplier::where('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit(5)
            ->get(['name', 'balance']);

        /* ── Workers: total monthly salary commitment for active staff ── */
        $totalWorkersSalary = (float) Worker::where('is_active', true)->sum('monthly_salary');

        return view('reports.index', compact(
            'from', 'to',
            'totalRevenue', 'totalInvoices', 'avgInvoiceValue',
            'cashSales', 'creditSales', 'paymentMethods',
            'dailyTrend', 'dailyMilk',
            'totalMilkLiters', 'avgFat', 'avgSnf',
            'totalMilkCost', 'totalMilkPaid', 'milkBalanceDue',
            'milkWasted', 'netMilk',
            'grossProfit',
            'productPerformance', 'topSuppliers',
            'totalCustomerOutstanding', 'overdueCustomers',
            'totalSupplierOwed', 'overdueSuppliers',
            'totalWorkersSalary'
        ));
    }

    /** Download a CSV of daily sales for the given date range. */
    public function export(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $fromInput = $data['from'] ?? null;
        $toInput = $data['to'] ?? null;

        $settings = Setting::current();
        $businessNow = $settings->now();
        $businessTimezone = $settings->timezone();
        $from = $fromInput
            ? Carbon::createFromFormat('Y-m-d', $fromInput, $businessTimezone)
            : $businessNow->copy()->startOfDay();
        $to = $toInput
            ? Carbon::createFromFormat('Y-m-d', $toInput, $businessTimezone)
            : $businessNow->copy()->startOfDay();

        $sales = Sale::with('customer')
            ->whereBetween('sale_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('sale_date')
            ->get();

        $filename = 'sales-report-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sales) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice No', 'Date', 'Customer', 'Subtotal', 'Discount', 'Total', 'Paid', 'Payment Method']);

            foreach ($sales as $sale) {
                fputcsv($handle, [
                    $this->csvSafe($sale->invoice_no),
                    $sale->sale_date->format('Y-m-d'),
                    $this->csvSafe($sale->customer->name ?? 'Walk-in Customer'),
                    $sale->subtotal,
                    $sale->discount,
                    $sale->total_amount,
                    $sale->paid_amount,
                    $this->csvSafe(ucfirst($sale->payment_method)),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
