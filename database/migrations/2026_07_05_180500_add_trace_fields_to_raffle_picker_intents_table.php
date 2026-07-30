<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffle_picker_intents', function (Blueprint $table): void {
            $table->string('source')->nullable()->after('quantity')->index();
            $table->jsonb('metadata_json')->nullable()->after('selected_numbers_json');
        });
    }

    public function down(): void
    {
        Schema::table('raffle_picker_intents', function (Blueprint $table): void {
            $table->dropColumn(['source', 'metadata_json']);
        });
    }
};
