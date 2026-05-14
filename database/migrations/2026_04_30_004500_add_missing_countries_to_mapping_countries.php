<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mapping_countries', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });

        $countries = [
            ['code' => 'BE', 'name_ru' => 'Бельгия'],
            ['code' => 'PT', 'name_ru' => 'Португалия'],
            ['code' => 'SK', 'name_ru' => 'Словакия'],
            ['code' => 'LU', 'name_ru' => 'Люксембург'],
            ['code' => 'EU', 'name_ru' => 'Европа'],
            ['code' => 'LV', 'name_ru' => 'Латвия'],
            ['code' => 'LT', 'name_ru' => 'Литва'],
            ['code' => 'SI', 'name_ru' => 'Словения'],
            ['code' => 'CN', 'name_ru' => 'Китай'],
            ['code' => 'JO', 'name_ru' => 'Иордания'],
            ['code' => 'GR', 'name_ru' => 'Греция'],
            ['code' => 'HK', 'name_ru' => 'Гонконг'],
            ['code' => 'MENA', 'name_ru' => 'MENA (Ближний Восток)'],
        ];

        foreach ($countries as $country) {
            DB::table('mapping_countries')->updateOrInsert(
                ['code' => $country['code']],
                ['name_ru' => $country['name_ru']]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to delete these
    }
};
