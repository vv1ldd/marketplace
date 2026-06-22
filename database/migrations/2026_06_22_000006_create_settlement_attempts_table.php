<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('identity_payment_intent_id');
            $table->unsignedSmallInteger('attempt_no');
            $table->string('routing_snapshot_ref', 64)->nullable();
            $table->string('network', 32);
            $table->unsignedBigInteger('binding_from');
            $table->unsignedBigInteger('binding_to');
            $table->string('status', 16);
            $table->string('failure_reason', 255)->nullable();
            $table->string('tx_reference', 96)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['identity_payment_intent_id', 'attempt_no'], 'settlement_attempts_intent_no_uq');
            $table->index(['identity_payment_intent_id', 'status'], 'settlement_attempts_intent_status_idx');

            $table->foreign('identity_payment_intent_id', 'settlement_attempts_intent_fk')
                ->references('id')
                ->on('identity_payment_intents')
                ->cascadeOnDelete();
            $table->foreign('binding_from', 'settlement_attempts_binding_from_fk')
                ->references('id')
                ->on('identity_bindings')
                ->nullOnDelete();
            $table->foreign('binding_to', 'settlement_attempts_binding_to_fk')
                ->references('id')
                ->on('identity_bindings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_attempts');
    }
};
