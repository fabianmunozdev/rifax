<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->unsignedInteger('number_digits')->default(4)->after('status');
        });

        DB::statement("
            alter table raffles
            add constraint raffles_number_digits_check
            check (number_digits between 1 and 12)
        ");
    }

    public function down(): void
    {
        DB::statement('alter table raffles drop constraint if exists raffles_number_digits_check');

        Schema::table('raffles', function (Blueprint $table) {
            $table->dropColumn('number_digits');
        });
    }
};

