<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SovereignCalendar;
use Carbon\Carbon;

class HolidayApiController extends Controller
{
    /**
     * Get the database of all festive periods and the currently active one.
     */
    public function getActiveHoliday(Request $request)
    {
        $events = SovereignCalendar::all();
        $holidays = [];

        // Map unified SovereignCalendar events back to the exact API response format
        foreach ($events as $key => $e) {
            $isSingle = ($e['day_from'] === $e['day_to']);
            $holidays[$key] = [
                'name'        => $e['name'],
                'title_ru'    => $e['title_ru'],
                'description' => $e['description'],
                'type'        => $isSingle ? 'single' : 'range',
                'rule'        => $isSingle 
                    ? ['month' => $e['month'], 'day' => $e['day_from']]
                    : [
                        'start_month' => $e['month'],
                        'start_day'   => $e['day_from'],
                        'end_month'   => $e['month'],
                        'end_day'     => $e['day_to']
                      ],
                'aesthetics'  => $e['aesthetics']
            ];
        }

        // 1. Resolve targeted date (supports manual ?date=YYYY-MM-DD or defaults to local time)
        $dateParam = $request->get('date');
        $now = $dateParam ? Carbon::parse($dateParam) : Carbon::now();

        // 2. Resolve active holiday key via SovereignCalendar
        $activeHolidayKey = SovereignCalendar::resolve($now);

        // 3. Allow manual bypass via ?holiday=xxx query param
        $manualHoliday = $request->get('holiday');
        if ($manualHoliday && $manualHoliday !== 'none') {
            $manualHoliday = strtolower($manualHoliday);
            if (array_key_exists($manualHoliday, $holidays)) {
                $activeHolidayKey = $manualHoliday;
            }
        }

        $activeHolidayData = null;
        if ($activeHolidayKey && isset($holidays[$activeHolidayKey])) {
            $activeHolidayData = array_merge(['id' => $activeHolidayKey], $holidays[$activeHolidayKey]);
        }

        return response()->json([
            'status'         => 'success',
            'server_time'    => Carbon::now()->toIso8601String(),
            'queried_date'   => $now->toDateString(),
            'active_holiday' => $activeHolidayData,
            'all_holidays'   => $holidays
        ]);
    }
}
