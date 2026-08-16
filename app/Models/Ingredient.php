<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = ['name', 'unit', 'selling_price', 'stock_qty', 'cost_per_unit'];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'stock_qty' => 'decimal:2',
            'cost_per_unit' => 'decimal:2',
        ];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function milkCollections()
    {
        return $this->hasMany(MilkCollection::class);
    }
}
