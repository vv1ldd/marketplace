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
        Schema::table('currency_liquidity_method', function (Blueprint $table) {
            $table->integer('risk_score')->default(10)->after('fee_percent'); // 0-100
            $table->decimal('latency_hours', 8, 2)->default(0.5)->after('risk_score'); 
            $table->decimal('success_rate', 5, 2)->default(99.9)->after('latency_hours'); // percentage
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currency_liquidity_method', function (Blueprint $table) {
            $table->dropColumn(['risk_score', 'latency_hours', 'success_rate']);
        });
    }
};
