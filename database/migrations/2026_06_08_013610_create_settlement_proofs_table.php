<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_deposit_intent_id')->constrained('merchant_deposit_intents')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('credited_ledger_id')->nullable()->constrained('sovereign_ledger')->nullOnDelete();
            $table->string('source', 40);
            $table->string('status', 32)->default('proof_received');
            $table->string('external_reference', 160);
            $table->string('idempotency_key', 160)->unique();
            $table->decimal('confirmed_amount', 16, 4);
            $table->string('confirmed_currency', 10)->default('RUB');
            $table->unsignedInteger('confirmation_count')->default(0);
            $table->string('raw_payload_hash', 80)->nullable();
            $table->json('raw_payload')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_reference']);
            $table->index(['legal_entity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_proofs');
    }
};
