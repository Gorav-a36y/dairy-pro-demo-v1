<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MilkCollection extends Model
{
    const PAYMENT_METHODS = ['cash', 'easypaisa', 'jazzcash'];

    protected $fillable = [
        'supplier_id', 'product_id', 'quantity', 'unit', 'purchase_price',
        'total_amount', 'paid_amount', 'payment_method', 'collected_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
