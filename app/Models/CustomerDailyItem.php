<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDailyItem extends Model
{
    protected $fillable = [
        'customer_id', 'product_id', 'raw_material_id', 'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    /** The polymorphic-ish resolved item (Product or RawMaterial), or null. */
    public function item(): ?Model
    {
        return $this->product_id ? $this->product : $this->rawMaterial;
    }

    /** 'product' or 'raw_material' — matches SaleItem.item_type values. */
    public function itemType(): ?string
    {
        if ($this->product_id) return 'product';
        if ($this->raw_material_id) return 'raw_material';

        return null;
    }

    /** 'product:3' style key — same format the form uses. */
    public function getItemKeyAttribute(): ?string
    {
        $type = $this->itemType();

        return $type ? "{$type}:{$this->{$type . '_id'}}" : null;
    }
}