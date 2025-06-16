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
        Schema::create('play_stations', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 36);
            $table->uuid('category_id');
            $table->uuid('region_id');
            $table->jsonb('data')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'category_id']);
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('play_stations');
    }
};
