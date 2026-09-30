<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');            // display label, e.g. "Cash"
            $table->string('code', 30)->unique(); // stored value, e.g. "cash"
            // System methods (Khata, Partial Khata) always exist, are always
            // active, and can't be deactivated or deleted — enforced in
            // PaymentMethodController.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Built-in methods. Cash can be deactivated (or more added from
        // Settings) but never deleted; Khata and Partial Khata are locked on.
        DB::table('payment_methods')->insert([
            ['name' => 'Cash', 'code' => 'cash', 'is_system' => false, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Khata', 'code' => 'khata', 'is_system' => true, 'is_active' => true, 'sort_order' => 98, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Partial Khata', 'code' => 'partial_khata', 'is_system' => true, 'is_active' => true, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
