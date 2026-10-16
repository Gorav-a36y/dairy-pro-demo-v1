<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_rounds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->date('round_date');

            $table->string('status', 20)->default('pending');
            $table->string('skip_reason', 255)->nullable();

            $table->timestamps();

            // One Daily Round record per customer per day.
            $table->unique(['customer_id', 'round_date']);

            $table->index('round_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_rounds');
    }
};