<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('identity_payment_accounting_event_id');
            $table->unsignedBigInteger('settlement_attempt_id');
            $table->boolean('identity_from_match');
            $table->boolean('identity_to_match');
            $table->boolean('asset_match');
            $table->boolean('amount_match');
            $table->string('status', 16);
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique('identity_payment_accounting_event_id', 'recon_records_acct_uq');
            $table->index(['status', 'created_at'], 'recon_records_status_idx');

            $table->foreign('identity_payment_accounting_event_id', 'recon_records_acct_fk')
                ->references('id')
                ->on('identity_payment_accounting_events')
                ->cascadeOnDelete();
            $table->foreign('settlement_attempt_id', 'recon_records_attempt_fk')
                ->references('id')
                ->on('settlement_attempts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
    }
};
