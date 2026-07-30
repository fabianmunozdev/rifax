<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('status');
        });

        DB::statement('create unique index raffles_only_one_featured_idx on raffles ((is_featured)) where is_featured = true');
    }

    public function down(): void
    {
        DB::statement('drop index if exists raffles_only_one_featured_idx');

        Schema::table('raffles', function (Blueprint $table): void {
            $table->dropColumn('is_featured');
        });
    }
};

