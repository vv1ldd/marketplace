<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (!Schema::hasColumn('currencies', 'base_asset')) {
                $table->string('base_asset')->default('USDT')->after('symbol');
                $table->string('quote_asset')->nullable()->after('base_asset');
                $table->decimal('price_last', 24, 10)->nullable()->after('quote_asset');
                
                $table->decimal('obs_agreement', 5, 4)->default(1.0)->after('observability_score');
                $table->decimal('obs_freshness', 5, 4)->default(1.0)->after('obs_agreement');
                $table->decimal('obs_stability', 5, 4)->default(1.0)->after('obs_freshness');
                
                $table->decimal('volatility_1h', 8, 4)->nullable()->after('obs_stability');
                $table->decimal('volatility_24h', 8, 4)->nullable()->after('volatility_1h');
            }
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn([
                'base_asset', 'quote_asset', 'price_last',
                'obs_agreement', 'obs_freshness', 'obs_stability',
                'volatility_1h', 'volatility_24h'
            ]);
        });
    }
};
