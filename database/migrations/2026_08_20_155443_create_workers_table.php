<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone_number')->unique();
            $table->decimal('monthly_salary', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->date('joined_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};