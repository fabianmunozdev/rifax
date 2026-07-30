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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('quantity');
            $table->string('selection_mode')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->timestamp('expires_at')->index();
            $table->timestamp('expired_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->jsonb('numbers_snapshot_json')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'status']);
            $table->index(['customer_id', 'status']);
        });

        DB::statement("
            alter table reservations
            add constraint reservations_status_check
            check (status in ('active', 'expired', 'converted', 'cancelled'))
        ");

        DB::statement("
            alter table reservations
            add constraint reservations_quantity_check
            check (quantity >= 1)
        ");

        DB::statement("
            alter table reservations
            add constraint reservations_selection_mode_check
            check (selection_mode in ('manual', 'random') or selection_mode is null)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
