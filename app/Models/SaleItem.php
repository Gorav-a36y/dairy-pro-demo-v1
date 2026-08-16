<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'item_type', 'item_id', 'item_name', 'unit',
        'quantity', 'unit_price', 'discount', 'subtotal',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /** Resolve back to the actual Product or Ingredient model (may be null if deleted). */
    public function item()
    {
        return $this->item_type === 'product'
            ? Product::find($this->item_id)
            : Ingredient::find($this->item_id);
    }
}
