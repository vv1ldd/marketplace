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
                'name' => 'PlayStation Store',
                'is_active' => true,
                'settings' => ['tax' => 35],
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
