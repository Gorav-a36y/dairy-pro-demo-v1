<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    const UNITS = [
        'Liter', 'Kilogram', 'Gram', 'Piece',
        'Packet', 'Bottle', 'Box', 'Dozen',
    ];

    protected $fillable = [
        'name', 'unit', 'selling_price', 'stock_qty',
        'output_qty_per_batch', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price'        => 'decimal:2',
            'stock_qty'            => 'decimal:2',
            'output_qty_per_batch' => 'decimal:2',
            'is_active'            => 'boolean',
        ];
    }

    public function rawMaterials()
    {
        return $this->belongsToMany(
            RawMaterial::class,
            'product_raw_materials'   // ← must be this, not 'product_ingredients'
        )
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function batchProductions()
    {
        return $this->hasMany(BatchProduction::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'item_id')
            ->where('item_type', 'product');
    }

    /** Cost of one base batch using current milk purchase prices. */
    public function baseBatchCost(): float
    {
        $cost = 0.0;

        foreach ($this->rawMaterials as $rawMaterial) {
            $qtyRequired = (float) ($rawMaterial->pivot->quantity_required ?? 0);
            $unitCost    = $rawMaterial->currentCostPerUnit(); // ← from milk collections
            $cost += $qtyRequired * $unitCost;
        }

        return round($cost, 2);
    }

    public function baseCostPerUnit(): float
    {
        $output = (float) ($this->output_qty_per_batch ?: 1);

        return round($this->baseBatchCost() / $output, 2);
    }

    public function latestBatch()
    {
        return $this->batchProductions()->latest()->first();
    }
}