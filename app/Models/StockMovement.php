<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['item_type', 'item_id', 'direction', 'quantity', 'reason'];
}
