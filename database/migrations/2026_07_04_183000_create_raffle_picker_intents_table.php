<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffle_picker_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('raffle_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->unsignedInteger('quantity');
            $table->jsonb('selected_numbers_json');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->foreignId('consumed_by_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();

            $table->index(['raffle_id', 'expires_at']);
        });

        DB::statement("
            alter table raffle_picker_intents
            add constraint raffle_picker_intents_quantity_check
            check (quantity >= 1)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_picker_intents');
    }
};
