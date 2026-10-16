<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRound extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_DELIVERED = 'delivered';

    protected $fillable = [
        'customer_id',
        'round_date',
        'status',
        'skip_reason',
    ];

    protected function casts(): array
    {
        return [
            'round_date' => 'date:Y-m-d',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
