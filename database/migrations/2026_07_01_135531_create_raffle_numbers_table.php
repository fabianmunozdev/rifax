<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('raffle_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('status')->default('available')->index();
            $table->timestamp('reserved_until')->nullable()->index();
            $table->timestamps();

            $table->unique(['raffle_id', 'number']);
            $table->index(['raffle_id', 'status']);
        });

        DB::statement("
            alter table raffle_numbers
            add constraint raffle_numbers_status_check
            check (status in ('available', 'reserved', 'paid', 'winner', 'cancelled'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raffle_numbers');
    }
};
