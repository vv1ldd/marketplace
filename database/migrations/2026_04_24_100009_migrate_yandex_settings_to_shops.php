<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Settings;
use App\Models\Shop;

return new class extends Migration
{
    public function up()
    {
        $businessId = Settings::get('YM_BUSINESS_ID');
        $campaignId = Settings::get('YM_CAMPAIGN_ID');
        $apiKey = Settings::get('YM_API_KEY');

        if ($businessId || $campaignId || $apiKey) {
            $shop = Shop::where('name', 'MEANLY')
                ->orWhere('domain', 'meanly.ru')
                ->first();

            if ($shop) {
                $shop->update([
                    'business_id' => $businessId ?: $shop->business_id,
                    'campaign_id' => $campaignId ?: $shop->campaign_id,
                    'api_key' => $apiKey ?: $shop->api_key,
                ]);
            }
        }
    }

    public function down()
    {
    }
};
