<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchMaterialConsumption extends Model
{
    protected $fillable = [
        'batch_production_id',
        'raw_material_id',
        'material_name',
        'unit',
        'quantity_consumed',
    ];

    protected function casts(): array
    {
        return [
            'quantity_consumed' => 'decimal:2',
        ];
    }

    public function batchProduction()
    {
        return $this->belongsTo(BatchProduction::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
