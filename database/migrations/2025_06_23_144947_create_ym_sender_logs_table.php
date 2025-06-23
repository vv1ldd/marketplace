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
        Schema::create('ym_sender_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('lang_region_id');
            $table->uuid('price_region_id');
            $table->string('send_id', 16);
            $table->jsonb('request');
            $table->string('status')->default('pending');
            $table->jsonb('response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ym_sender_logs');
    }
};
