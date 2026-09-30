<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierTransaction extends Model
{
    const TYPE_PURCHASE = 'purchase';
    const TYPE_PAYMENT  = 'payment';

    protected $fillable = [
        'supplier_id', 'type', 'amount',
        'reference', 'notes', 'transaction_date', 'invoice_no', 'milk_collection_id',
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
        // Cache into 'balance'
        static::saved(function ($transaction) {
            $transaction->supplier?->updateQuietly([
                'balance' => $transaction->supplier->currentBalance()
            ]);
        });

        static::deleted(function ($transaction) {
            $transaction->supplier?->updateQuietly([
                'balance' => $transaction->supplier->currentBalance()
            ]);
        });
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}