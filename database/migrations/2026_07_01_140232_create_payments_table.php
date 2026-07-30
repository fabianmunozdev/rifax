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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->string('status')->default('pending_review')->index();
            $table->string('reference')->nullable()->index();
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->timestamp('proof_received_at')->nullable()->index();
            $table->string('proof_channel')->default('whatsapp');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('rejection_reason')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'status']);
        });

        DB::statement("
            alter table payments
            add constraint payments_status_check
            check (status in ('pending_review', 'approved', 'rejected'))
        ");

        DB::statement("
            alter table payments
            add constraint payments_proof_channel_check
            check (proof_channel in ('whatsapp'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
