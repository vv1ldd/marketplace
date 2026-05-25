<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_gaps', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_query')->unique();
            $table->integer('search_volume')->default(0);
            $table->integer('zero_results_count')->default(0);
            $table->decimal('average_results_count', 8, 2)->default(0.00);
            $table->integer('attributed_orders_count')->default(0);
            $table->decimal('attributed_gmv', 12, 2)->default(0.00);
            $table->decimal('estimated_lost_gmv', 12, 2)->default(0.00);
            $table->decimal('demand_gap_score', 12, 2)->default(0.00);
            $table->string('priority_label')->default('low'); // critical, high, medium, low
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->index('demand_gap_score');
            $table->index('priority_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_gaps');
    }
};
