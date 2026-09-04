<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_phone_unique');
        DB::statement('ALTER TABLE customers ALTER COLUMN phone DROP NOT NULL');
        if (Schema::hasIndex('customers', ['phone'])) {
            DB::statement('DROP INDEX IF EXISTS customers_phone_index CASCADE');
        }
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS customers_phone_unique ON customers (phone) WHERE phone IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN phone SET NOT NULL');
        DB::statement('DROP INDEX IF EXISTS customers_phone_unique');
    }
};
