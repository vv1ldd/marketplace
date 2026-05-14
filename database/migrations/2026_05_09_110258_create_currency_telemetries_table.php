<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_telemetries', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3)->index();
            $table->decimal('rate', 14, 4);
            $table->string('source_type'); // telegram, p2p_spread, manual, import_arbitrage
            $table->string('source_name')->nullable(); // @ashgabat_exchange_bot, etc.
            $table->string('city')->nullable();
            $table->float('confidence')->default(0.5); // Trust score 0.0 - 1.0
            $table->json('payload')->nullable(); // Raw data for audit
            $table->timestamp('observed_at');
            $table->timestamps();
        });

        // Also add buy/sell rates to currencies for spread monitoring
        Schema::table('currencies', function (Blueprint $table) {
            $table->decimal('shadow_buy_rate', 14, 4)->nullable()->after('manual_rate');
            $table->decimal('shadow_sell_rate', 14, 4)->nullable()->after('shadow_buy_rate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_telemetries');
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['shadow_buy_rate', 'shadow_sell_rate']);
        });
    }
};
