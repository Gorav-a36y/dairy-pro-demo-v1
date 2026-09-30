<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    // Payment methods are now managed dynamically via the payment_methods
    // table/Settings page — see App\Models\PaymentMethod::activeCodes().
    const STATUS_PAID   = 'paid';
    const STATUS_UNPAID = 'unpaid';

    const SOURCE_POS         = 'pos';
    const SOURCE_DAILY_ROUND = 'daily_round';

    protected $fillable = [
        'invoice_no', 'customer_id', 'user_id', 'sale_date',
        'subtotal', 'discount', 'total_amount', 'paid_amount', 'amount_tendered',
        'payment_method', 'payment_status', 'source',
    ];

    protected function casts(): array
    {
        return [
            'sale_date'       => 'date',
            'subtotal'        => 'decimal:2',
            'discount'        => 'decimal:2',
            'total_amount'    => 'decimal:2',
            'paid_amount'     => 'decimal:2',
            'amount_tendered' => 'decimal:2',
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

    /** Amount still owed on this invoice. */
    public function balanceDue(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function isPaid(): bool
    {
        return $this->balanceDue() <= 0;
    }

    /** Sync payment_status based on paid_amount vs total_amount. */
    public function syncPaymentStatus(): void
    {
        $this->payment_status = $this->isPaid() ? self::STATUS_PAID : self::STATUS_UNPAID;
    }

    /**
     * Change handed back to the customer — only meaningful for sales where
     * cash (or another non-khata method) was tendered above the total.
     */
    public function changeDue(): float
    {
        if ($this->amount_tendered === null) {
            return 0.0;
        }

        return max(round((float) $this->amount_tendered - (float) $this->total_amount, 2), 0.0);
    }
}