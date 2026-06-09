<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validator_attestations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('authority_verdict_id')->nullable()->constrained('authority_verdicts')->nullOnDelete();
            $table->foreignId('merchant_deposit_intent_id')->nullable()->constrained('merchant_deposit_intents')->cascadeOnDelete();
            $table->foreignId('settlement_proof_id')->nullable()->constrained('settlement_proofs')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_identity', 120);
            $table->string('signer_role', 80)->nullable();
            $table->string('attestation_type', 60);
            $table->string('status', 32)->default('accepted');
            $table->string('external_reference', 160)->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->string('signed_payload_hash', 80)->nullable();
            $table->json('signature_payload')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('attested_at')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status'], 'validator_attestations_entity_status_idx');
            $table->index(['settlement_proof_id', 'attestation_type'], 'validator_attestations_proof_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validator_attestations');
    }
};
