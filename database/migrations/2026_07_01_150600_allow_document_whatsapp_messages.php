<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table whatsapp_messages drop constraint if exists whatsapp_messages_message_type_check');

        DB::statement("
            alter table whatsapp_messages
            add constraint whatsapp_messages_message_type_check
            check (message_type in ('text', 'image', 'template', 'interactive', 'document', 'other'))
        ");
    }

    public function down(): void
    {
        DB::statement('alter table whatsapp_messages drop constraint if exists whatsapp_messages_message_type_check');

        DB::statement("
            alter table whatsapp_messages
            add constraint whatsapp_messages_message_type_check
            check (message_type in ('text', 'image', 'template', 'interactive', 'other'))
        ");
    }
};
