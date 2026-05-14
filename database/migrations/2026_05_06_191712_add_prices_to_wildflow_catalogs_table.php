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
        Schema::table('wildflow_catalogs', function (Blueprint $blueprint) {
            $blueprint->decimal('retail_price', 16, 4)->nullable()->after('service_sku')->comment('Номинал / Рекомендованная розница (MSRP)');
            $blueprint->decimal('purchase_price', 16, 4)->nullable()->after('retail_price')->comment('Техническая цена закупки у провайдера');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wildflow_catalogs', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['retail_price', 'purchase_price']);
        });
    }
};
