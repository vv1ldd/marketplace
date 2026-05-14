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
        Schema::create('currency_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_currency_id')->constrained('currencies')->onDelete('cascade');
            $table->foreignId('target_currency_id')->constrained('currencies')->onDelete('cascade');
            
            // The 4 Layers of Truth
            $table->decimal('official_rate', 20, 10)->nullable();
            $table->decimal('tradfi_rate', 20, 10)->nullable();
            $table->decimal('spot_rate', 20, 10)->nullable();
            $table->decimal('p2p_rate', 20, 10)->nullable();
            
            // Bid/Ask for P2P
            $table->decimal('p2p_buy_rate', 20, 10)->nullable();
            $table->decimal('p2p_sell_rate', 20, 10)->nullable();
            
            $table->decimal('spread_percent', 8, 4)->default(0);
            $table->integer('liquidity_score')->default(50); // 0-100
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['base_currency_id', 'target_currency_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_pairs');
    }
};
