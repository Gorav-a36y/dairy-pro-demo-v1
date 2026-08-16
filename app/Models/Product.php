<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    const UNITS = ['Liter', 'Kilogram', 'Gram', 'Piece', 'Packet', 'Bottle', 'Box', 'Dozen'];

    protected $fillable = [
        'name', 'unit', 'selling_price', 'stock_qty', 'output_qty_per_batch', 'is_active',
    ];

    protected function casts(): array
    {
        return [
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

    /** Cost to produce one full recipe yield (output_qty_per_batch units) at current raw material costs. */
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

    /** Most recent production run's cost/date, for display on the product page. */
    public function latestBatch()
    {
        return $this->batchProductions()->latest()->first();
    }
}
