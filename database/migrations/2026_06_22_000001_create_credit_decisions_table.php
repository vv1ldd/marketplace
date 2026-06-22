<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_settlement_proof_id')
                ->unique()
                ->constrained('vault_settlement_proofs')
                ->cascadeOnDelete();
            $table->foreignId('identity_binding_id')
                ->constrained('identity_bindings')
                ->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('reason', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['identity_binding_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_decisions');
    }
};
