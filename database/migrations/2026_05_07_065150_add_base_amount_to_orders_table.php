<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Сумма в базовой валюте (RUB) для сводной аналитики
            $table->decimal('total_amount_base', 12, 2)->nullable()->after('currency')
                ->comment('Итоговая сумма в базовой валюте (RUB) — для кросс-валютной аналитики');

            // Курс на момент создания заказа
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('total_amount_base')
                ->comment('Курс валюты заказа к RUB на момент создания');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['total_amount_base', 'exchange_rate']);
        });
    }
};
