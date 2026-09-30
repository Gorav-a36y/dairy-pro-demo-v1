<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerTransaction extends Model
{
    const TYPE_SALARY_DUE = 'salary_due';
    const TYPE_BONUS       = 'bonus';
    const TYPE_PAYMENT     = 'payment';

    const PAYMENT_TYPE_SALARY  = 'salary';
    const PAYMENT_TYPE_ADVANCE = 'advance';

    protected $fillable = [
        'worker_id', 'type', 'payment_type', 'amount',
        'for_month', 'notes', 'transaction_date', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'for_month'        => 'date',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Keep the cached workers.balance column in sync, same pattern as
        // CustomerTransaction/SupplierTransaction.
        static::saved(function ($transaction) {
            $transaction->worker?->updateQuietly([
                'balance' => $transaction->worker->currentBalance(),
            ]);
        });

        static::deleted(function ($transaction) {
            $transaction->worker?->updateQuietly([
                'balance' => $transaction->worker->currentBalance(),
            ]);
        });
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }
}
