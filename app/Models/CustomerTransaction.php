<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTransaction extends Model
{
    protected $fillable = ['customer_id', 'type', 'amount', 'invoice_no', 'notes', 'transaction_date'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
