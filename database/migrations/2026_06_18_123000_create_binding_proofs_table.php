<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binding_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->foreignId('identity_binding_id')->nullable()->constrained('identity_bindings')->nullOnDelete();
            $table->string('proof_type', 64);
            $table->string('binding_key', 64);
            $table->string('proof_reference', 128);
            $table->string('verification_state', 16)->default('verified');
            $table->json('proof_payload');
            $table->timestamp('verified_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['vault_id', 'proof_reference'], 'binding_proofs_vault_reference_unique');
            $table->index(['vault_id', 'proof_type', 'verified_at']);
            $table->index(['binding_key', 'proof_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binding_proofs');
    }
};
