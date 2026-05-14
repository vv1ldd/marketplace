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
            ['code' => 'DZ', 'name_ru' => 'Алжир'],
            ['code' => 'AR', 'name_ru' => 'Аргентина'],
            ['code' => 'NZ', 'name_ru' => 'Новая Зеландия'],
            ['code' => 'TH', 'name_ru' => 'Таиланд'],
            ['code' => 'MY', 'name_ru' => 'Малайзия'],
            ['code' => 'PH', 'name_ru' => 'Филиппины'],
            ['code' => 'VN', 'name_ru' => 'Вьетнам'],
            ['code' => 'ID', 'name_ru' => 'Индонезия'],
            ['code' => 'SG', 'name_ru' => 'Сингапур'],
            ['code' => 'HK', 'name_ru' => 'Гонконг'],
            ['code' => 'TW', 'name_ru' => 'Тайвань'],
            ['code' => 'JP', 'name_ru' => 'Япония'],
            ['code' => 'KR', 'name_ru' => 'Южная Корея'],
            ['code' => 'NO', 'name_ru' => 'Норвегия'],
            ['code' => 'SE', 'name_ru' => 'Швеция'],
            ['code' => 'FI', 'name_ru' => 'Финляндия'],
            ['code' => 'DK', 'name_ru' => 'Дания'],
            ['code' => 'IS', 'name_ru' => 'Исландия'],
            ['code' => 'CH', 'name_ru' => 'Швейцария'],
            ['code' => 'AT', 'name_ru' => 'Австрия'],
            ['code' => 'BE', 'name_ru' => 'Бельгия'],
            ['code' => 'NL', 'name_ru' => 'Нидерланды'],
            ['code' => 'LU', 'name_ru' => 'Люксембург'],
            ['code' => 'IE', 'name_ru' => 'Ирландия'],
            ['code' => 'PT', 'name_ru' => 'Португалия'],
            ['code' => 'GR', 'name_ru' => 'Греция'],
            ['code' => 'CY', 'name_ru' => 'Кипр'],
            ['code' => 'MT', 'name_ru' => 'Мальта'],
            ['code' => 'CZ', 'name_ru' => 'Чехия'],
            ['code' => 'SK', 'name_ru' => 'Словакия'],
            ['code' => 'HU', 'name_ru' => 'Венгрия'],
            ['code' => 'PL', 'name_ru' => 'Польша'],
            ['code' => 'RO', 'name_ru' => 'Румыния'],
            ['code' => 'BG', 'name_ru' => 'Болгария'],
            ['code' => 'HR', 'name_ru' => 'Хорватия'],
            ['code' => 'SI', 'name_ru' => 'Словения'],
            ['code' => 'EE', 'name_ru' => 'Эстония'],
            ['code' => 'LV', 'name_ru' => 'Латвия'],
            ['code' => 'LT', 'name_ru' => 'Литва'],
            ['code' => 'IL', 'name_ru' => 'Израиль'],
            ['code' => 'JO', 'name_ru' => 'Иордания'],
            ['code' => 'LB', 'name_ru' => 'Ливан'],
            ['code' => 'MA', 'name_ru' => 'Марокко'],
            ['code' => 'TN', 'name_ru' => 'Тунис'],
            ['code' => 'CO', 'name_ru' => 'Колумбия'],
            ['code' => 'PE', 'name_ru' => 'Перу'],
            ['code' => 'CL', 'name_ru' => 'Чили'],
            ['code' => 'MX', 'name_ru' => 'Мексика'],
            ['code' => 'BR', 'name_ru' => 'Бразилия'],
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
