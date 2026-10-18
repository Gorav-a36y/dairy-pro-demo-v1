<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_material_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_production_id')
                ->constrained('batch_productions')
                ->cascadeOnDelete();
            $table->foreignId('raw_material_id')
                ->constrained('raw_materials')
                ->restrictOnDelete();
            $table->string('material_name');
            $table->string('unit', 20);
            $table->decimal('quantity_consumed', 12, 2);
            $table->timestamps();

            $table->index(['batch_production_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_material_consumptions');
    }
};
