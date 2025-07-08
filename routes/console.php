<?php

use App\Console\Commands\CheckNewOrderFromYM;
use App\Console\Commands\PlayStation\DetailFromRegion;
use App\Console\Commands\TranslateItems;
use Illuminate\Support\Facades\Schedule;

Schedule::command(DetailFromRegion::class)->hourly();
Schedule::command(CheckNewOrderFromYM::class)->everyMinute();
Schedule::command(TranslateItems::class)->at('23:00');





