<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('canonical_product_identity_overrides')) {
            return;
        }

        Schema::create('canonical_product_identity_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canonical_product_identity_id')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->string('brand')->nullable();
            $table->string('product_family')->nullable();
            $table->decimal('face_value', 18, 4)->nullable();
            $table->string('face_value_currency', 16)->nullable();
            $table->string('region')->nullable();
            $table->string('platform')->nullable();
            $table->string('canonical_category', 64)->nullable();
            $table->string('confidence', 32)->nullable();
            $table->string('review_status', 32)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->foreignId('created_by')->nullable();
            $table->json('signals')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('canonical_product_identity_id', 'canonical_identity_overrides_identity_fk')
                ->references('id')
                ->on('canonical_product_identities')
                ->nullOnDelete();
            $table->foreign('reviewed_by', 'canonical_identity_overrides_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('created_by', 'canonical_identity_overrides_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['review_status', 'updated_at'], 'canonical_identity_overrides_review_status_updated_idx');
            $table->index('canonical_product_identity_id', 'canonical_identity_overrides_identity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canonical_product_identity_overrides');
    }
};
