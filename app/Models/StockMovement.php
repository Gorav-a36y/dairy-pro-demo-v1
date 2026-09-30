<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    const DIRECTION_IN  = 'in';
    const DIRECTION_OUT = 'out';

    protected $fillable = [
        'item_type', 'item_id', 'direction', 'quantity', 'reason',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function item(): ?Model
    {
        return match ($this->item_type) {
            'product'      => Product::find($this->item_id),
            'raw_material' => RawMaterial::find($this->item_id),
            default        => null,
        };
    }
}