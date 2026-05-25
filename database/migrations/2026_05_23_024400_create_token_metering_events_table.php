<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_metering_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('sovereign_ledger_id')->nullable()->constrained('sovereign_ledger')->nullOnDelete();
            $table->string('event_type');
            $table->string('layer')->default('usage');
            $table->string('tariff_key');
            $table->string('tariff_version');
            $table->string('idempotency_key')->nullable()->unique();
            $table->nullableMorphs('source');
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('unit')->default('event');
            $table->decimal('sl1_amount', 18, 4)->default(0);
            $table->decimal('rub_equivalent', 18, 2)->default(0);
            $table->decimal('estimated_value_rub', 18, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['legal_entity_id', 'occurred_at']);
            $table->index(['shop_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index(['layer', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_metering_events');
    }
};
