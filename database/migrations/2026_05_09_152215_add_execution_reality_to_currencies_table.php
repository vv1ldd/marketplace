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
            $table->decimal('max_executable_size', 24, 2)->default(0)->after('confidence_score');
            $table->decimal('estimated_slippage', 5, 4)->default(0)->after('max_executable_size');
            $table->unsignedInteger('settlement_time_hours')->default(1)->after('estimated_slippage');
            $table->decimal('cashout_probability', 5, 4)->default(1.0)->after('settlement_time_hours');
            $table->timestamp('telemetry_updated_at')->nullable()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn([
                'max_executable_size', 
                'estimated_slippage', 
                'settlement_time_hours', 
                'cashout_probability',
                'telemetry_updated_at'
            ]);
        });
    }
};
