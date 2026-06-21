<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_settlement_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->foreignId('identity_binding_id')->nullable()->constrained('identity_bindings')->nullOnDelete();
            $table->string('rail', 64);
            $table->string('external_reference', 128);
            $table->string('proof_kind', 64);
            $table->string('asset', 32);
            $table->string('amount', 96);
            $table->string('recipient', 128);
            $table->string('status', 32);
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['vault_id', 'external_reference'], 'vault_settlement_proofs_vault_reference_unique');
            $table->index(['vault_id', 'rail', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_settlement_proofs');
    }
};
