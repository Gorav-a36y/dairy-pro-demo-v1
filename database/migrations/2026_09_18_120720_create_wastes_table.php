<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastes', function (Blueprint $table) {
            $table->id();

            // Not a real polymorphic relation on purpose — 'raw_material' or 'product'.
            $table->string('item_type', 20);
            $table->unsignedBigInteger('item_id');

            // Snapshot the name/unit at the time of waste, so history stays
            // readable even if the raw material/product is later renamed or removed.
            $table->string('item_name');
            $table->string('unit', 20);

            $table->decimal('quantity', 12, 2);
            $table->text('note')->nullable();

            // Date of the waste. Required on the form: it starts as today and
            // can be changed to an earlier day, but never to a future day.
            $table->date('wasted_at');

            $table->timestamps();

            $table->index(['item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastes');
    }
};