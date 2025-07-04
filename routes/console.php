<?php

use App\Console\Commands\PlayStation\DetailFromRegion;
use Illuminate\Support\Facades\Schedule;

Schedule::command(DetailFromRegion::class)->hourly();





