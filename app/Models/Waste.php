<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Waste extends Model
{
    const TYPE_RAW_MATERIAL = 'raw_material';
    const TYPE_PRODUCT = 'product';

    protected $fillable = [
        'item_type', 'item_id', 'item_name', 'unit', 'quantity', 'note', 'wasted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'  => 'decimal:2',
            'wasted_at' => 'date',
        ];
    }

    /** Resolve the actual RawMaterial or Product model this waste entry points to (if it still exists). */
    public function item()
    {
        return $this->item_type === self::TYPE_RAW_MATERIAL
            ? RawMaterial::find($this->item_id)
            : Product::find($this->item_id);
    }

    public function isRawMaterial(): bool
    {
        return $this->item_type === self::TYPE_RAW_MATERIAL;
    }
}