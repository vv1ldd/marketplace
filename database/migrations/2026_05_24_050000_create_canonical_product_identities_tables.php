<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canonical_product_identities')) {
            Schema::create('canonical_product_identities', function (Blueprint $table) {
                $table->id();
                $table->string('fingerprint', 64)->unique();
                $table->string('identity_slug', 160)->unique();
                $table->string('canonical_category', 64)->nullable()->index();
                $table->string('brand')->nullable();
                $table->string('product_family')->nullable();
                $table->decimal('face_value', 18, 4)->nullable();
                $table->string('face_value_currency', 16)->nullable();
                $table->string('region')->nullable();
                $table->string('platform')->nullable();
                $table->string('confidence', 32)->nullable()->index();
                $table->json('signals')->nullable();
                $table->unsignedInteger('provider_candidates_count')->default(0);
                $table->unsignedInteger('seller_offers_count')->default(0);
                $table->foreignId('best_offer_product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('canonical_product_identity_sources')) {
            Schema::create('canonical_product_identity_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('canonical_product_identity_id');
                $table->string('source_type', 64)->index();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('source_sku')->nullable();
                $table->string('confidence', 32)->nullable();
                $table->json('signals')->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();

                $table->foreign('canonical_product_identity_id', 'canonical_identity_sources_identity_fk')
                    ->references('id')
                    ->on('canonical_product_identities')
                    ->cascadeOnDelete();
                $table->unique(['source_type', 'source_id'], 'canonical_identity_sources_source_unique');
                $table->index(['canonical_product_identity_id', 'source_type'], 'canonical_identity_sources_identity_type_idx');
            });
        }

        if (
            Schema::hasTable('canonical_product_identity_sources')
            && ! $this->hasSourceIdentityForeignKey()
        ) {
            Schema::table('canonical_product_identity_sources', function (Blueprint $table) {
                $table->foreign('canonical_product_identity_id', 'canonical_identity_sources_identity_fk')
                    ->references('id')
                    ->on('canonical_product_identities')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_product_identity_sources');
        Schema::dropIfExists('canonical_product_identities');
    }

    private function hasSourceIdentityForeignKey(): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return true;
        }

        return $connection->selectOne(
            <<<'SQL'
            select constraint_name
            from information_schema.key_column_usage
            where table_schema = ?
              and table_name = ?
              and column_name = ?
              and referenced_table_name = ?
            limit 1
            SQL,
            [
                $connection->getDatabaseName(),
                'canonical_product_identity_sources',
                'canonical_product_identity_id',
                'canonical_product_identities',
            ],
        ) !== null;
    }
};
