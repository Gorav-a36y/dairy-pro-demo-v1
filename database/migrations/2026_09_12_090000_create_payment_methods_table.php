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
            // Built-in methods (Cash, Khata, Partial Khata) always exist,
            // stay active, and can't be deleted.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Built-in methods stay available; custom methods can be added in Settings.
        DB::table('payment_methods')->insert([
            ['name' => 'Cash', 'code' => 'cash', 'is_system' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Khata', 'code' => 'khata', 'is_system' => true, 'is_active' => true, 'sort_order' => 98, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Partial Khata', 'code' => 'partial_khata', 'is_system' => true, 'is_active' => true, 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
