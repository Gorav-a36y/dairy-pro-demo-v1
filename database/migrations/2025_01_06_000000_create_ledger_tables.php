<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete(); // added
            $table->string('type', 20); // khata | payment
            $table->decimal('amount', 12, 2);
            $table->string('invoice_no')->nullable();
            $table->uuid('submit_token')->nullable()->unique(); // one-time token: a payment can be saved only once
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
        });

        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milk_collection_id')->nullable()->constrained()->nullOnDelete(); // added
            $table->string('type', 20); // purchase | payment
            $table->decimal('amount', 12, 2);
            $table->string('invoice_no')->nullable(); // added
            $table->uuid('submit_token')->nullable()->unique(); // one-time token: a payment can be saved only once
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
        Schema::dropIfExists('supplier_transactions');
    }
};
