<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\WildflowCatalog;
use App\Models\Product;

return new class extends Migration {
    public function up(): void
    {
        // Переносим данные из wildflow_catalogs в products
        WildflowCatalog::chunk(500, function ($items) {
            $products = [];
            foreach ($items as $item) {
                $data = $item->data['data'] ?? [];
                $productData = $data['product'] ?? [];
                
                $name = '';
                if (($productData['reward_type_text'] ?? '') === 'Gift-Card') {
                    $name .= 'Подарочная карта ';
                }
                
                $title = $productData['title'] ?? ($item->sku);
                $priceLabel = $data['price'] ?? '';
                $currencySymbol = $productData['currency']['code'] ?? '';
                
                $name .= $title . ' ' . $priceLabel . $currencySymbol;

                $products[] = [
                    'sku' => $item->sku,
                    'name' => $name,
                    'type' => 'wildflow',
                    'price_rub' => 0, // Цены Wildflow считаются динамически в контроллере
                    'price_try' => 0,
                    'data' => json_encode($item->data),
                    'is_active' => true,
                    'is_manual' => false,
                    'created_at' => $item->created_at ?? now(),
                    'updated_at' => $item->updated_at ?? now(),
                ];
            }
            
            \DB::table('products')->insertOrIgnore($products);
        });
    }

    public function down(): void
    {
        Product::where('type', 'wildflow')->delete();
    }
};
