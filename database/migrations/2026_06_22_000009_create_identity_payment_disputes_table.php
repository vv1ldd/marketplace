<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->uuid('dispute_uuid')->unique();
            $table->foreignId('identity_payment_intent_id')
                ->constrained('identity_payment_intents')
                ->cascadeOnDelete();
            $table->string('opened_by_identity_id', 64);
            $table->string('opened_by_alias', 64)->nullable();
            $table->string('reason', 128);
            $table->string('status', 32);
            $table->boolean('evidence_required')->default(true);
            $table->json('evidence_snapshot');
            $table->json('lifecycle_log');
            $table->json('resolution')->nullable();
            $table->foreignId('compensation_intent_id')
                ->nullable()
                ->constrained('identity_payment_intents')
                ->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique('identity_payment_intent_id', 'ip_disputes_intent_uq');
            $table->index('status', 'ip_disputes_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_payment_disputes');
    }
};
