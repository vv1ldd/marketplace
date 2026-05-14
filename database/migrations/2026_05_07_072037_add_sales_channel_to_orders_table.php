<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Канал продаж: yandex_market, woocommerce, telegram_bot и т.д.
            $table->string('sales_channel', 50)->nullable()->after('shop_id')
                ->index()
                ->comment('ID канала продаж из config/sales_channels.php');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sales_channel');
        });
    }
};
