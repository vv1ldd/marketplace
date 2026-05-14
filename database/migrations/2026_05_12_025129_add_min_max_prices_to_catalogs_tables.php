<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_products', function (Blueprint $table) {
            $table->decimal('min_price', 16, 4)->nullable()->after('retail_price');
            $table->decimal('max_price', 16, 4)->nullable()->after('min_price');
        });

        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $table->decimal('min_price', 16, 4)->nullable()->after('purchase_price');
            $table->decimal('max_price', 16, 4)->nullable()->after('min_price');
        });
    }

    public function down(): void
    {
        Schema::table('provider_products', function (Blueprint $table) {
            $table->dropColumn(['min_price', 'max_price']);
        });

        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $table->dropColumn(['min_price', 'max_price']);
        });
    }
};
