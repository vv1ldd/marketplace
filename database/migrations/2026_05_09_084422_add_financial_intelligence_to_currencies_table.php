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
            $table->decimal('official_rate', 16, 4)->nullable()->after('rate_to_rub')->comment('Курс ЦБ');
            $table->decimal('spread_percent', 8, 4)->nullable()->after('official_rate')->comment('Спред P2P к ЦБ (%)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['official_rate', 'spread_percent']);
        });
    }
};
