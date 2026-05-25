<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach ([
            ['type' => 'wildflow', 'name' => 'Wildflow', 'settings' => ['tax' => 30]],
            ['type' => 'playstation', 'name' => 'PlayStation Store (TR)', 'settings' => ['tax' => 35]],
            ['type' => 'playstation_us', 'name' => 'PlayStation Store (US Bundles)', 'settings' => ['tax' => 0]],
        ] as $provider) {
            DB::table('providers')->updateOrInsert(
                ['type' => $provider['type']],
                [
                    'name' => $provider['name'],
                    'is_active' => true,
                    'settings' => json_encode($provider['settings'], JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        if (class_exists('App\Models\PlayStation\PlayStationRegion')) {
            \App\Models\PlayStation\PlayStationRegion::updateOrCreate(
                ['id' => '44d8bb20-653e-431e-8ad0-c0a365f68d2f'],
                [
                    'name' => 'United States',
                    'slug' => 'US',
                ]
            );

            \App\Models\PlayStation\PlayStationRegion::updateOrCreate(
                ['id' => '063101db-9ac0-4e48-a948-29fe7e3f8dec'],
                [
                    'name' => 'Turkey',
                    'slug' => 'TR',
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not strictly necessary but good practice
    }
};
