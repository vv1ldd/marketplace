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
            $table->decimal('rate_to_usdt', 16, 8)->nullable()->after('rate_to_rub')->comment('Market rate: Units of fiat per 1 USDT');
            $table->decimal('official_rate_usdt', 16, 8)->nullable()->after('official_rate')->comment('Official rate: Units of fiat per 1 USDT');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['rate_to_usdt', 'official_rate_usdt']);
        });
    }
};
