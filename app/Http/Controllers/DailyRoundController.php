<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDailyItem;
use App\Models\CustomerTransaction;
use App\Models\DailyRound;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DailyRoundController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $customers = Customer::where('is_daily_customer', true)
            ->with([
                'dailyItems.product',
                'dailyItems.rawMaterial',
                'dailyRounds' => fn ($q) => $q->where('round_date', $today),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($customer) {
                $round = $customer->dailyRounds->first();

                $items = $customer->dailyItems
                    ->map(fn (CustomerDailyItem $di) => $this->itemView($customer->id, $di))
                    ->values();

                $allDelivered   = $items->isNotEmpty() && $items->every(fn ($i) => $i['delivered']);
                $deliveredCount = $items->where('delivered', true)->count();
                $isSkipped      = ! $allDelivered && $round?->status === DailyRound::STATUS_SKIPPED;

                /*
                 * Sales are the real delivery record.
                 *  - delivered : every item delivered today
                 *  - partial   : some items delivered, some not
                 *  - skipped   : operator skipped, nothing delivered
                 *  - pending   : nothing done yet
                 */
                if ($allDelivered) {
                    $status = DailyRound::STATUS_DELIVERED;
                } elseif ($deliveredCount > 0) {
                    $status = 'partial';
                } elseif ($isSkipped) {
                    $status = DailyRound::STATUS_SKIPPED;
                } else {
                    $status = DailyRound::STATUS_PENDING;
                }

                return [
                    'customer'        => $customer,
                    'items'           => $items,
                    'delivered_today' => $allDelivered,
                    'status'          => $status,
                    'skipped'         => $isSkipped,
                    'skip_reason'     => $isSkipped ? $round?->skip_reason : null,
                ];
            });

        return view('daily-round.index', compact('customers'));
    }

    protected function itemView(int $customerId, CustomerDailyItem $di): array
    {
        $item      = $di->item();
        $delivered = $this->itemDeliveredToday($customerId, $di);
        $stock     = (float) ($item?->stock_qty ?? 0);

        // A "hard block" means this item cannot be delivered right now,
        // whatever quantity is typed. Low stock is checked against the typed quantity.
        $blocked = null;

        if (! $delivered) {
            if (! $item) {
                $blocked = 'Item no longer exists';
            } elseif (! $item->is_active) {
                $blocked = 'Item is inactive';
            } elseif ($stock <= 0) {
                $blocked = 'Out of stock';
            }
        }

        return [
            'key'            => $di->item_key,                 // e.g. 'raw_material:1'
            'name'           => $item?->name ?? 'Unknown item',
            'unit'           => $item?->unit ?? '',
            'default_qty'    => (float) $di->quantity,
            'price'          => (float) ($item?->selling_price ?? 0),
            'stock'          => $stock,
            'delivered'      => $delivered,
            'blocked_reason' => $blocked,
        ];
    }

    protected function itemDeliveredToday(int $customerId, CustomerDailyItem $di): bool
    {
        return SaleItem::whereHas('sale', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                    ->where('source', Sale::SOURCE_DAILY_ROUND)
                    ->whereDate('sale_date', now()->toDateString());
            })
            ->where('item_type', $di->itemType())
            ->where('item_id', $di->product_id ?? $di->raw_material_id)
            ->exists();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'deliveries' => 'nullable|array',
            'skipped'    => 'nullable|array',
            'skipped.*'  => 'nullable|in:1',
            'skip_reason'   => 'nullable|array',
            'skip_reason.*' => 'nullable|string|max:255',
        ]);

        $deliveries       = $data['deliveries'] ?? [];
        $skippedCustomers = $data['skipped'] ?? [];
        $skipReasons      = $data['skip_reason'] ?? [];

        if (empty($deliveries) && empty($skippedCustomers)) {
            return back()->with('error', 'Select at least one customer or skip someone.');
        }

        $loggedCount = 0;
        $skipped     = [];
        $today       = now()->toDateString();

        DB::transaction(function () use (
            $deliveries,
            $skippedCustomers,
            $skipReasons,
            $today,
            &$loggedCount,
            &$skipped
        ) {
            /*
             * ---------------------------------------------------------
             * 1. Save skipped customers first (same rules as before)
             * ---------------------------------------------------------
             */
            foreach ($skippedCustomers as $customerId => $value) {
                if ((string) $value !== '1') {
                    continue;
                }

                $customer = Customer::find($customerId);

                if (! $customer || ! $customer->is_daily_customer) {
                    continue;
                }

                $allDone = $customer->hasDeliveryToday();
                $reason  = trim((string) ($skipReasons[$customerId] ?? ''));

                DailyRound::updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'round_date'  => $today,
                    ],
                    [
                        'status'      => $allDone
                            ? DailyRound::STATUS_DELIVERED
                            : DailyRound::STATUS_SKIPPED,
                        'skip_reason' => $allDone || $reason === '' ? null : $reason,
                    ]
                );
            }

            /*
             * ---------------------------------------------------------
             * 2. Process deliveries — ONE sale per customer per day,
             *    with one SaleItem line per delivered item.
             *
             *    New format: deliveries[customerId][itemKey] = qty
             *    Legacy:     deliveries[customerId] = qty
             *               (only accepted when the customer has
             *                exactly one daily item)
             * ---------------------------------------------------------
             */
            foreach ($deliveries as $customerId => $payload) {
                // Lock the customer row so a double-click or two devices
                // cannot log the same delivery twice.
                $customer = Customer::with('dailyItems.product', 'dailyItems.rawMaterial')
                    ->lockForUpdate()
                    ->find($customerId);

                if (! $customer || ! $customer->is_daily_customer) {
                    continue;
                }

                if ($customer->hasDeliveryToday()) {
                    DailyRound::updateOrCreate(
                        [
                            'customer_id' => $customer->id,
                            'round_date'  => $today,
                        ],
                        [
                            'status'      => DailyRound::STATUS_DELIVERED,
                            'skip_reason' => null,
                        ]
                    );

                    $skipped[] = $customer->name . ' (already logged today)';
                    continue;
                }

                // Normalize the payload to [item_key => qty]
                if (is_array($payload)) {
                    $qtyByKey = $payload;
                } else {
                    $qty = (float) $payload;

                    if ($customer->dailyItems->count() === 1) {
                        $qtyByKey = [$customer->dailyItems->first()->item_key => $qty];
                    } else {
                        continue; // ambiguous with multiple items — needs per-item input
                    }
                }

                // Items NOT yet delivered today. Anything else is never delivered twice.
                $pendingKeys = $customer->pendingDailyItemsToday()
                    ->map(fn ($d) => $d->item_key)
                    ->all();

                // Build deliverable lines, checking stock per item.
                $lines = [];

                foreach ($qtyByKey as $itemKey => $qty) {
                    $qty = round((float) $qty, 2);

                    if ($qty <= 0) {
                        continue;
                    }

                    /** @var CustomerDailyItem|null $di */
                    $di = $customer->dailyItems
                        ->first(fn ($d) => $d->item_key === $itemKey);

                    if (! $di) {
                        $skipped[] = $customer->name . " (unknown item: {$itemKey})";
                        continue;
                    }

                    if (! in_array($itemKey, $pendingKeys, true)) {
                        $skipped[] = $customer->name
                            . ' (' . ($di->item()?->name ?? $itemKey) . ' already delivered today)';
                        continue;
                    }

                    $item = $di->item();

                    if ($item) {
                        // Read the item again with a lock so stock is checked
                        // against the latest number.
                        $item = $item->newQuery()
                            ->whereKey($item->getKey())
                            ->lockForUpdate()
                            ->first();
                    }

                    if (! $item) {
                        $skipped[] = $customer->name . ' (a daily item no longer exists)';
                        continue;
                    }

                    // POS does not sell inactive items, and neither does the round.
                    if (! $item->is_active) {
                        $skipped[] = $customer->name . ' (' . $item->name . ' is inactive)';
                        continue;
                    }

                    if ($qty > (float) $item->stock_qty) {
                        $skipped[] = $customer->name
                            . ' (not enough ' . $item->name . ' in stock, available '
                            . (float) $item->stock_qty . ' ' . $item->unit . ')';

                        continue;
                    }

                    $lines[] = [
                        'di'       => $di,
                        'item'     => $item,
                        'qty'      => $qty,
                        'subtotal' => round($qty * (float) $item->selling_price, 2),
                    ];
                }

                if (empty($lines)) {
                    continue;
                }

                $total = round(array_sum(array_column($lines, 'subtotal')), 2);

                $sale = Sale::create([
                    'invoice_no'      => InvoiceNumberGenerator::next('INV'),
                    'customer_id'     => $customer->id,
                    'user_id'         => Auth::id(),
                    'sale_date'       => $today,
                    'subtotal'        => $total,
                    'discount'        => 0,
                    'total_amount'    => $total,
                    'paid_amount'     => 0,
                    'payment_method'  => PaymentMethod::KHATA_CODE,
                    'source'          => Sale::SOURCE_DAILY_ROUND,
                ]);

                foreach ($lines as $line) {
                    SaleItem::create([
                        'sale_id'    => $sale->id,
                        'item_type'  => $line['di']->itemType(),
                        'item_id'    => $line['di']->product_id ?? $line['di']->raw_material_id,
                        'item_name'  => $line['item']->name,
                        'unit'       => $line['item']->unit,
                        'quantity'   => $line['qty'],
                        'unit_price' => $line['item']->selling_price,
                        'discount'   => 0,
                        'subtotal'   => $line['subtotal'],
                    ]);

                    $line['item']->decrement('stock_qty', $line['qty']);
                }

                // One khata transaction for the whole delivery, linked to its sale.
                CustomerTransaction::create([
                    'customer_id'      => $customer->id,
                    'sale_id'          => $sale->id,
                    'type'             => CustomerTransaction::TYPE_KHATA,
                    'amount'           => $total,
                    'invoice_no'       => $sale->invoice_no,
                    'notes'            => 'Daily round delivery',
                    'transaction_date' => $today,
                ]);

                // Delivered only when EVERY daily item is now delivered.
                DailyRound::updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'round_date'  => $today,
                    ],
                    [
                        'status'      => $customer->hasDeliveryToday()
                            ? DailyRound::STATUS_DELIVERED
                            : DailyRound::STATUS_PENDING,
                        'skip_reason' => null, // delivered after a skip: the skip no longer applies
                    ]
                );

                $loggedCount++;
            }
        });

        $message = "Logged {$loggedCount} deliveries.";

        if (! empty($skipped)) {
            $message .= ' Not delivered: ' . implode(', ', $skipped);
        }

        return back()->with(
            $loggedCount > 0 ? 'success' : 'error',
            $message
        );
    }

    public function history(Request $request)
    {
        $customers = Customer::where('is_daily_customer', true)
            ->orderBy('name')
            ->get();

        $selectedCustomer = null;
        $deliveries = collect();
        $summary = null;

        if ($request->filled('customer_id')) {
            $selectedCustomer = Customer::find($request->customer_id);

            if ($selectedCustomer) {
                $deliveries = Sale::with('items')
                    ->where('customer_id', $selectedCustomer->id)
                    ->where('source', Sale::SOURCE_DAILY_ROUND)
                    ->when(
                        $request->filled('from'),
                        fn ($q) => $q->whereDate('sale_date', '>=', $request->from)
                    )
                    ->when(
                        $request->filled('to'),
                        fn ($q) => $q->whereDate('sale_date', '<=', $request->to)
                    )
                    ->orderBy('sale_date')
                    ->orderBy('id')
                    ->get();

                // Payments the customer made on the khata ledger in the chosen period.
                $paymentsReceived = CustomerTransaction::where('customer_id', $selectedCustomer->id)
                    ->where('type', CustomerTransaction::TYPE_PAYMENT)
                    ->when(
                        $request->filled('from'),
                        fn ($q) => $q->whereDate('transaction_date', '>=', $request->from)
                    )
                    ->when(
                        $request->filled('to'),
                        fn ($q) => $q->whereDate('transaction_date', '<=', $request->to)
                    )
                    ->sum('amount');

                $summary = [
                    'total_quantity' => $deliveries
                        ->flatMap(fn ($sale) => $sale->items)
                        ->sum('quantity'),

                    // Deliveries in the chosen period.
                    'total_amount' => $deliveries->sum('total_amount'),

                    // Khata payments received in the chosen period.
                    'total_paid'   => (float) $paymentsReceived,

                    // The customer's real khata balance right now (all purchases and payments).
                    'balance'      => $selectedCustomer->currentBalance(),
                ];
            }
        }

        return view(
            'daily-round.history',
            compact(
                'customers',
                'selectedCustomer',
                'deliveries',
                'summary'
            )
        );
    }
}
