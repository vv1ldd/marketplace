<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\PlayStationObserver;

Schedule::command(PlayStationObserver::class)->everyFiveMinutes();



