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
        Schema::table('currencies', function (Blueprint $table) {
            $table->decimal('p2p_bybit', 16, 4)->nullable()->after('spread_percent')->comment('Курс Bybit P2P');
            $table->decimal('p2p_telegram', 16, 4)->nullable()->after('p2p_bybit')->comment('Курс TG Wallet (Bybit +3%)');
            $table->decimal('p2p_kucoin', 16, 4)->nullable()->after('p2p_telegram')->comment('Курс KuCoin P2P');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['p2p_bybit', 'p2p_telegram', 'p2p_kucoin']);
        });
    }
};
