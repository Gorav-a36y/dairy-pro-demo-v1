<?php

namespace App\Services;

use App\Models\InvoiceSequence;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    /**
     * Next sequential number for a prefix, resets to 0001 every day.
     * Format: PREFIX-YYYYMMDD-0001
     *
     * Works on SQLite, MySQL, and Postgres — uses a row lock inside
     * a transaction instead of database-specific upsert syntax.
     */
    public static function next(string $prefix): string
    {
        $dateKey = Setting::current()->now()->format('Ymd');

        return DB::transaction(function () use ($prefix, $dateKey) {
            $sequence = InvoiceSequence::where('prefix', $prefix)
                ->where('date_key', $dateKey)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = InvoiceSequence::create([
                    'prefix' => $prefix,
                    'date_key' => $dateKey,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');

            return sprintf('%s-%s-%04d', $prefix, $dateKey, $sequence->last_number);
        });
    }
}
