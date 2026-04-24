<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ApiApplication;
use App\Models\Shop;

return new class extends Migration
{
    public function up()
    {
        $apps = ApiApplication::all();

        foreach ($apps as $app) {
            $shop = null;

            // 1. Try by shop_id if available
            if ($app->shop_id) {
                $shop = Shop::find($app->shop_id);
            }

            // 2. Try by exact domain
            if (!$shop && $app->domain) {
                $shop = Shop::where('domain', $app->domain)->first();
            }

            // 3. Try by partial domain match (e.g. meanly.ru match marketplace.meanly.ru)
            if (!$shop && $app->domain) {
                $shop = Shop::where('domain', 'like', '%' . $app->domain . '%')->first();
            }

            // 4. Try by name
            if (!$shop && $app->name) {
                $shop = Shop::where('name', $app->name)->first();
            }
            
            // Special case for Meanly as seen in screenshots
            if (!$shop && (str_contains(strtolower($app->name), 'meanly') || str_contains(strtolower($app->domain), 'meanly'))) {
                $shop = Shop::where('name', 'MEANLY')
                    ->orWhere('domain', 'like', '%meanly.ru%')
                    ->first();
            }

            if ($shop && $app->token) {
                $shop->update([
                    'store_api_token' => $app->token,
                ]);
                \Illuminate\Support\Facades\Log::info("Migrated token for shop: {$shop->name} from app: {$app->name}");
            } else {
                \Illuminate\Support\Facades\Log::warning("Could not find shop for app: id={$app->id}, name={$app->name}, domain={$app->domain}");
            }
        }
    }

    public function down()
    {
        // No easy way to reverse this
    }
};
