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
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('status')->default('main_menu')->index();
            $table->string('substatus')->nullable();
            $table->foreignId('current_raffle_id')->nullable()->constrained('raffles')->nullOnDelete();
            $table->unsignedInteger('requested_quantity')->nullable();
            $table->string('selection_mode')->nullable();
            $table->jsonb('selected_numbers_json')->nullable();
            $table->unsignedBigInteger('reservation_id')->nullable()->index();
            $table->unsignedBigInteger('purchase_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('last_inbound_message_id')->nullable();
            $table->unsignedBigInteger('last_outbound_message_id')->nullable();
            $table->timestamp('last_user_message_at')->nullable()->index();
            $table->timestamp('last_bot_message_at')->nullable()->index();
            $table->timestamp('context_expires_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->string('locked_by')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'channel']);
            $table->index(['status', 'updated_at']);
            $table->index(['current_raffle_id', 'status']);
            $table->index(['purchase_id', 'status']);
        });

        DB::statement("
            alter table conversation_states
            add constraint conversation_states_channel_check
            check (channel in ('whatsapp'))
        ");

        DB::statement("
            alter table conversation_states
            add constraint conversation_states_requested_quantity_check
            check (requested_quantity is null or requested_quantity >= 1)
        ");

        DB::statement("
            alter table conversation_states
            add constraint conversation_states_selection_mode_check
            check (selection_mode in ('manual', 'random') or selection_mode is null)
        ");

        DB::statement("
            alter table conversation_states
            add constraint conversation_states_status_check
            check (
                status in (
                    'main_menu',
                    'purchase_select_raffle',
                    'purchase_enter_quantity',
                    'purchase_choose_mode',
                    'purchase_select_numbers',
                    'purchase_random_assignment',
                    'purchase_reservation_pending',
                    'purchase_payment_instructions',
                    'purchase_proof_received',
                    'purchase_under_review',
                    'purchase_paid',
                    'purchase_rejected',
                    'purchase_expired',
                    'info_available_numbers',
                    'info_my_numbers',
                    'info_statistics',
                    'info_upcoming_raffles',
                    'info_conditions',
                    'info_help'
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_states');
    }
};
