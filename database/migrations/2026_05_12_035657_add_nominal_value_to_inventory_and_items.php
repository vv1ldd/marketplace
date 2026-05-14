<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_inventory', function (Blueprint $table) {
            $table->decimal('nominal_amount', 12, 2)->nullable()->after('sku');
            $table->string('nominal_currency', 3)->nullable()->after('nominal_amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('nominal_amount', 12, 2)->nullable()->after('sku');
            $table->string('nominal_currency', 3)->nullable()->after('nominal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('product_inventory', function (Blueprint $table) {
            $table->dropColumn(['nominal_amount', 'nominal_currency']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['nominal_amount', 'nominal_currency']);
        });
    }
};
