<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_demand_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_hash', 64)->unique();
            $table->string('type')->index();
            $table->text('query');
            $table->string('normalized_query', 512)->index();
            $table->string('insight_type')->index();
            $table->json('expected_entity')->nullable();
            $table->decimal('impact_score', 8, 2)->default(0)->index();
            $table->decimal('confidence', 5, 2)->default(0)->index();
            $table->json('evidence')->nullable();
            $table->string('status')->default('proposed')->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'impact_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_demand_recommendations');
    }
};
