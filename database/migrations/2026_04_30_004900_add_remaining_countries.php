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
            ['code' => 'UY', 'name_ru' => 'Уругвай'],
            ['code' => 'RE', 'name_ru' => 'Реюньон'],
            ['code' => 'NG', 'name_ru' => 'Нигерия'],
            ['code' => 'DZ', 'name_ru' => 'Алжир'],
            ['code' => 'MA', 'name_ru' => 'Марокко'],
            ['code' => 'LB', 'name_ru' => 'Ливан'],
            ['code' => 'QA', 'name_ru' => 'Катар'],
            ['code' => 'KW', 'name_ru' => 'Кувейт'],
            ['code' => 'BH', 'name_ru' => 'Бахрейн'],
            ['code' => 'OM', 'name_ru' => 'Оман'],
            ['code' => 'JO', 'name_ru' => 'Иордания'],
            ['code' => 'IQ', 'name_ru' => 'Ирак'],
            ['code' => 'EG', 'name_ru' => 'Египет'],
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
