<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('raffles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('min_numbers_per_purchase')->default(1);
            $table->string('lottery_name')->nullable();
            $table->string('lottery_draw_number')->nullable();
            $table->date('draw_date')->nullable()->index();
            $table->time('draw_time')->nullable();
            $table->string('lottery_reference_url')->nullable();
            $table->decimal('price_per_number', 12, 2);
            $table->unsignedInteger('reservation_timeout_minutes')->default(15);
            $table->string('cover_image_path')->nullable();
            $table->timestamps();
        });

        DB::statement("
            alter table raffles
            add constraint raffles_status_check
            check (status in ('draft', 'published', 'closed', 'cancelled'))
        ");

        DB::statement("
            alter table raffles
            add constraint raffles_min_numbers_check
            check (min_numbers_per_purchase >= 1)
        ");

        DB::statement("
            alter table raffles
            add constraint raffles_reservation_timeout_check
            check (reservation_timeout_minutes >= 1)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raffles');
    }
};
