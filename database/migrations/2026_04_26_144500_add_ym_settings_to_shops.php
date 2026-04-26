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
            $table->decimal('ym_tax', 8, 2)->default(30.00)->after('business_id')->comment('Наценка Яндекс.Маркета в %');
            $table->integer('ym_stock')->default(10)->after('ym_tax')->comment('Виртуальный остаток для Яндекс.Маркета');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['ym_tax', 'ym_stock']);
        });
    }
};
