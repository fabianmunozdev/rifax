<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table): void {
            $table->boolean('random_selection_by_blocks')
                ->default(false)
                ->after('min_numbers_per_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('raffles', function (Blueprint $table): void {
            $table->dropColumn('random_selection_by_blocks');
        });
    }
};
