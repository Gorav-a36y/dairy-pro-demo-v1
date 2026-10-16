<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

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

    protected static function booted(): void
    {
        // System methods (Khata, Partial Khata) can never be deleted.
        static::deleting(function (PaymentMethod $method) {
            if ($method->is_system) {
                throw new LogicException("{$method->name} is a system payment method and cannot be deleted.");
            }
        });

        // System methods must stay active, keep their code, and stay "system".
        static::updating(function (PaymentMethod $method) {
            if (! $method->getOriginal('is_system')) {
                return;
            }

            if (! $method->is_active || ! $method->is_system || $method->isDirty('code')) {
                throw new LogicException("{$method->getOriginal('name')} is a system payment method and cannot be deactivated or changed this way.");
            }
        });
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