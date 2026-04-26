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
        Schema::table('shops', function (Blueprint $table) {
            $table->integer('ym_min_price')->nullable()->after('ym_warehouse_id')->comment('Минимальная цена для выгрузки');
            $table->bigInteger('ym_category_id')->nullable()->after('ym_min_price')->comment('Категория на Маркете по умолчанию');
            $table->integer('ym_diff_hours')->nullable()->after('ym_category_id')->comment('Разница в часах для скидки');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['ym_min_price', 'ym_category_id', 'ym_diff_hours']);
        });
    }
};
