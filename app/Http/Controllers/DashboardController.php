<?php

namespace App\Http\Controllers;

use App\Models\CustomerTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $todaySalesCount = Sale::whereDate('sale_date', $today)->count();
        $todayRevenue = Sale::whereDate('sale_date', $today)->sum('total_amount');

        $milkSoldToday = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereDate('sales.sale_date', $today)
            ->where('products.unit', 'Liter')
            ->sum('sale_items.quantity');

        $outstandingKhata = max(
            (CustomerTransaction::where('type', 'khata')->sum('amount'))
            - (CustomerTransaction::where('type', 'payment')->sum('amount')),
            0
        );

        $trend = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            $total = Sale::whereDate('sale_date', $date)->sum('total_amount');

            return [
                'label' => $date->format('D'),
                'total' => (float) $total,
            ];
        });

        return view('dashboard.index', compact(
            'todaySalesCount', 'todayRevenue', 'milkSoldToday', 'outstandingKhata', 'trend'
        ));
    }
}
