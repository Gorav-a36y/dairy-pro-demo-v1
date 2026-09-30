<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MilkCollection extends Model
{
    // Payment methods are now managed dynamically via the payment_methods
    // table/Settings page — see App\Models\PaymentMethod::activeCodes().
    const SHIFTS = ['morning', 'afternoon', 'evening', 'night'];

    // Units treated as liquid — only these get Fat/SNF/Total Solids fields.
    // Kept as a list here (not on RawMaterial) since it's a milk-collection-specific rule.
    const LIQUID_UNITS = ['liter', 'litre', 'l'];

    protected $fillable = [
        'supplier_id', 'raw_material_id', 'quantity', 'unit',
        'purchase_price', 'total_amount', 'paid_amount', 'amount_given', 'return_amount',
        'payment_method', 'fat', 'snf', 'total_solids', 'shift',
        'collected_at', 'notes', 'invoice_no',
    ];

    protected function casts(): array
    {
        return [
            'collected_at'   => 'datetime',
            'quantity'       => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'total_amount'   => 'decimal:2',
            'paid_amount'    => 'decimal:2',
            'amount_given'   => 'decimal:2',
            'return_amount'  => 'decimal:2',
            'fat'            => 'decimal:2',
            'snf'            => 'decimal:2',
            'total_solids'   => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Financial guard: total_amount is ALWAYS derived, never trusted from input
        static::saving(function ($collection) {
            $qty   = (float) ($collection->quantity ?? 0);
            $price = (float) ($collection->purchase_price ?? 0);
            $collection->total_amount = round($qty * $price, 2);
        });
    }

    public static function isLiquidUnit(?string $unit): bool
    {
        return in_array(strtolower(trim($unit ?? '')), self::LIQUID_UNITS, true);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    /** How much is still unpaid to the supplier for this collection. */
    public function balanceDue(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }
}