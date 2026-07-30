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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('verification_token')->unique();
            $table->string('public_url')->nullable()->unique();
            $table->string('image_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamps();

            $table->unique('purchase_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
