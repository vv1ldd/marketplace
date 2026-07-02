<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('snapshot_uuid')->unique();
            $table->foreignId('canonical_product_identity_id')
                ->constrained('canonical_product_identities')
                ->cascadeOnDelete();
            $table->string('entitlement_fingerprint', 64)->index();

            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku');
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('provider_product_id')->constrained('provider_products')->cascadeOnDelete();
            $table->string('provider_sku');
            $table->string('offer_kind', 50);

            $table->unsignedBigInteger('buyer_price_cents');
            $table->char('buyer_currency', 3);
            $table->unsignedBigInteger('purchase_price_cents');
            $table->unsignedBigInteger('storage_price_cents')->default(0);

            $table->string('fulfillment_mode', 50);
            $table->unsignedInteger('stock_count')->nullable();
            $table->decimal('ranking_score', 8, 2)->nullable();
            $table->json('full_payload_json');

            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable()->index();
            $table->uuid('superseded_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['canonical_product_identity_id', 'valid_until'], 'offer_snapshots_identity_valid_idx');
            $table->index(['provider_id', 'provider_sku'], 'offer_snapshots_provider_sku_idx');
            $table->foreign('superseded_by_id', 'offer_snapshots_superseded_by_fk')
                ->references('id')
                ->on('offer_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_snapshots');
    }
};
