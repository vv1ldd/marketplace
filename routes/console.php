<?php

use App\Console\Commands\CheckNewOrderFromYM;
use App\Console\Commands\TranslateItems;
use App\Console\Commands\SyncCatalogsCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:update-currency-rates')->hourly()->after(function () {
    Artisan::call('app:sync-catalogs');
});
Schedule::command(CheckNewOrderFromYM::class)->everyMinute();
Schedule::command(TranslateItems::class)->at('23:00');
Schedule::command(SyncCatalogsCommand::class)->hourly();
Schedule::command(\App\Console\Commands\WildflowToMarket::class)->hourlyAt(30); // Run 30 mins after parser
Schedule::command(\App\Console\Commands\SendRedeemReminders::class)->everyFifteenMinutes();
Schedule::command(\App\Console\Commands\RetryFailedPurchases::class)->everyFifteenMinutes();
Schedule::command(\App\Console\Commands\NormalizeBrands::class)->hourlyAt(45); // Run 15 mins after WildflowToMarket

// 🔥 Keep hot storefront caches warm so runtime never pays the cold-start cost.
// TTL is 300s; warming the primary market every 4 min refreshes keys before they
// expire while keeping the loop cheap. Secondary markets warm lazily on request,
// plus a slower full-market sweep below.
Schedule::command('catalog:warm-cache')
    ->everyFourMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

// Slower full sweep so secondary markets also stay warm without loading the DB
// every few minutes.
Schedule::command('catalog:warm-cache --all-markets')
    ->everyThirtyMinutes()
    ->withoutOverlapping(20)
    ->runInBackground();

// 🚨 Operational alerts (fulfillment, checkout, disk, queue depth).
Schedule::command('meanly:check-alerts')->everyFiveMinutes()->withoutOverlapping();

// 🤖 Auto-healing loop for OOS items
Schedule::command(\App\Console\Commands\HealOutOfStockItems::class, ['--limit=50'])->everyFifteenMinutes();
Schedule::command('catalog:refresh-indexing --skip-audit --fail-on-internal-review-rate=35')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Yandex Market Bridge Sync
Schedule::command('ym:push-catalog')->everyFifteenMinutes();
Schedule::command('ym:sync-full')->dailyAt('02:00');
Schedule::command('ym:sync-params --limit=500')->dailyAt('03:00');
Schedule::command('ym:sync-categories')->weeklyOn(0, '04:00');
