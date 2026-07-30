<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table raffles drop constraint if exists raffles_number_digits_check');
        DB::statement("
            alter table raffles
            add constraint raffles_number_digits_check
            check (number_digits between 2 and 5)
        ");
    }

    public function down(): void
    {
        DB::statement('alter table raffles drop constraint if exists raffles_number_digits_check');
        DB::statement("
            alter table raffles
            add constraint raffles_number_digits_check
            check (number_digits between 1 and 12)
        ");
    }
};

