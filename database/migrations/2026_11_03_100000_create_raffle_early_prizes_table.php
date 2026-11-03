<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Premios anticipados: se juegan antes del premio mayor, con su propia lotería y fecha.
        // Un solo ganador por premio, con las mismas cifras de la rifa principal.
        Schema::create('raffle_early_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('lottery_name');
            $table->string('lottery_draw_number')->nullable();
            $table->date('draw_date');
            $table->time('draw_time')->nullable();
            $table->string('lottery_reference_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // Resultado oficial tal como lo publica la lotería y número de la rifa que se deriva de él.
            $table->string('lottery_result')->nullable();
            $table->string('result_number')->nullable();
            $table->foreignId('winner_raffle_number_id')->nullable()->constrained('raffle_numbers')->nullOnDelete();
            $table->foreignId('winner_purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->timestamp('result_published_at')->nullable();
            $table->foreignId('result_published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['raffle_id', 'draw_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_early_prizes');
    }
};
