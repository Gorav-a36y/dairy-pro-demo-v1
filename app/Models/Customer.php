<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'address',
        'is_daily_customer', 'daily_item_type', 'daily_item_id', 'daily_quantity',
    ];

    protected function casts(): array
    {
        return [
            'is_daily_customer' => 'boolean',
            'daily_quantity' => 'decimal:2',
        ];
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function transactions()
    {
        return $this->hasMany(CustomerTransaction::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }

    /** How much this customer currently owes on khata. */
    public function currentBalance(): float
    {
        $khata = $this->transactions()->where('type', 'khata')->sum('amount');
        $payments = $this->transactions()->where('type', 'payment')->sum('amount');

        return round((float) $khata - (float) $payments, 2);
    }

    /** Resolve their assigned daily item back to the real Product or Ingredient model. */
    public function dailyItem()
    {
        if (! $this->daily_item_type || ! $this->daily_item_id) {
            return null;
        }

        return $this->daily_item_type === 'product'
            ? Product::find($this->daily_item_id)
            : Ingredient::find($this->daily_item_id);
    }

    /** Has today's delivery already been logged for this daily customer? */
    public function hasDeliveryToday(): bool
    {
        return $this->sales()
            ->whereDate('sale_date', now()->toDateString())
            ->where('payment_status', 'unpaid')
            ->exists();
    }
}