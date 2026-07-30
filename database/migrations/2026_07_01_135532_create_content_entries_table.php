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
        Schema::create('content_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('key');
            $table->string('title');
            $table->string('category')->index();
            $table->string('locale')->default('es')->index();
            $table->string('channel')->default('whatsapp')->index();
            $table->string('status')->default('draft')->index();
            $table->string('trigger_intent')->nullable()->index();
            $table->text('body_text');
            $table->jsonb('variables_json')->nullable();
            $table->text('fallback_text')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_ai_eligible')->default(false)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['key', 'locale', 'channel']);
            $table->index(['type', 'status']);
            $table->index(['category', 'status']);
            $table->index(['trigger_intent', 'status']);
        });

        DB::statement("
            alter table content_entries
            add constraint content_entries_type_check
            check (type in ('faq_fixed', 'faq_parametrized', 'system_message', 'support_message', 'template_bridge'))
        ");

        DB::statement("
            alter table content_entries
            add constraint content_entries_status_check
            check (status in ('draft', 'published', 'archived'))
        ");

        DB::statement("
            alter table content_entries
            add constraint content_entries_channel_check
            check (channel in ('whatsapp'))
        ");

        DB::statement("
            alter table content_entries
            add constraint content_entries_priority_check
            check (priority >= 0)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_entries');
    }
};
