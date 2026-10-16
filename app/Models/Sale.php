<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    // Payment methods are managed dynamically via the payment_methods
    // table/Settings page — see App\Models\PaymentMethod::activeCodes().
    //
    // There is no payment status on a sale on purpose. Who owes what is
    // tracked by the khata ledger (customer_transactions). A sale only
    // remembers what was paid at the time it was made (paid_amount).

    const SOURCE_POS         = 'pos';
    const SOURCE_DAILY_ROUND = 'daily_round';

    protected $fillable = [
        'invoice_no', 'customer_id', 'user_id', 'sale_date',
        'subtotal', 'discount', 'total_amount', 'paid_amount', 'amount_received',
        'payment_method', 'source',
    ];

    protected function casts(): array
    {
        return [
            'sale_date'       => 'date',
            'subtotal'        => 'decimal:2',
            'discount'        => 'decimal:2',
            'total_amount'    => 'decimal:2',
            'paid_amount'     => 'decimal:2',
            'amount_received' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Amount that was NOT paid at the time of sale (it went on khata). */
    public function balanceDue(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    /** True when the sale was paid in full at the time of sale. */
    public function isPaid(): bool
    {
        return $this->balanceDue() <= 0;
    }

    /**
     * Change handed back to the customer — only meaningful for sales where
     * cash (or another non-khata method) was tendered above the total.
     */
    public function changeDue(): float
    {
        if ($this->amount_received === null) {
            return 0.0;
        }

        return max(round((float) $this->amount_received - (float) $this->total_amount, 2), 0.0);
    }
}
