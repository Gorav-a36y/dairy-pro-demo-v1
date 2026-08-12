<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::today()->subDays(29);
        $to = $request->to ? Carbon::parse($request->to) : Carbon::today();

        $sales = Sale::whereBetween('sale_date', [$from, $to])->get();
        $totalRevenue = $sales->sum('total_amount');
        $totalOrders = $sales->count();
        $avgOrderValue = $totalOrders ? round($totalRevenue / $totalOrders, 2) : 0;

        $dailyTrend = DB::table('sales')
            ->select(DB::raw('DATE(sale_date) as day'), DB::raw('SUM(total_amount) as total'))
            ->whereBetween('sale_date', [$from, $to])
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $productPerformance = DB::table('sale_items')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->select('products.name', DB::raw('SUM(sale_items.quantity) as qty'), DB::raw('SUM(sale_items.subtotal) as revenue'))
            ->groupBy('products.name')
            ->orderByDesc('revenue')
            ->get();

        return view('reports.index', compact(
            'from', 'to', 'totalRevenue', 'totalOrders', 'avgOrderValue', 'dailyTrend', 'productPerformance'
        ));
    }

    /** Download a CSV of daily sales for the given date range (defaults to today). */
    public function export(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::today();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::today();

        $sales = Sale::with('customer')
            ->whereBetween('sale_date', [$from, $to])
            ->orderBy('sale_date')
            ->get();

        $filename = 'sales-report-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sales) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice No', 'Date', 'Customer', 'Subtotal', 'Discount', 'Total', 'Paid', 'Payment Method', 'Status']);

            foreach ($sales as $sale) {
                fputcsv($handle, [
                    $sale->invoice_no,
                    $sale->sale_date->format('Y-m-d'),
                    $sale->customer->name ?? 'Walk-in Customer',
                    $sale->subtotal,
                    $sale->discount,
                    $sale->total_amount,
                    $sale->paid_amount,
                    ucfirst($sale->payment_method),
                    ucfirst($sale->payment_status),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
