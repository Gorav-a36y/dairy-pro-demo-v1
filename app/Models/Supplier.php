<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'phone', 'address', 'balance',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function milkCollections()
    {
        return $this->hasMany(MilkCollection::class);
    }

    public function transactions()
    {
        return $this->hasMany(SupplierTransaction::class)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
    }

    /** Live calculated balance (what we still owe this supplier). */
    public function currentBalance(): float
    {
        $purchases = (float) $this->transactions()->where('type', SupplierTransaction::TYPE_PURCHASE)->sum('amount');
        $payments  = (float) $this->transactions()->where('type', SupplierTransaction::TYPE_PAYMENT)->sum('amount');

        return round($purchases - $payments, 2);
    }
}