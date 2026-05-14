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
        $countries = [
            ['code' => 'KR', 'name_ru' => 'Южная Корея'],
            ['code' => 'RO', 'name_ru' => 'Румыния'],
            ['code' => 'CZ', 'name_ru' => 'Чехия'],
            ['code' => 'MA', 'name_ru' => 'Марокко'],
            ['code' => 'LB', 'name_ru' => 'Ливан'],
            ['code' => 'EE', 'name_ru' => 'Эстония'],
            ['code' => 'HU', 'name_ru' => 'Венгрия'],
            ['code' => 'GR', 'name_ru' => 'Греция'],
            ['code' => 'IL', 'name_ru' => 'Израиль'],
            ['code' => 'JP', 'name_ru' => 'Япония'],
            ['code' => 'TW', 'name_ru' => 'Тайвань'],
            ['code' => 'RU', 'name_ru' => 'Россия'],
            ['code' => 'UA', 'name_ru' => 'Украина'],
            ['code' => 'KZ', 'name_ru' => 'Казахстан'],
            ['code' => 'UZ', 'name_ru' => 'Узбекистан'],
            ['code' => 'BY', 'name_ru' => 'Беларусь'],
            ['code' => 'AZ', 'name_ru' => 'Азербайджан'],
            ['code' => 'AM', 'name_ru' => 'Армения'],
            ['code' => 'GE', 'name_ru' => 'Грузия'],
            ['code' => 'MD', 'name_ru' => 'Молдова'],
            ['code' => 'KG', 'name_ru' => 'Киргизия'],
            ['code' => 'TJ', 'name_ru' => 'Таджикистан'],
            ['code' => 'TM', 'name_ru' => 'Туркменистан'],
            ['code' => 'CN', 'name_ru' => 'Китай'],
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
    public function down(): void {}
};
