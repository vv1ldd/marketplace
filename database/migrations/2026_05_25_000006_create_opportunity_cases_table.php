<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('opportunity_cases', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_query')->index();
            $table->string('status')->default('open'); // open, in_progress, resolved, archived

            // Baseline Metrics (Before)
            $table->float('before_opportunity_score');
            $table->integer('before_search_volume');
            $table->integer('before_views_count');
            $table->integer('before_carts_count');
            $table->float('before_orders_count');
            $table->float('before_gmv');
            $table->string('before_diagnosis');
            $table->text('before_diagnosis_graph');

            // Action Metrics
            $table->string('action_type')->nullable(); // add_supply, improve_pricing, fix_checkout
            $table->text('action_details')->nullable();
            $table->timestamp('action_taken_at')->nullable();

            // Outcome Metrics (After)
            $table->float('after_opportunity_score')->nullable();
            $table->integer('after_search_volume')->nullable();
            $table->integer('after_views_count')->nullable();
            $table->integer('after_carts_count')->nullable();
            $table->float('after_orders_count')->nullable();
            $table->float('after_gmv')->nullable();
            $table->string('after_diagnosis')->nullable();
            $table->text('after_diagnosis_graph')->nullable();

            $table->float('gmv_growth_percentage')->nullable();
            $table->float('conversion_growth_percentage')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_cases');
    }
};
