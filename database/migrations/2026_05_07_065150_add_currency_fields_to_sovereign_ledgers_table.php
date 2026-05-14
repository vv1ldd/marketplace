<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            // Валюта события (ISO 4217: RUB, TRY, USD...)
            $table->string('currency', 3)->nullable()->after('payload')
                ->comment('Валюта транзакционного события ISO 4217');

            // Сумма в базовой валюте (RUB) для кросс-валютной аналитики
            $table->decimal('amount_base', 14, 2)->nullable()->after('currency')
                ->comment('Сумма в базовой валюте (RUB) на момент события');

            // Базовая валюта системы (всегда RUB, но заложено для гибкости)
            $table->string('base_currency', 3)->nullable()->default('RUB')->after('amount_base')
                ->comment('Базовая валюта системы для нормализации');

            // Курс на момент события
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('base_currency')
                ->comment('Курс валюты события к базовой на момент транзакции');
        });
    }

    public function down(): void
    {
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            $table->dropColumn(['currency', 'amount_base', 'base_currency', 'exchange_rate']);
        });
    }
};
