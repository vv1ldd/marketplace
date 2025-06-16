<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('play_station_region_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('region_id');
            $table->uuid('category_id');
            $table->integer('count')->default(0);
            $table->integer('total_count')->default(0);
            $table->timestamps();

            $table->unique(['region_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('play_station_region_categories');
    }
};
