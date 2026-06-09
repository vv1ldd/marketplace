<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_deposit_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->string('rail', 40);
            $table->string('status', 32)->default('draft');
            $table->string('reference', 80)->unique();
            $table->decimal('amount', 16, 4);
            $table->string('currency', 10)->default('RUB');
            $table->string('idempotency_key', 160)->unique();
            $table->json('invoice_payload')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->foreignId('credited_ledger_id')->nullable()->constrained('sovereign_ledger')->nullOnDelete();
            $table->timestamps();

            $table->index(['legal_entity_id', 'status']);
            $table->index(['rail', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_deposit_intents');
    }
};
