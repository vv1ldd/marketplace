<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            // null = мастер-склад, иначе ключ канала продаж
            $table->string('channel')->nullable()->after('is_main')
                ->comment('null = мастер. Иначе: yandex_market | ozon | wildberries | avito');

            // Квота (%) от мастер-склада для этого канала
            $table->unsignedTinyInteger('channel_quota')->default(100)->after('channel')
                ->comment('Процент остатков мастера, выделенный этому каналу');

            // ym_id — делаем nullable, т.к. мастер-склад не имеет YM ID
            $table->bigInteger('ym_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['channel', 'channel_quota']);
            $table->bigInteger('ym_id')->nullable(false)->change();
        });
    }
};
