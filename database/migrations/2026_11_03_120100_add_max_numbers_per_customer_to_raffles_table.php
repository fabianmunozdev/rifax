<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table): void {
            // Null = usar el tope global de config/rifax.php.
            $table->unsignedInteger('max_numbers_per_customer')->nullable()->after('min_numbers_per_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('raffles', function (Blueprint $table): void {
            $table->dropColumn('max_numbers_per_customer');
        });
    }
};
