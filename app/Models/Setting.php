<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'dairy_name',
        'phone',
        'address',
        'currency',
        'invoice_region',
        'ollama_api_key', // <-- MUST BE HERE
    ];

    protected function casts(): array
    {
        return [
            'ollama_api_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'dairy_name' => 'DairyPro',
            'currency' => 'Rs.',
            'invoice_region' => 'Pakistan (PKT)',
        ]);
    }
}