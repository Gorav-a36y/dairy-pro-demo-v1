<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchProduction extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'multiplier', 'output_qty',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
            'output_qty' => 'decimal:2',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
