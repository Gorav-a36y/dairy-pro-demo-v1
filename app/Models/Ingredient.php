<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = ['name', 'unit', 'stock_qty', 'cost_per_unit', 'reorder_level'];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'decimal:2',
            'cost_per_unit' => 'decimal:2',
            'reorder_level' => 'decimal:2',
        ];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function isLowStock(): bool
    {
        return (float) $this->stock_qty <= (float) $this->reorder_level;
    }
}
