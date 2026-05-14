<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_synced_orders', function (Blueprint $blueprint) {
            $blueprint->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('woo_synced_orders', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['shop_id']);
            $blueprint->dropColumn('shop_id');
        });
    }
};
