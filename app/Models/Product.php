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

    public function latestBatch()
    {
        return $this->batchProductions()->latest()->first();
    }
}