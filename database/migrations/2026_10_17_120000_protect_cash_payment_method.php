<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')
            ->where('code', 'cash')
            ->update(['is_system' => true, 'is_active' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('payment_methods')
            ->where('code', 'cash')
            ->update(['is_system' => false, 'updated_at' => now()]);
    }
};