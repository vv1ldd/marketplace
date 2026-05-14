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
            $table->dropColumn('p2p_kucoin');
            $table->decimal('p2p_binance', 16, 4)->after('p2p_bybit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->decimal('p2p_kucoin', 16, 4)->nullable();
            $table->dropColumn('p2p_binance');
        });
    }
};
