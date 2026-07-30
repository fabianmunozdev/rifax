<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_numbers', function (Blueprint $table) {
            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->cascadeOnDelete();
        });

        Schema::table('conversation_states', function (Blueprint $table) {
            $table->foreign('reservation_id')
                ->references('id')
                ->on('reservations')
                ->nullOnDelete();

            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->nullOnDelete();

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->nullOnDelete();

            $table->foreign('last_inbound_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->nullOnDelete();

            $table->foreign('last_outbound_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->nullOnDelete();
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->foreign('whatsapp_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_numbers', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });

        Schema::table('conversation_states', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['purchase_id']);
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['last_inbound_message_id']);
            $table->dropForeign(['last_outbound_message_id']);
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_message_id']);
        });
    }
};
