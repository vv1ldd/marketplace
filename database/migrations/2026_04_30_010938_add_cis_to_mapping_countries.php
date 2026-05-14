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
        $regions = [
            ['code' => 'CIS', 'name_ru' => 'СНГ (CIS)'],
        ];

        foreach ($regions as $region) {
            DB::table('mapping_countries')->updateOrInsert(
                ['code' => $region['code']],
                ['name_ru' => $region['name_ru']]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mapping_countries')->where('code', 'CIS')->delete();
    }
};
