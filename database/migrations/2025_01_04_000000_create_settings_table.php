<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('dairy_name')->default('DairyPro');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 10)->default('Rs.');
            $table->string('invoice_region', 50)->default('Pakistan (PKT)');
            $table->text('ollama_api_key')->nullable();
            $table->timestamps();
        });

        // The one and only settings row, created here so two early requests
        // can never create two rows.
        DB::table('settings')->insert([
            'dairy_name' => 'DairyPro',
            'currency' => 'Rs.',
            'invoice_region' => 'Pakistan (PKT)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
