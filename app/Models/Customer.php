<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'is_daily_customer', 'balance',
    ];

    protected function casts(): array
    {
        return [
            'is_daily_customer' => 'boolean',
            'balance'           => 'decimal:2',
        ];
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function transactions()
    {
        return $this->hasMany(CustomerTransaction::class)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
    }

    public function dailyRounds(): HasMany
    {
        return $this->hasMany(DailyRound::class);
    }

    public function dailyItems(): HasMany
    {
        return $this->hasMany(CustomerDailyItem::class)->orderBy('id');
    }

    /** Live calculated balance (khata minus payments). */
    public function currentBalance(): float
    {
        $khata    = (float) $this->transactions()->where('type', CustomerTransaction::TYPE_KHATA)->sum('amount');
        $payments = (float) $this->transactions()->where('type', CustomerTransaction::TYPE_PAYMENT)->sum('amount');

        return round($khata - $payments, 2);
    }

    /**
     * Replace all daily items in one shot.
     * $rows = [['item_type' => 'product|raw_material', 'item_id' => int, 'quantity' => float], ...]
     */
    public function syncDailyItems(array $rows): void
    {
        $this->dailyItems()->delete();

        foreach ($rows as $row) {
            $this->dailyItems()->create([
                'product_id'      => $row['item_type'] === 'product' ? $row['item_id'] : null,
                'raw_material_id' => $row['item_type'] === 'raw_material' ? $row['item_id'] : null,
                'quantity'        => $row['quantity'],
            ]);
        }
    }

    /** Daily items NOT yet delivered today (used to prefill the Daily Round page). */
    public function pendingDailyItemsToday()
    {
        return $this->dailyItems->filter(function (CustomerDailyItem $di) {
            return ! SaleItem::whereHas('sale', function ($q) {
                    $q->where('customer_id', $this->id)
                        ->where('source', Sale::SOURCE_DAILY_ROUND)
                        ->whereDate('sale_date', now()->toDateString());
                })
                ->where('item_type', $di->itemType())
                ->where('item_id', $di->product_id ?? $di->raw_material_id)
                ->exists();
        })->values();
    }

    /** True when EVERY daily item has been delivered today. */
    public function hasDeliveryToday(): bool
    {
        $items = $this->dailyItems;
        if ($items->isEmpty()) {
            return false;
        }

        return $this->pendingDailyItemsToday()->isEmpty();
    }

    /** True when any delivered item today was not fully paid at the time of sale (went on khata). */
    public function hasUnpaidDeliveryToday(): bool
    {
        return SaleItem::whereHas('sale', function ($q) {
                $q->where('customer_id', $this->id)
                    ->where('source', Sale::SOURCE_DAILY_ROUND)
                    ->whereDate('sale_date', now()->toDateString())
                    ->whereColumn('paid_amount', '<', 'total_amount');
            })
            ->whereIn('item_type', ['product', 'raw_material'])
            ->where(function ($q) {
                $productIds      = $this->dailyItems->whereNotNull('product_id')->pluck('product_id');
                $rawMaterialIds  = $this->dailyItems->whereNotNull('raw_material_id')->pluck('raw_material_id');

                $q->where(function ($q2) use ($productIds) {
                    $q2->where('item_type', 'product')->whereIn('item_id', $productIds);
                })->orWhere(function ($q2) use ($rawMaterialIds) {
                    $q2->where('item_type', 'raw_material')->whereIn('item_id', $rawMaterialIds);
                });
            })
            ->exists();
    }
}
