<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_managed_wallet_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->foreignId('identity_binding_id')->constrained('identity_bindings')->cascadeOnDelete();
            $table->string('network_key', 64);
            $table->string('address_normalized', 256);
            $table->uuid('key_reference')->unique();
            $table->text('encrypted_secret');
            $table->timestamps();

            $table->unique(['vault_id', 'network_key'], 'vault_managed_wallet_keys_vault_network_unique');
            $table->index(['identity_binding_id', 'network_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_managed_wallet_keys');
    }
};
