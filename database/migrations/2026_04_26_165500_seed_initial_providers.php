<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Provider;
use App\Models\PlayStation\PlayStationRegion;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'settings' => ['tax' => 30],
            ]
        );

        Provider::updateOrCreate(
            ['type' => 'playstation'],
            [
                'name' => 'PlayStation Store (TR)',
                'is_active' => true,
                'settings' => ['tax' => 35],
            ]
        );

        Provider::updateOrCreate(
            ['type' => 'playstation_us'],
            [
                'name' => 'PlayStation Store (US Bundles)',
                'is_active' => true,
                'settings' => ['tax' => 0], // Bundles use card prices which already have tax
            ]
        );

        PlayStationRegion::updateOrCreate(
            ['id' => '44d8bb20-653e-431e-8ad0-c0a365f68d2f'],
            [
                'name' => 'United States',
                'slug' => 'US',
            ]
        );

        PlayStationRegion::updateOrCreate(
            ['id' => '063101db-9ac0-4e48-a948-29fe7e3f8dec'],
            [
                'name' => 'Turkey',
                'slug' => 'TR',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not strictly necessary but good practice
    }
};
