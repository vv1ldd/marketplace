<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidity_corridors', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3)->index();
            $table->string('provider_node')->index(); // e.g. binance_p2p, hawala_dubai
            $table->string('routing_asset', 10)->default('USDT'); // The bridge currency
            $table->enum('direction', ['inbound', 'outbound', 'bidirectional'])->default('bidirectional');
            $table->unsignedTinyInteger('trust_tier')->default(3); // 1 = LSEG/SWIFT, 5 = Shadow
            
            // Financial Physics
            $table->decimal('base_fee_percent', 5, 2)->default(0.00); // Route cost
            $table->decimal('fixed_fee_amount', 12, 4)->default(0.00);
            $table->decimal('min_volume', 16, 4)->nullable(); // Capacity floor
            $table->decimal('max_volume', 16, 4)->nullable(); // Capacity ceiling
            $table->integer('sla_minutes')->default(60); // Settlement guarantees
            
            // State
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Route specific rules (e.g. "Cash only", "Needs KYC")
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidity_corridors');
    }
};
