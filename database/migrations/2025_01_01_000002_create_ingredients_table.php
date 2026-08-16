<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 20)->default('Liter');
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('stock_qty', 12, 2)->default(0);
            // Cost per unit is set automatically from Milk Collection purchases —
            // it is not editable on the Raw Material form itself.
            $table->decimal('cost_per_unit', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
