<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierTransaction extends Model
{
    protected $fillable = ['supplier_id', 'type', 'amount', 'reference', 'notes', 'transaction_date'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
