<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchProduction extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'multiplier', 'output_qty',
        'batch_cost', 'cost_per_unit', 'status', 'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
