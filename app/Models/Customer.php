<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'address',
        'is_daily_customer', 'daily_item_type', 'daily_item_id', 'daily_quantity',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'is_daily_customer' => 'boolean',
            'daily_quantity'    => 'decimal:2',
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

    /** Live calculated balance (khata minus payments). */
    public function currentBalance(): float
    {
        $khata    = (float) $this->transactions()->where('type', CustomerTransaction::TYPE_KHATA)->sum('amount');
        $payments = (float) $this->transactions()->where('type', CustomerTransaction::TYPE_PAYMENT)->sum('amount');

        return round($khata - $payments, 2);
    }

    /** Resolve their assigned daily item. */
    public function dailyItem(): ?Model
    {
        if (! $this->daily_item_type || ! $this->daily_item_id) {
            return null;
        }

        return match ($this->daily_item_type) {
            'product'      => Product::find($this->daily_item_id),
            'raw_material' => RawMaterial::find($this->daily_item_id),
            default        => null,
        };
    }

    public function hasDeliveryToday(): bool
    {
        if (! $this->daily_item_type || ! $this->daily_item_id) {
            return false;
        }

        return SaleItem::whereHas('sale', function ($q) {
                $q->where('customer_id', $this->id)
                    ->where('source', Sale::SOURCE_DAILY_ROUND)
                    ->whereDate('sale_date', now()->toDateString());
            })
            ->where('item_type', $this->daily_item_type)
            ->where('item_id', $this->daily_item_id)
            ->exists();
    }

    public function hasUnpaidDeliveryToday(): bool
    {
        if (! $this->daily_item_type || ! $this->daily_item_id) {
            return false;
        }

        return SaleItem::whereHas('sale', function ($q) {
                $q->where('customer_id', $this->id)
                    ->where('source', Sale::SOURCE_DAILY_ROUND)
                    ->whereDate('sale_date', now()->toDateString())
                    ->where('payment_status', Sale::STATUS_UNPAID);
            })
            ->where('item_type', $this->daily_item_type)
            ->where('item_id', $this->daily_item_id)
            ->exists();
    }
}