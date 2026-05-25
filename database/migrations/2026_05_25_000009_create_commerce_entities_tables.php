<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_entities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('entity_type')->default('digital_good')->index();
            $table->json('attributes')->nullable();
            $table->string('canonical_query')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('commerce_entity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_entity_id')->constrained('commerce_entities')->cascadeOnDelete();
            $table->string('link_type')->index();
            $table->unsignedBigInteger('link_id')->index();
            $table->decimal('confidence', 5, 2)->default(1.00);
            $table->json('signals')->nullable();
            $table->timestamps();

            $table->unique(['commerce_entity_id', 'link_type', 'link_id'], 'commerce_entity_links_unique');
            $table->index(['link_type', 'link_id'], 'commerce_entity_links_target_idx');
        });

        Schema::create('commerce_entity_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_entity_id')->unique()->constrained('commerce_entities')->cascadeOnDelete();
            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('carts')->default(0);
            $table->decimal('orders', 10, 2)->default(0);
            $table->decimal('attributed_gmv', 14, 2)->default(0);
            $table->decimal('estimated_lost_gmv', 14, 2)->default(0);
            $table->decimal('opportunity_score', 5, 2)->default(0);
            $table->unsignedInteger('active_cases')->default(0);
            $table->unsignedInteger('resolved_cases')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_entity_metrics');
        Schema::dropIfExists('commerce_entity_links');
        Schema::dropIfExists('commerce_entities');
    }
};
