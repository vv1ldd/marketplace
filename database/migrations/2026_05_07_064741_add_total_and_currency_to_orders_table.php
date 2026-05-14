<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Денормализованная итоговая сумма — для быстрых запросов,
            // аналитики и Sovereign Ledger без парсинга JSON
            $table->decimal('total_amount', 12, 2)->nullable()->after('shop_id')
                ->comment('Итоговая сумма заказа (денормализованная)');

            // Валюта заказа: RUB, TRY, USD и т.д.
            $table->string('currency', 3)->nullable()->after('total_amount')
                ->comment('Код валюты ISO 4217: RUB, TRY, USD...');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'currency']);
        });
    }
};
