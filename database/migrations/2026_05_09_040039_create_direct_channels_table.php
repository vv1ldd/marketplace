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
        Schema::create('direct_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('yandex_market');
            $table->boolean('is_active')->default(true);
            $table->string('business_id')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('woo_api_url')->nullable();
            $table->string('woo_consumer_key')->nullable();
            $table->string('woo_consumer_secret')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // Pivot table to link products directly to these channels
        Schema::create('direct_channel_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            
            $table->unique(['direct_channel_id', 'product_id'], 'dcp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_channel_product');
        Schema::dropIfExists('direct_channels');
    }
};
