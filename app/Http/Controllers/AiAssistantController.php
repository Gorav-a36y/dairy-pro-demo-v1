<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\MilkCollection;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Waste;
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
            // The browser (JavaScript) sends its own date and timezone so the AI always knows "today".
            'client_datetime' => ['nullable', 'date_format:Y-m-d\TH:i:s'],
            'client_timezone' => ['nullable', 'timezone:all'],
        ]);

        $now = $this->resolveNow($request);

        $history = $request->session()->get('ai_chat_history', []);

        $systemPrompt = [
            'role' => 'system',
            'content' => $this->systemPrompt($now) . "\n\n" . $this->businessSnapshot($now),
        ];

        $history[] = ['role' => 'user', 'content' => $request->message];

        try {
            $reply = $this->cleanReply((string) $ollama->chat(array_merge([$systemPrompt], $history)));
        } catch (\Throwable $e) {
            report($e);
            $reply = 'Sorry, I could not get an answer right now. Please try again in a moment.';
        }

        if ($reply === '') {
            $reply = 'Sorry, I did not get a clear answer. Please ask again in a different way.';
        }

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

    /* ------------------------------------------------------------------
     |  Date / time
     * ------------------------------------------------------------------ */

    protected function businessTimezone(): string
    {
        return Setting::current()->timezone() ?: config('app.timezone');
    }

    /** Use the browser's date and time when it was sent. Otherwise use the dairy's own timezone from Settings. */
    protected function resolveNow(Request $request): Carbon
    {
        $fallbackTz = $this->businessTimezone();

        if ($request->filled('client_datetime')) {
            try {
                $tz = $request->input('client_timezone') ?: $fallbackTz;

                return Carbon::createFromFormat('Y-m-d\TH:i:s', $request->input('client_datetime'), $tz);
            } catch (\Throwable $e) {
                // fall through to the default below
            }
        }

        return Carbon::now($fallbackTz);
    }

    /* ------------------------------------------------------------------
     |  System prompt
     * ------------------------------------------------------------------ */

    protected function systemPrompt(Carbon $now): string
    {
        $dairy = Setting::current()->dairy_name ?? 'DairyPro';
        $currency = Setting::current()->currency ?? 'Rs.';
        $dateText = $now->format('l, j F Y, g:i A') . ' (' . $now->getTimezone()->getName() . ')';

        $lines = [
            "ROLE",
            "You are the {$dairy} AI Assistant, built by GoravAI. You are a trusted business advisor and data analyst for the owner of a small dairy business in Pakistan. You help the owner understand their numbers, spot problems early, and make better decisions about sales, production, buying milk, stock, khata and waste.",
            "",
            "TODAY",
            "The current date and time is: {$dateText}.",
            "Always use this date when the owner says today, yesterday, this week or this month. Never guess the date.",
            "",
            "HOW THE BUSINESS WORKS",
            "- The dairy sells milk, yogurt (dahi), ghee, butter and cheese. All amounts are in {$currency}.",
            "- Raw materials (like raw milk) are bought from suppliers and farmers on the Milk Collection page. Raw materials can also be sold directly.",
            "- Products are made from raw materials in production batches, using a recipe.",
            "- Khata is a credit ledger. Customer khata is money customers owe the business. Supplier khata is money the business owes suppliers.",
            "- Partial Khata means part of the bill was paid and the rest went on khata.",
            "- Daily customers take an item every day and usually pay at month end.",
            "- Waste entries remove stock, so waste is lost money.",
            "- Sales revenue means the bill total. Cash received can be higher than the bill because change is given back, so never count change as revenue.",
            "",
            "HOW TO USE THE DATA",
            "- The live business data below is your only source of truth. Never invent numbers, names, invoices, prices or stock.",
            "- If the answer is not in the data, say so plainly and tell the owner which page to check (Reports, Khata, Milk Collection, Waste, Sales History).",
            "- Do the maths carefully. Margin percent = (selling price minus cost) divided by selling price. Estimated costs cover raw materials only. They do not include labour, fuel or packing, so say that when you talk about margins.",
            "- If a number is zero or missing, say the data is missing. Do not draw big conclusions from very little data.",
            "- Do not make up market prices, tax rules or legal advice.",
            "",
            "HOW TO GIVE GOOD ADVICE",
            "When the owner asks for advice, or asks how the business is doing, do not just repeat numbers. Follow these steps:",
            "1. Answer the question directly in the first one or two sentences.",
            "2. Show only the key numbers that support the answer.",
            "3. Point out what matters most: low stock, high khata, falling sales, low margins, waste, milk cost going up.",
            "4. End with one to three clear actions the owner can do today or this week. Put the biggest money impact first.",
            "Be honest. If something looks bad, say it politely and clearly. Do not flatter. Compare this month with last month when the data allows it. If the question is not about the business, answer briefly and steer back to the business. If the question is unclear, ask one short question.",
            "",
            "STYLE RULES (very important)",
            "- Write plain text only. Never use markdown. No asterisks, no hash signs, no backticks, no bold, no tables.",
            "- Never leave a blank line between lines. Use a single line break only.",
            "- To organize an answer, write a short label that ends with a colon on its own line. Under it, write lines that start with a dash and a space, or numbered steps like 1. and 2.",
            "- Use short sentences and simple words, as if you are talking to a busy owner who is not technical.",
            "- Keep answers under about 150 words unless the owner asks for more detail.",
            "- Write money like Rs. 12,500 with commas. Write dates like 5 Oct 2026.",
            "- Reply in the same language the owner writes in (English, Urdu or Roman Urdu).",
            "",
            "EXAMPLE OF THE STYLE (do not copy these numbers)",
            "Owner asks: How is business today?",
            "Good answer:",
            "Today is going well. You have 6 sales for Rs. 18,400 so far.",
            "Key points:",
            "- Yogurt is your top seller today.",
            "- Raw milk stock is low, only 40 Liter left.",
            "- Rs. 52,000 is still owed to you on khata.",
            "What to do:",
            "1. Collect milk this evening so tomorrow's batch can run.",
            "2. Call your two biggest khata customers this week.",
        ];

        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------
     |  Reply cleaning (no stars, no blank-line gaps)
     * ------------------------------------------------------------------ */

    protected function cleanReply(string $text): string
    {
        $text = str_replace(["\r\n", "\r", '\\n'], "\n", $text);

        // Bold / italic markers
        $text = preg_replace('/(\*\*|__)(.+?)\1/s', '$2', $text);

        // 5 * 3 -> 5 x 3
        $text = preg_replace('/(?<=\d)\s*\*\s*(?=\d)/', ' x ', $text);

        // Star or dot bullets -> dash bullets
        $text = preg_replace('/^[ \t]*[\*•]\s+/m', '- ', $text);

        // Headings (#), leftover stars and backticks
        $text = preg_replace('/^[ \t]*#{1,6}\s*/m', '', $text);
        $text = str_replace(['*', '`'], '', $text);

        // Table separator lines like |---|---| or ---
        $text = preg_replace('/^[ \t]*\|?[-: |]{3,}\|?[ \t]*$/m', '', $text);

        // Trailing spaces, then collapse blank lines into a single line break
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace("/\n{2,}/", "\n", $text);

        return trim($text);
    }

    /* ------------------------------------------------------------------
     |  Live business snapshot for the AI
     * ------------------------------------------------------------------ */

    protected function businessSnapshot(Carbon $now): string
    {
        $currency = Setting::current()->currency ?? 'Rs.';
        $today = $now->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $weekStart = $now->copy()->subDays(6)->toDateString();
        $lastMonth = $now->copy()->subMonthNoOverflow();
        $lastStart = $lastMonth->copy()->startOfMonth()->toDateString();
        $lastEnd = $lastMonth->toDateString();

        $header = "LIVE BUSINESS DATA (as of {$now->format('j F Y, g:i A')}, amounts in {$currency}). "
            . "This month means {$now->format('j M')} back to 1 {$now->format('M Y')}.";

        $sections = [$header];

        // ---------------- SALES ----------------
        $sections[] = $this->section('SALES', function () use ($currency, $today, $weekStart, $monthStart, $lastStart, $lastEnd) {
            $todaySales = Sale::whereDate('sale_date', $today);
            $todayCount = (clone $todaySales)->count();
            $todayRevenue = (clone $todaySales)->sum('total_amount');
            $todayDiscount = (clone $todaySales)->sum('discount');
            $todayUnpaid = (clone $todaySales)->whereColumn('paid_amount', '<', 'total_amount')->count();

            $week = $this->between(Sale::query(), 'sale_date', $weekStart, $today);
            $month = $this->between(Sale::query(), 'sale_date', $monthStart, $today);
            $last = $this->between(Sale::query(), 'sale_date', $lastStart, $lastEnd);

            $monthCount = (clone $month)->count();
            $monthRevenue = (float) (clone $month)->sum('total_amount');
            $avgOrder = $monthCount > 0 ? $monthRevenue / $monthCount : 0;

            $lines = [];
            $lines[] = "- Today: {$todayCount} orders, revenue {$currency} " . $this->n($todayRevenue)
                . ", discounts given {$currency} " . $this->n($todayDiscount) . ", {$todayUnpaid} orders on khata/unpaid";
            $lines[] = "- Last 7 days: " . (clone $week)->count() . " orders, revenue {$currency} " . $this->n((clone $week)->sum('total_amount'));
            $lines[] = "- This month so far: {$monthCount} orders, revenue {$currency} " . $this->n($monthRevenue)
                . ", average order {$currency} " . $this->n($avgOrder)
                . ", total discounts {$currency} " . $this->n((clone $month)->sum('discount'));
            $lines[] = "- Same period last month: " . (clone $last)->count() . " orders, revenue {$currency} " . $this->n((clone $last)->sum('total_amount'));

            $mix = (clone $month)
                ->selectRaw('payment_method, COUNT(*) as orders, SUM(total_amount) as revenue')
                ->groupBy('payment_method')
                ->orderByDesc('revenue')
                ->get()
                ->map(fn ($r) => str_replace('_', ' ', $r->payment_method) . ": {$r->orders} orders, {$currency} " . $this->n($r->revenue))
                ->implode('; ');
            $lines[] = "- Payment methods this month: " . ($mix ?: 'no sales yet');

            $top = $this->between(
                SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id'),
                'sales.sale_date', $monthStart, $today
            )
                ->selectRaw('sale_items.item_name, sale_items.unit, SUM(sale_items.quantity) as qty, SUM(sale_items.subtotal) as revenue')
                ->groupBy('sale_items.item_name', 'sale_items.unit')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                ->map(fn ($r) => "{$r->item_name} ({$this->n($r->qty, 1)} {$r->unit}, {$currency} " . $this->n($r->revenue) . ")")
                ->implode('; ');
            $lines[] = "- Top sellers this month by revenue: " . ($top ?: 'no sales yet');

            $recent = Sale::with('customer')->latest('sale_date')->latest('id')->take(5)->get()
                ->map(fn ($s) => "{$s->invoice_no}: {$currency} " . $this->n($s->total_amount) . " (" . ($s->customer->name ?? 'Walk-in') . ", " . ((float) $s->paid_amount < (float) $s->total_amount ? 'sold on khata' : 'paid in full') . ", {$s->sale_date->format('j M')})")
                ->implode('; ');
            $lines[] = "- Last 5 sales: " . ($recent ?: 'none');

            return implode("\n", $lines);
        });

        // ---------------- MILK COLLECTION ----------------
        $sections[] = $this->section('MILK COLLECTION AND BUYING', function () use ($currency, $today, $monthStart) {
            $rows = $this->between(MilkCollection::query(), 'collected_at', $monthStart, $today)
                ->selectRaw('raw_material_id, unit, SUM(quantity) as qty, SUM(total_amount) as spend, AVG(purchase_price) as avg_price')
                ->groupBy('raw_material_id', 'unit')
                ->get();

            $names = RawMaterial::whereIn('id', $rows->pluck('raw_material_id'))->pluck('name', 'id');

            $todaySpend = MilkCollection::whereDate('collected_at', $today)->sum('total_amount');

            $lines = [];
            $lines[] = "- Bought today: {$currency} " . $this->n($todaySpend);
            $lines[] = "- Bought this month: " . ($rows->isEmpty() ? 'nothing recorded yet' : $rows->map(
                fn ($r) => ($names[$r->raw_material_id] ?? 'Unknown') . ": " . $this->n($r->qty, 1) . " {$r->unit}, spent {$currency} "
                    . $this->n($r->spend) . ", average price {$currency} " . $this->n($r->avg_price, 2) . " per {$r->unit}"
            )->implode('; '));

            return implode("\n", $lines);
        });

        // ---------------- WASTE ----------------
        $sections[] = $this->section('WASTE THIS MONTH', function () use ($monthStart, $today) {
            $count = $this->between(Waste::query(), 'wasted_at', $monthStart, $today)->count();

            $top = $this->between(Waste::query(), 'wasted_at', $monthStart, $today)
                ->selectRaw('item_name, unit, SUM(quantity) as qty')
                ->groupBy('item_name', 'unit')
                ->orderByDesc('qty')
                ->limit(5)
                ->get()
                ->map(fn ($r) => "{$r->item_name} ({$this->n($r->qty, 1)} {$r->unit})")
                ->implode('; ');

            return "- {$count} waste entries. Most wasted: " . ($top ?: 'nothing wasted');
        });

        // ---------------- STOCK ----------------
        $sections[] = $this->section('STOCK AND PRICES', function () use ($currency) {
            $products = Product::where('is_active', true)->with('rawMaterials')->orderBy('name')->get();

            $productLines = $products->map(function ($p) use ($currency) {
                $cost = null;

                if ($p->rawMaterials->isNotEmpty() && (float) $p->output_qty_per_batch > 0) {
                    $batchCost = 0;
                    $known = true;

                    foreach ($p->rawMaterials as $rm) {
                        $unitCost = $rm->currentCostPerUnit();
                        if ($unitCost <= 0) {
                            $known = false;
                            break;
                        }
                        $batchCost += $unitCost * (float) $rm->pivot->quantity_required;
                    }

                    if ($known) {
                        $cost = $batchCost / (float) $p->output_qty_per_batch;
                    }
                }

                $line = "{$p->name} (stock {$this->n($p->stock_qty, 1)} {$p->unit}, price {$currency} " . $this->n($p->selling_price);

                if ($cost !== null && (float) $p->selling_price > 0) {
                    $margin = (((float) $p->selling_price - $cost) / (float) $p->selling_price) * 100;
                    $line .= ", estimated cost {$currency} " . $this->n($cost, 2) . " per {$p->unit}, estimated margin " . $this->n($margin, 0) . '%';
                } else {
                    $line .= ', cost unknown';
                }

                return $line . ')';
            })->implode("\n  ");

            $raw = RawMaterial::where('is_active', true)->orderBy('name')->get();
            $rawLines = $raw->map(fn ($r) => "{$r->name} (stock {$this->n($r->stock_qty, 1)} {$r->unit}, sells {$currency} " . $this->n($r->selling_price) . ")")->implode("\n  ");

            $low = $products->filter(fn ($p) => (float) $p->stock_qty <= 5)->map(fn ($p) => "{$p->name} ({$this->n($p->stock_qty, 1)} {$p->unit})")
                ->concat($raw->filter(fn ($r) => (float) $r->stock_qty <= 5)->map(fn ($r) => "{$r->name} ({$this->n($r->stock_qty, 1)} {$r->unit})"))
                ->implode('; ');

            return "- Products:\n  " . ($productLines ?: 'none') . "\n- Raw materials:\n  " . ($rawLines ?: 'none')
                . "\n- Low or out of stock (5 or less): " . ($low ?: 'none');
        });

        // ---------------- KHATA ----------------
        $sections[] = $this->section('KHATA (CREDIT LEDGERS)', function () use ($currency) {
            $debtors = CustomerTransaction::query()
                ->selectRaw("customer_id, SUM(CASE WHEN type = 'khata' THEN amount WHEN type = 'payment' THEN -amount ELSE 0 END) AS balance")
                ->groupBy('customer_id')
                ->havingRaw('balance > 0')
                ->orderByDesc('balance')
                ->get();

            $debtorNames = Customer::whereIn('id', $debtors->take(5)->pluck('customer_id'))->pluck('name', 'id');

            $owed = SupplierTransaction::query()
                ->selectRaw("supplier_id, SUM(CASE WHEN type = 'purchase' THEN amount WHEN type = 'payment' THEN -amount ELSE 0 END) AS balance")
                ->groupBy('supplier_id')
                ->havingRaw('balance > 0')
                ->orderByDesc('balance')
                ->get();

            $supplierNames = Supplier::whereIn('id', $owed->take(5)->pluck('supplier_id'))->pluck('name', 'id');

            $lines = [];
            $lines[] = "- Customers owe the business {$currency} " . $this->n($debtors->sum('balance')) . " in total, across {$debtors->count()} customers.";
            $lines[] = "- Biggest customer debts: " . ($debtors->isEmpty() ? 'none' : $debtors->take(5)->map(
                fn ($d) => ($debtorNames[$d->customer_id] ?? 'Unknown') . " {$currency} " . $this->n($d->balance)
            )->implode('; '));
            $lines[] = "- The business owes suppliers {$currency} " . $this->n($owed->sum('balance')) . " in total, across {$owed->count()} suppliers.";
            $lines[] = "- Biggest supplier balances: " . ($owed->isEmpty() ? 'none' : $owed->take(5)->map(
                fn ($o) => ($supplierNames[$o->supplier_id] ?? 'Unknown') . " {$currency} " . $this->n($o->balance)
            )->implode('; '));

            return implode("\n", $lines);
        });

        // ---------------- PEOPLE ----------------
        $sections[] = $this->section('PEOPLE', function () {
            return '- Customers: ' . Customer::count() . ' (daily customers: ' . Customer::where('is_daily_customer', true)->count()
                . '), Suppliers: ' . Supplier::count();
        });

        return implode("\n\n", $sections);
    }

    /** One part of the snapshot. If it fails, the AI still works with the other parts. */
    protected function section(string $title, callable $build): string
    {
        try {
            return $title . ":\n" . $build();
        } catch (\Throwable $e) {
            report($e);

            return $title . ": data is not available right now.";
        }
    }

    /** Filter by a date column from one day to another, including both days. */
    protected function between($query, string $column, string $from, string $to)
    {
        return $query->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);
    }

    protected function n($value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals);
    }
}