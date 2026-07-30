<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table conversation_states drop constraint if exists conversation_states_status_check');

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
                    'onboarding_privacy_consent',
                    'onboarding_collect_name',
                    'onboarding_collect_document',
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

    public function down(): void
    {
        DB::statement('alter table conversation_states drop constraint if exists conversation_states_status_check');

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
};

