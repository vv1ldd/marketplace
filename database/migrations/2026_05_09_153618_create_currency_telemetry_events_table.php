<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 10)->index();
            $table->string('base_asset', 10)->default('USDT');
            
            // The Resolved "State"
            $table->decimal('executable_rate', 24, 8);
            $table->decimal('confidence_score', 5, 4);
            $table->unsignedTinyInteger('trust_tier');
            
            // The Evidence Graph
            $table->json('evidence_graph'); // { "signals": [...], "consensus": {...}, "rejections": [...] }
            $table->json('execution_reality')->nullable(); // { "max_size": 10000, "slippage": 0.05, "decay": 0.1 }
            
            // Metadata
            $table->string('trigger_source')->default('cron'); // cron, manual, anomaly_guard
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_telemetry_events');
    }
};
