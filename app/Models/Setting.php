<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['dairy_name', 'phone', 'address', 'currency', 'invoice_region'];

    /** Get the single settings row, creating a default one if it doesn't exist yet. */
    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'dairy_name' => 'DairyPro',
            'currency' => 'Rs.',
            'invoice_region' => 'Pakistan (PKT)',
        ]);
    }
}
