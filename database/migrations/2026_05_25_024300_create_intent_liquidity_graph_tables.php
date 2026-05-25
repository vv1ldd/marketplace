<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intent_liquidity_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('intent_key')->unique();
            $table->string('intent_type')->index();
            $table->string('actor_role')->index();
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('entity_slug')->nullable()->index();
            $table->string('entity_label')->nullable();
            $table->json('attributes')->nullable();
            $table->decimal('demand_score', 8, 4)->default(0);
            $table->decimal('readiness_score', 8, 4)->default(0);
            $table->decimal('confidence_score', 8, 4)->default(0);
            $table->string('status')->default('observed')->index();
            $table->timestamp('calculated_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('intent_liquidity_corridors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intent_liquidity_node_id')->constrained('intent_liquidity_nodes')->cascadeOnDelete();
            $table->string('corridor_type')->index();
            $table->string('corridor_key')->index();
            $table->string('source')->nullable()->index();
            $table->string('route_type')->nullable()->index();
            $table->decimal('route_score', 8, 4)->default(0);
            $table->decimal('capacity', 18, 4)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('friction_score', 8, 4)->default(0);
            $table->json('failure_modes')->nullable();
            $table->json('diagnostics')->nullable();
            $table->boolean('execution_ready')->default(false)->index();
            $table->timestamp('observed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['intent_liquidity_node_id', 'corridor_type', 'corridor_key', 'source'],
                'intent_liquidity_corridors_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intent_liquidity_corridors');
        Schema::dropIfExists('intent_liquidity_nodes');
    }
};
