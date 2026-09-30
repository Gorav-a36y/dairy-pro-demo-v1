<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'item_type', 'item_id', 'item_name',
        'unit', 'quantity', 'unit_price', 'discount', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount'   => 'decimal:2',
            'subtotal'   => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Auto-calculate subtotal so it can never be wrong
        static::saving(function ($item) {
            $qty      = (float) ($item->quantity ?? 0);
            $price    = (float) ($item->unit_price ?? 0);
            $discount = (float) ($item->discount ?? 0);

            $item->subtotal = round(($qty * $price) - $discount, 2);
        });
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /** Resolve back to the actual Product or RawMaterial model. */
    public function item(): ?Model
    {
        return match ($this->item_type) {
            'product'      => Product::find($this->item_id),
            'raw_material' => RawMaterial::find($this->item_id),
            default        => null,
        };
    }
}