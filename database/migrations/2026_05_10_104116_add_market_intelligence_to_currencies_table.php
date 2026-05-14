<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            if (!Schema::hasColumn('currencies', 'market_regime')) {
                $table->string('market_regime')->default('UNKNOWN')->after('obs_stability');
                $table->boolean('execution_ready')->default(false)->after('market_regime');
                $table->json('corridors')->nullable()->after('execution_ready');
            }
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['market_regime', 'execution_ready', 'corridors']);
        });
    }
};
