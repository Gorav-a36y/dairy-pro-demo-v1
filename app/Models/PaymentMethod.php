<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    // The two payment methods the system depends on internally for khata
    // logic (POS/Milk Collection payment behavior branches on these codes).
    const KHATA_CODE = 'khata';
    const PARTIAL_KHATA_CODE = 'partial_khata';

    protected $fillable = [
        'name', 'code', 'is_system', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system'  => 'boolean',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Codes usable in validation rules, e.g. Rule::in(PaymentMethod::activeCodes()). */
    public static function activeCodes(): array
    {
        return static::active()->ordered()->pluck('code')->all();
    }
}
