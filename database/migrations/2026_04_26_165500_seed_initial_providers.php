<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Provider;

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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not strictly necessary but good practice
    }
};
