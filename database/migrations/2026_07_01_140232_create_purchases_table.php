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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('reserved')->index();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->string('raffle_title_snapshot');
            $table->jsonb('payment_instructions_snapshot')->nullable();
            $table->timestamp('reserved_until')->nullable()->index();
            $table->timestamp('proof_submitted_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['raffle_id', 'status']);
        });

        DB::statement("
            alter table purchases
            add constraint purchases_status_check
            check (
                status in (
                    'reserved',
                    'payment_submitted',
                    'under_review',
                    'paid',
                    'rejected',
                    'expired',
                    'cancelled'
                )
            )
        ");

        DB::statement("
            alter table purchases
            add constraint purchases_quantity_check
            check (quantity >= 1)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
