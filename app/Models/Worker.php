<?php
// app/Models/Worker.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    // NOTE: Worker Khata (salary/advance/bonus ledger + its receipts) has
    // been removed. Workers are now a simple staff directory — name, phone,
    // monthly salary, active/inactive, join/leave dates — used for the
    // roster and for the "Total Workers' Salary" figure on Reports.
    protected $fillable = [
        'name',
        'phone_number',
        'monthly_salary',
        'is_active',
        'joined_on',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'is_active'      => 'boolean',
            'joined_on'      => 'date',
        ];
    }
}
