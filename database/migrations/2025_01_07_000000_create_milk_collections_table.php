<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milk_collections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->foreignId('raw_material_id')
                ->constrained('raw_materials')
                ->cascadeOnDelete();

            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20);
            $table->decimal('purchase_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('amount_given', 12, 2)->nullable();
            $table->decimal('return_amount', 12, 2)->default(0);
            $table->string('payment_method', 20)->default('cash');
            $table->string('invoice_no')->nullable();

            // Milk quality fields — only meaningful for liquid raw materials (unit = Liter).
            // Nullable, not default(0): "0" would falsely mean "tested, zero fat" instead
            // of "not applicable / not recorded".
            $table->decimal('fat', 5, 2)->nullable();
            $table->decimal('snf', 5, 2)->nullable();
            $table->decimal('total_solids', 5, 2)->nullable();

            // Which part of the day this batch was collected
            $table->string('shift', 20)->default('morning');

            $table->dateTime('collected_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_collections');
    }
};