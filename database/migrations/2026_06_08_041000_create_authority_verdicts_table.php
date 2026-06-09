<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_verdicts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_deposit_intent_id')->nullable()->constrained('merchant_deposit_intents')->cascadeOnDelete();
            $table->foreignId('settlement_proof_id')->nullable()->constrained('settlement_proofs')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('credited_ledger_id')->nullable()->constrained('sovereign_ledger')->nullOnDelete();
            $table->string('policy_key', 80);
            $table->string('status', 32)->default('pending');
            $table->string('decision', 32)->default('wait');
            $table->string('reason_code', 120)->nullable();
            $table->unsignedInteger('required_quorum')->default(1);
            $table->unsignedInteger('accepted_attestations')->default(0);
            $table->string('idempotency_key', 160)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status']);
            $table->index(['policy_key', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_verdicts');
    }
};
