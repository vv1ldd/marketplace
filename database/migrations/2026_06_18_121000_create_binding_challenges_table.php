<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binding_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->string('binding_type', 32);
            $table->string('binding_key', 64);
            $table->string('binding_value_original', 256);
            $table->string('binding_value_normalized', 256);
            $table->string('nonce', 64)->unique();
            $table->text('message');
            $table->string('verification_method', 32)->default('signature');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedSmallInteger('verification_attempt_count')->default(0);
            $table->json('last_verification_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['vault_id', 'binding_type', 'binding_key']);
            $table->index(['vault_id', 'expires_at']);
            $table->index(['vault_id', 'nonce', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binding_challenges');
    }
};
