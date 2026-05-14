<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Себестоимость в оригинальной валюте покупки (например, TRY)
            $table->decimal('cost_amount', 12, 2)->nullable()->after('currency')
                ->comment('Себестоимость заказа (закупка)');

            // Валюта себестоимости
            $table->string('cost_currency', 3)->nullable()->after('cost_amount')
                ->comment('Валюта закупки');

            // Себестоимость в базовой валюте (RUB)
            $table->decimal('cost_amount_base', 12, 2)->nullable()->after('cost_currency')
                ->comment('Себестоимость в базовой валюте (RUB)');
            
            // Чистая прибыль (маржа) в RUB
            $table->decimal('margin_base', 12, 2)->nullable()->after('cost_amount_base')
                ->comment('Чистая маржа в базовой валюте (RUB)');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cost_amount', 'cost_currency', 'cost_amount_base', 'margin_base']);
        });
    }
};
