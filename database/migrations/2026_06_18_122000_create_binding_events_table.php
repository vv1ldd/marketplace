<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->foreignId('identity_binding_id')->nullable()->constrained('identity_bindings')->nullOnDelete();
            $table->string('binding_type', 32);
            $table->string('binding_key', 64);
            $table->string('binding_value_normalized', 256)->nullable();
            $table->string('event_type', 64);
            $table->string('verification_method', 32)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['vault_id', 'occurred_at']);
            $table->index(['vault_id', 'event_type', 'occurred_at']);
            $table->index(['identity_binding_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binding_events');
    }
};
