<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 20)->default('Liter');
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('stock_qty', 12, 2)->default(0);
            // Recipe output/yield: how many units one full production run makes.
            $table->decimal('output_qty_per_batch', 12, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
