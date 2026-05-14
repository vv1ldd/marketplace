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
            $table->float('p2p_rate_usdt', 16, 8)->nullable()->after('p2p_source');
            $table->float('spot_rate_usdt', 16, 8)->nullable()->after('p2p_rate_usdt');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['p2p_rate_usdt', 'spot_rate_usdt']);
        });
    }
};
