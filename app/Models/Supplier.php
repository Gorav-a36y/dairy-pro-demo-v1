<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'opening_balance', 'is_active'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function milkCollections()
    {
        return $this->hasMany(MilkCollection::class);
    }

    public function transactions()
    {
        return $this->hasMany(SupplierTransaction::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }

    /** Amount the dairy currently owes this supplier. */
    public function currentBalance(): float
    {
        $purchases = $this->transactions()->where('type', 'purchase')->sum('amount');
        $payments = $this->transactions()->where('type', 'payment')->sum('amount');

        return round((float) $this->opening_balance + (float) $purchases - (float) $payments, 2);
    }
}
