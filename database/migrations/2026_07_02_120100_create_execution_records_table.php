<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('intent_id')->nullable()->index();
            $table->foreignId('canonical_product_identity_id')
                ->constrained('canonical_product_identities')
                ->cascadeOnDelete();
            $table->foreignUuid('offer_snapshot_id')
                ->constrained('offer_snapshots')
                ->cascadeOnDelete();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();

            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('provider_order_id')->nullable();

            $table->string('state', 50)->default('reserved');
            $table->string('error_class', 100)->nullable();
            $table->string('vault_reference_id')->nullable();

            $table->json('audit_payload')->nullable();
            $table->timestamps();

            $table->index('order_id', 'execution_records_order_idx');
            $table->index('offer_snapshot_id', 'execution_records_snapshot_idx');
            $table->index(['state', 'created_at'], 'execution_records_state_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_records');
    }
};
