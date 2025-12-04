<?php

use App\Console\Commands\CheckNewOrderFromYM;
use App\Console\Commands\ImportWooUsers;
use App\Console\Commands\PlayStation\DetailFromRegion;
use App\Console\Commands\TranslateItems;
use App\Console\Commands\WooNewOrders;
use App\Console\Commands\WooPriceUpdate;
use Illuminate\Support\Facades\Schedule;

Schedule::command(DetailFromRegion::class)->everyTwoHours();
Schedule::command(CheckNewOrderFromYM::class)->everyMinute();
Schedule::command(TranslateItems::class)->at('23:00');
Schedule::command(WooPriceUpdate::class)->at('21:00');
Schedule::command(WooNewOrders::class)->everyMinute();
Schedule::command(ImportWooUsers::class)->hourly();

