<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->string('binding_type', 32);
            $table->string('binding_key', 64);
            $table->string('binding_value_original', 256);
            $table->string('binding_value_normalized', 256);
            $table->string('verification_state', 16)->default('pending');
            $table->string('verification_method', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['vault_id', 'binding_type', 'verification_state']);
            $table->index(['vault_id', 'binding_type', 'binding_key']);
            $table->index(['binding_type', 'binding_key', 'binding_value_normalized'], 'identity_bindings_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_bindings');
    }
};
