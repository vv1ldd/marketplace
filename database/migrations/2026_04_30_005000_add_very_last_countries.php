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
            ['code' => 'MV', 'name_ru' => 'Мальдивы'],
            ['code' => 'HN', 'name_ru' => 'Гондурас'],
            ['code' => 'GT', 'name_ru' => 'Гватемала'],
            ['code' => 'BD', 'name_ru' => 'Бангладеш'],
            ['code' => 'DZ', 'name_ru' => 'Алжир'],
            ['code' => 'MA', 'name_ru' => 'Марокко'],
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
