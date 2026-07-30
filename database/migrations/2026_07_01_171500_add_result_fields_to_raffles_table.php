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
        Schema::table('raffles', function (Blueprint $table): void {
            $table->string('result_number')->nullable()->after('lottery_reference_url')->index();
            $table->timestamp('result_published_at')->nullable()->after('result_number')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raffles', function (Blueprint $table): void {
            $table->dropColumn([
                'result_number',
                'result_published_at',
            ]);
        });
    }
};
