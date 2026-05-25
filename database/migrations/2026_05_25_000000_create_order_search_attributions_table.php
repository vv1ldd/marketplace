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
        Schema::create('order_search_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('search_log_id')->constrained('catalog_search_logs')->cascadeOnDelete();
            $table->string('touch_type'); // 'first', 'last', 'middle'
            $table->decimal('attribution_weight', 4, 3); // weight e.g. 0.333
            $table->decimal('attributed_gmv', 12, 2); // GMV allocated to this search
            $table->timestamps();

            // Indexing for rapid joins in analytic reports and gap recalculations
            $table->index('search_log_id');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_search_attributions');
    }
};
