<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    protected $fillable = ['name', 'unit', 'selling_price', 'stock_qty', 'is_active', 'is_milk'];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'stock_qty'     => 'decimal:2',
            'is_active'     => 'boolean',
            'is_milk'       => 'boolean',
        ];
    }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'product_raw_materials'
        )
            ->withPivot('quantity_required')
            ->withTimestamps();
    }

    public function milkCollections()
    {
        return $this->hasMany(MilkCollection::class);
    }

    public function currentCostPerUnit(): float
    {
        $avg = $this->milkCollections()
            ->where(
                'collected_at',
                '>=',
                Setting::current()->now()->subDays(30)->setTimezone(config('app.timezone'))
            )
            ->avg('purchase_price');

        return round((float) $avg, 2);
    }
}
