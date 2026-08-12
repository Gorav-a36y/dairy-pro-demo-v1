<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    const PAYMENT_METHODS = ['cash', 'easypaisa', 'jazzcash'];

    protected $fillable = [
        'invoice_no', 'customer_id', 'user_id', 'sale_date',
        'subtotal', 'discount', 'total_amount', 'paid_amount',
        'payment_method', 'payment_status',
    ];

    protected function casts(): array
    {
        return ['sale_date' => 'date'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
