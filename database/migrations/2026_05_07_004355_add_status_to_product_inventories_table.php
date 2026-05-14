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
        Schema::table('product_inventory', function (Blueprint $table) {
            $table->string('status')->default('available')->after('is_used')->index();
            $table->timestamp('liquidated_at')->nullable()->after('status');
            $table->string('liquidation_reason')->nullable()->after('liquidated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_inventory', function (Blueprint $table) {
            $table->dropColumn(['status', 'liquidated_at', 'liquidation_reason']);
        });
    }
};
