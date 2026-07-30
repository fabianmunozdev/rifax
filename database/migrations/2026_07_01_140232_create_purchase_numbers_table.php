<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->foreignId('raffle_number_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->timestamps();

            $table->unique(['purchase_id', 'raffle_number_id']);
            $table->unique('raffle_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_numbers');
    }
};
