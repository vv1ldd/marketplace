<?php

namespace Tests\Feature;

use Tests\TestCase;

class HolidayApiTest extends TestCase
{
    /**
     * Test active holiday resolution for natural calendar days and manual overrides.
     */
    public function test_active_holiday_api()
    {
        // 1. Check basic response structure
        $response = $this->getJson('/api/holidays/active');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'server_time',
                'queried_date',
                'active_holiday',
                'all_holidays'
            ]);

        // 2. Test manual override via holiday parameter
        $response = $this->getJson('/api/holidays/active?holiday=sons-birthday');
        $response->assertStatus(200)
            ->assertJsonPath('active_holiday.id', 'sons-birthday')
            ->assertJsonPath('active_holiday.name', 'Sovereign Heir Day');

        // 3. Test date-based resolution for May 19
        $response = $this->getJson('/api/holidays/active?date=2026-05-19');
        $response->assertStatus(200)
            ->assertJsonPath('active_holiday.id', 'sons-birthday');

        // 4. Test date-based resolution for May 12 (Orchid Day)
        $response = $this->getJson('/api/holidays/active?date=2026-05-12');
        $response->assertStatus(200)
            ->assertJsonPath('active_holiday.id', 'orchid-day');
    }
}
