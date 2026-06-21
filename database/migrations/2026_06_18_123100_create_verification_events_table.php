<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->foreignId('binding_proof_id')->nullable()->constrained('binding_proofs')->nullOnDelete();
            $table->string('proof_type', 64);
            $table->string('binding_key', 64);
            $table->string('event_type', 64);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['vault_id', 'occurred_at']);
            $table->index(['vault_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_events');
    }
};
