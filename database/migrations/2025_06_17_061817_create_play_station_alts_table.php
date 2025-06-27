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
        Schema::create('play_station_alts', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 36);
            $table->uuid('region_id');
            $table->jsonb('data')->nullable();
            $table->timestamps();
            $table->integer('base_price')->nullable();
            $table->integer('price_with_discount')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('send_to_ym_at')->nullable();

            $table->unique(['sku', 'region_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('play_station_alts');
    }
};
