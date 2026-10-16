<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_daily_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                  ->constrained('customers')
                  ->cascadeOnDelete();

            // Exactly one of these is filled per row (XOR enforced in app layer)
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->nullable()->constrained('raw_materials')->cascadeOnDelete();

            $table->decimal('quantity', 12, 2);

            $table->timestamps();

            // One unique index PER item type. A single index over all three
            // columns never blocks duplicates, because NULL is never equal to NULL.
            $table->unique(['customer_id', 'product_id'], 'customer_daily_product_unique');
            $table->unique(['customer_id', 'raw_material_id'], 'customer_daily_raw_material_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_daily_items');
    }
};
