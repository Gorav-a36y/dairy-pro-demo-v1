<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    const UNITS = ['Liter', 'Kilogram', 'Gram', 'Piece', 'Packet', 'Bottle', 'Box', 'Dozen'];

    protected $fillable = [
        'name', 'unit', 'purchase_price', 'selling_price',
        'stock_qty', 'output_qty_per_batch', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock_qty' => 'decimal:2',
            'output_qty_per_batch' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredients')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function batchProductions()
    {
        return $this->hasMany(BatchProduction::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function baseBatchCost(): float
    {
        $cost = 0.0;
        foreach ($this->ingredients as $ingredient) {
            $cost += (float) $ingredient->pivot->quantity_required * (float) $ingredient->cost_per_unit;
        }
        return round($cost, 2);
    }

    public function baseCostPerUnit(): float
    {
        $output = (float) $this->output_qty_per_batch ?: 1;
        return round($this->baseBatchCost() / $output, 2);
    }
}
