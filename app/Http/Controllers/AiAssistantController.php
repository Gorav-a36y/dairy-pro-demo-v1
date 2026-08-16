<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Services\OllamaCloudService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AiAssistantController extends Controller
{
    public function index(Request $request)
    {
        $history = $request->session()->get('ai_chat_history', []);

        return view('ai.index', compact('history'));
    }

    public function send(Request $request, OllamaCloudService $ollama)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $history = $request->session()->get('ai_chat_history', []);

        $systemPrompt = [
            'role' => 'system',
            'content' => 'You are the ' . (Setting::current()->dairy_name ?? 'DairyPro') . ' AI Assistant, built by GoravAI. You help '
                . 'the owner of a small dairy business (milk, yogurt, ghee, butter, cheese) with questions about production planning, '
                . 'milk collection from suppliers, raw material/product costing, batch sizing, khata (customer/supplier credit ledgers), '
                . 'and sales trends. Below is a live snapshot of today\'s real numbers from their system — use it directly to answer '
                . 'questions instead of saying you can\'t access the data. If something isn\'t in the snapshot (e.g. a specific past '
                . 'invoice, a date outside today), say plainly that you only have today\'s summary and suggest they check the Reports '
                . 'or Khata page for that detail. Keep answers short, practical, and easy to understand. Use plain language.'
                . "\n\n" . $this->businessSnapshot(),
        ];

        $history[] = ['role' => 'user', 'content' => $request->message];

        $reply = $ollama->chat(array_merge([$systemPrompt], $history));

        $history[] = ['role' => 'assistant', 'content' => $reply];

        // Keep only the last 20 messages to avoid unbounded session growth
        $history = array_slice($history, -20);

        $request->session()->put('ai_chat_history', $history);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['reply' => $reply]);
        }

        return back();
    }

    public function clear(Request $request)
    {
        $request->session()->forget('ai_chat_history');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    /** A compact, plain-text snapshot of today's real business numbers for the AI to ground its answers in. */
    protected function businessSnapshot(): string
    {
        $today = Carbon::today();
        $currency = Setting::current()->currency ?? 'Rs.';

        $todaySales = Sale::whereDate('sale_date', $today);
        $todaySalesCount = (clone $todaySales)->count();
        $todayRevenue = (clone $todaySales)->sum('total_amount');

        $outstandingKhata = max(
            CustomerTransaction::where('type', 'khata')->sum('amount') - CustomerTransaction::where('type', 'payment')->sum('amount'),
            0
        );
        $amountOwedToSuppliers = SupplierTransaction::where('type', 'purchase')->sum('amount') - SupplierTransaction::where('type', 'payment')->sum('amount');

        $recentSales = Sale::with('customer')->latest('sale_date')->latest('id')->take(5)->get()
            ->map(fn ($s) => "{$s->invoice_no}: {$currency} {$s->total_amount} (" . ($s->customer->name ?? 'Walk-in') . ", {$s->payment_status}, {$s->sale_date->format('M j')})")
            ->implode('; ');

        $products = Product::where('is_active', true)->get()
            ->map(fn ($p) => "{$p->name} ({$p->unit}, stock {$p->stock_qty}, sells {$currency}{$p->selling_price})")
            ->implode('; ');

        $ingredients = Ingredient::all()
            ->map(fn ($i) => "{$i->name} ({$i->unit}, stock {$i->stock_qty}, sells {$currency}{$i->selling_price})")
            ->implode('; ');

        $customersCount = Customer::count();
        $suppliersCount = Supplier::count();

        return <<<TXT
        LIVE BUSINESS SNAPSHOT (as of {$today->format('F j, Y')}):
        - Today's sales: {$todaySalesCount} orders, {$currency} {$todayRevenue} revenue
        - Total outstanding customer khata (money owed to the business): {$currency} {$outstandingKhata}
        - Total owed to suppliers (khata): {$currency} {$amountOwedToSuppliers}
        - Registered customers: {$customersCount}, Suppliers: {$suppliersCount}
        - Products (name, unit, current stock, selling price): {$products}
        - Raw materials (name, unit, current stock, selling price): {$ingredients}
        - Last 5 sales: {$recentSales}
        TXT;
    }
}