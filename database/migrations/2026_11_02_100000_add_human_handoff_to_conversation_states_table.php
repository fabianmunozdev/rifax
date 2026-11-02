<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traspaso de conversaciones a un agente humano.
 *
 * handoff_status: null = atiende el bot, 'requested' = cliente pidió asesor,
 * 'active' = un agente la está atendiendo (el bot no responde).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_states', function (Blueprint $table) {
            $table->string('handoff_status')->nullable()->index();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamp('handoff_started_at')->nullable();
            $table->string('handoff_reason', 1000)->nullable();
            $table->timestamp('last_agent_message_at')->nullable();
            $table->timestamp('agent_last_read_at')->nullable();
        });

        DB::statement("
            alter table conversation_states
            add constraint conversation_states_handoff_status_check
            check (handoff_status in ('requested', 'active') or handoff_status is null)
        ");

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by_user_id');
        });

        DB::statement('alter table conversation_states drop constraint if exists conversation_states_handoff_status_check');

        Schema::table('conversation_states', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn([
                'handoff_status',
                'handoff_requested_at',
                'handoff_started_at',
                'handoff_reason',
                'last_agent_message_at',
                'agent_last_read_at',
            ]);
        });
    }
};
