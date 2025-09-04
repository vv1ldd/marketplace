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
        Schema::create('woo_synced_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_order_id');
            $table->string('connection');
            $table->json('created_result');
            $table->boolean('created_success');
            $table->timestamps();

            $table->unique(['woo_order_id', 'connection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woo_synced_orders');
    }
};
