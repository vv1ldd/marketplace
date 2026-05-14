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
        // 1. Optimize Product Inventory lookup
        if (Schema::hasTable('product_inventory')) {
            Schema::table('product_inventory', function (Blueprint $table) {
                if (!collect(DB::select("SHOW INDEX FROM product_inventory"))->pluck('Key_name')->contains('product_inventory_sku_bidx_index')) {
                    $table->index('sku_bidx');
                }
            });
        }

        // 2. Optimize Products filtering by shop
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!collect(DB::select("SHOW INDEX FROM products"))->pluck('Key_name')->contains('products_shop_id_index')) {
                    $table->index('shop_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_inventory')) {
            Schema::table('product_inventory', function (Blueprint $table) {
                $table->dropIndex(['sku_bidx']);
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['shop_id']);
            });
        }
    }
};
