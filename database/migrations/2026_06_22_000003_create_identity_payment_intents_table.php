<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('intent_uuid')->unique();
            $table->string('status', 24);
            $table->string('sender_vault_id', 36);
            $table->string('sender_identity_id', 64);
            $table->string('sender_alias', 64)->nullable();
            $table->string('receiver_identity_id', 64);
            $table->string('receiver_alias', 64)->nullable();
            $table->string('asset', 16);
            $table->string('amount', 48);
            $table->string('amount_wei', 64);
            $table->unsignedBigInteger('sender_binding_id')->nullable();
            $table->unsignedBigInteger('receiver_binding_id')->nullable();
            $table->string('network', 32)->nullable();
            $table->string('routing_policy', 64)->nullable();
            $table->json('routing_metadata')->nullable();
            $table->string('settlement_reference', 96)->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('routed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['sender_vault_id', 'idempotency_key'], 'ip_intents_vault_idem_uq');
            $table->index(['receiver_identity_id', 'status'], 'ip_intents_receiver_status_idx');
            $table->index(['sender_identity_id', 'status'], 'ip_intents_sender_status_idx');

            $table->foreign('sender_binding_id', 'ip_intents_sender_bind_fk')
                ->references('id')
                ->on('identity_bindings')
                ->nullOnDelete();
            $table->foreign('receiver_binding_id', 'ip_intents_receiver_bind_fk')
                ->references('id')
                ->on('identity_bindings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_payment_intents');
    }
};
