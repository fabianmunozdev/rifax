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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('direction')->index();
            $table->string('message_type')->index();
            $table->string('provider_message_id')->nullable()->unique();
            $table->text('body_text')->nullable();
            $table->jsonb('payload_json')->nullable();
            $table->string('status')->nullable()->index();
            $table->timestamp('provider_created_at')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['direction', 'created_at']);
        });

        DB::statement("
            alter table whatsapp_messages
            add constraint whatsapp_messages_direction_check
            check (direction in ('inbound', 'outbound'))
        ");

        DB::statement("
            alter table whatsapp_messages
            add constraint whatsapp_messages_message_type_check
            check (message_type in ('text', 'image', 'template', 'interactive', 'other'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
