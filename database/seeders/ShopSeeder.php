<?php

namespace Database\Seeders;

use App\Models\Settings;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $psTax = (int)Settings::get('PS_TAX', 35);
        $psTaxForSites = (int)Settings::get('PS_TAX_FOR_SITES', 35);

        Shop::firstOrCreate(
            ['name' => 'Магазин игр'],
            [
                'business_id' => Settings::get('YM_BUSSINES_ID', 'default'),
                'campaign_id' => Settings::get('YM_CAMPAIGN_ID', 'default'),
                'api_key' => Settings::get('YM_API_KEY', 'default'),
                'notification_token' => Settings::get('YM_NOTIFICATION_TOKEN', 'default'),
                'ps_tax' => $psTax,
                'ps_tax_for_sites' => $psTaxForSites,
                'is_active' => true,
            ]
        );
    }
}
