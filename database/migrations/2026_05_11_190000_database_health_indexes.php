<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function getTableIndexes(string $table): \Illuminate\Support\Collection
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))->pluck('name')->unique();
        }
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))->pluck('Key_name')->unique();
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Optimize Product Inventory lookup
        if (Schema::hasTable('product_inventory')) {
            Schema::table('product_inventory', function (Blueprint $table) {
                if (!$this->getTableIndexes('product_inventory')->contains('product_inventory_sku_bidx_index')) {
                    $table->index('sku_bidx');
                }
            });
        }

        // 2. Optimize Products filtering by shop
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!$this->getTableIndexes('products')->contains('products_shop_id_index')) {
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
