<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address'];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function transactions()
    {
        return $this->hasMany(CustomerTransaction::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }

    /** How much this customer currently owes on khata. */
    public function currentBalance(): float
    {
        $khata = $this->transactions()->where('type', 'khata')->sum('amount');
        $payments = $this->transactions()->where('type', 'payment')->sum('amount');

        return round((float) $khata - (float) $payments, 2);
    }
}
