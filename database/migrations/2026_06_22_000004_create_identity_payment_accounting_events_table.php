<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_payment_accounting_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('identity_payment_intent_id');
            $table->string('sender_identity_id', 64);
            $table->string('receiver_identity_id', 64);
            $table->unsignedBigInteger('sender_binding_id')->nullable();
            $table->unsignedBigInteger('receiver_binding_id')->nullable();
            $table->string('asset', 16);
            $table->string('amount', 48);
            $table->string('network', 32)->nullable();
            $table->string('narrative', 255);
            $table->string('settlement_reference', 96)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique('identity_payment_intent_id', 'ip_acct_events_intent_uq');
            $table->index(['sender_identity_id', 'recorded_at'], 'ip_acct_events_sender_idx');
            $table->index(['receiver_identity_id', 'recorded_at'], 'ip_acct_events_receiver_idx');

            $table->foreign('identity_payment_intent_id', 'ip_acct_events_intent_fk')
                ->references('id')
                ->on('identity_payment_intents')
                ->cascadeOnDelete();
            $table->foreign('sender_binding_id', 'ip_acct_events_sender_bind_fk')
                ->references('id')
                ->on('identity_bindings')
                ->nullOnDelete();
            $table->foreign('receiver_binding_id', 'ip_acct_events_receiver_bind_fk')
                ->references('id')
                ->on('identity_bindings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_payment_accounting_events');
    }
};
