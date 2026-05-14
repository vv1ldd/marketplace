<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add shop_id column
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // 2. Data Migration: Migrate existing links from product_shop to products table
        // We need to iterate over all existing links. 
        // If a product has multiple shops, we duplicate the product record for each additional shop.
        $links = DB::table('product_shop')->orderBy('product_id')->get();
        $processedProductIds = [];

        foreach ($links as $link) {
            if (!in_array($link->product_id, $processedProductIds)) {
                // First time we see this product, just set the shop_id
                DB::table('products')
                    ->where('id', $link->product_id)
                    ->update(['shop_id' => $link->shop_id]);
                
                $processedProductIds[] = $link->product_id;
            } else {
                // This product is shared! Create a duplicate for the other shop.
                $original = DB::table('products')->where('id', $link->product_id)->first();
                if ($original) {
                    $newProduct = (array) $original;
                    unset($newProduct['id']);
                    $newProduct['shop_id'] = $link->shop_id;
                    $newProduct['created_at'] = now();
                    $newProduct['updated_at'] = now();
                    
                    DB::table('products')->insert($newProduct);
                }
            }
        }

        // 3. Drop the pivot table
        Schema::dropIfExists('product_shop');

        // 4. Update Unique Constraints
        // Note: We might need to drop old unique constraints on SKU if they exist globally.
        // Let's check existing indexes if possible, or just add the new composite one.
        Schema::table('products', function (Blueprint $table) {
            $table->index(['shop_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::create('product_shop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->unique(['product_id', 'shop_id']);
            $table->timestamps();
        });

        // Migrate back (approximate)
        $products = DB::table('products')->whereNotNull('shop_id')->get();
        foreach ($products as $product) {
            DB::table('product_shop')->insert([
                'product_id' => $product->id,
                'shop_id' => $product->shop_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
