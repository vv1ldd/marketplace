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
            ['code' => 'PL', 'name_ru' => 'Польша'],
            ['code' => 'ZA', 'name_ru' => 'ЮАР'],
            ['code' => 'AU', 'name_ru' => 'Австралия'],
            ['code' => 'HR', 'name_ru' => 'Хорватия'],
            ['code' => 'ID', 'name_ru' => 'Индонезия'],
            ['code' => 'MY', 'name_ru' => 'Малайзия'],
            ['code' => 'TH', 'name_ru' => 'Таиланд'],
            ['code' => 'PH', 'name_ru' => 'Филиппины'],
            ['code' => 'VN', 'name_ru' => 'Вьетнам'],
            ['code' => 'BR', 'name_ru' => 'Бразилия'],
            ['code' => 'SG', 'name_ru' => 'Сингапур'],
            ['code' => 'NO', 'name_ru' => 'Норвегия'],
            ['code' => 'SE', 'name_ru' => 'Швеция'],
            ['code' => 'DK', 'name_ru' => 'Дания'],
            ['code' => 'CH', 'name_ru' => 'Швейцария'],
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
