<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_search_logs', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->text('normalized_query');
            $table->string('source'); // storefront, llm_retrieval, llm_understanding
            $table->string('intent')->nullable();
            $table->json('filters')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->integer('results_count')->default(0);
            $table->timestamps();

            // Indexes for speedy aggregations and lookups
            $table->index('source');
            $table->index('intent');
            $table->index('results_count');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_search_logs');
    }
};
