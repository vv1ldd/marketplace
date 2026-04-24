<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Models\Settings;
use App\Models\Shop;
use Illuminate\Console\Command;

class MigrateLegacyShop extends Command
{
    protected $signature = 'shops:migrate-legacy';
    protected $description = 'Migrate legacy global settings and orders to the new Shops structure';

    public function handle()
    {
        $this->info('Starting legacy shop migration...');

        // 1. Собираем старые настройки
        $apiKey = Settings::get('YM_API_KEY', config('services.ym.api_key'));
        $campaignId = Settings::get('YM_CAMPAIGN_ID', config('services.ym.campaign_id'));
        $businessId = Settings::get('YM_BUSSINES_ID', config('services.ym.business_id')); // Заметьте опечатку в ключе, как в docker-compose
        $notificationToken = Settings::get('YM_NOTIFICATION_TOKEN', config('services.ym.notification_token'));
        $psTax = (int)Settings::get('PS_TAX', 35);
        $psTaxForSites = (int)Settings::get('PS_TAX_FOR_SITES', 35);

        if (!$apiKey || !$campaignId) {
            $this->error('Legacy settings not found. Make sure YM_API_KEY and YM_CAMPAIGN_ID are set.');
            return;
        }

        // 2. Ищем или создаем магазин
        $shop = Shop::where('campaign_id', $campaignId)->first();

        if (!$shop) {
            $shop = Shop::create([
                'name' => 'Основной магазин (Мигрирован)',
                'business_id' => $businessId,
                'campaign_id' => $campaignId,
                'api_key' => $apiKey,
                'notification_token' => $notificationToken,
                'ps_tax' => $psTax,
                'ps_tax_for_sites' => $psTaxForSites,
                'is_active' => true,
                'auto_purchase_enabled' => true,
            ]);
            $this->info("Created new shop: {$shop->name}");
        } else {
            // Обновляем налоги даже для существующего, если они еще не перенесены (опционально)
            $shop->update([
                'ps_tax' => $psTax,
                'ps_tax_for_sites' => $psTaxForSites,
            ]);
            $this->info("Updated taxes for shop: {$shop->name}");
        }

        // 3. Привязываем старые заказы
        $count = Order::whereNull('shop_id')->update(['shop_id' => $shop->id]);

        $this->info("Successfully linked {$count} orders to the shop.");
        $this->info('Migration complete!');
    }
}
