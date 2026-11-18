<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un premio anticipado puede jugarse con una lotería o en un en vivo por redes.
     * En el en vivo, lottery_reference_url guarda el enlace de la transmisión.
     */
    public function up(): void
    {
        Schema::table('raffle_early_prizes', function (Blueprint $table) {
            $table->string('draw_method', 20)->default('lottery')->after('description');
            $table->string('lottery_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('raffle_early_prizes', function (Blueprint $table) {
            $table->dropColumn('draw_method');
        });
    }
};
