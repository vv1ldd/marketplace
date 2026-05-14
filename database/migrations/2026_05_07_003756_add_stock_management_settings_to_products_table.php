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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('low_stock_notification_threshold')->default(10)->after('is_active');
            $table->boolean('auto_replenish_enabled')->default(false)->after('low_stock_notification_threshold');
            $table->integer('auto_replenish_threshold')->default(2)->after('auto_replenish_enabled');
            $table->integer('auto_replenish_quantity')->default(1)->after('auto_replenish_threshold');
            $table->timestamp('last_low_stock_notification_at')->nullable()->after('auto_replenish_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'low_stock_notification_threshold',
                'auto_replenish_enabled',
                'auto_replenish_threshold',
                'auto_replenish_quantity',
                'last_low_stock_notification_at',
            ]);
        });
    }
};
