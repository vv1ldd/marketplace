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
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->decimal('demand_index', 8, 4)->default(1.0)->after('accessibility_score');
            $table->string('market_sentiment')->nullable()->after('demand_index'); // e.g. Hot, Stable, Cooling
            $table->timestamp('last_sentiment_audit_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->dropColumn(['demand_index', 'market_sentiment', 'last_sentiment_audit_at']);
        });
    }
};
