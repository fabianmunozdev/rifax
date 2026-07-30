<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('document_number')
                ->nullable()
                ->after('name')
                ->index();

            $table->timestamp('accepted_privacy_at')
                ->nullable()
                ->after('last_interaction_at')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('accepted_privacy_at');
            $table->dropColumn('document_number');
        });
    }
};
