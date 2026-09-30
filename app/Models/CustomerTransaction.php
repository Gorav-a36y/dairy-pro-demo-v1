<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTransaction extends Model
{
    const TYPE_KHATA   = 'khata';
    const TYPE_PAYMENT = 'payment';

    protected $fillable = [
        'customer_id', 'type', 'amount', 'invoice_no',
        'notes', 'transaction_date', 'sale_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Keep the cached customers.balance column in sync
        static::saved(function ($transaction) {
            $transaction->customer?->updateQuietly([
                'balance' => $transaction->customer->currentBalance()
            ]);
        });

        static::deleted(function ($transaction) {
            $transaction->customer?->updateQuietly([
                'balance' => $transaction->customer->currentBalance()
            ]);
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}