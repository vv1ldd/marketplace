<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('pictures')->nullable()->after('image');
            $table->string('vendor')->nullable()->after('name');
            $table->string('vendor_code')->nullable()->after('vendor');
            $table->string('barcode')->nullable()->after('vendor_code');
            $table->string('vat')->nullable()->after('price_rub');
            $table->decimal('weight', 10, 3)->nullable()->after('barcode');
            $table->string('dimensions')->nullable()->after('weight');
            $table->string('ym_url')->nullable()->after('data');
            $table->string('market_category_name')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'pictures',
                'vendor',
                'vendor_code',
                'barcode',
                'vat',
                'weight',
                'dimensions',
                'ym_url',
                'market_category_name'
            ]);
        });
    }
};
