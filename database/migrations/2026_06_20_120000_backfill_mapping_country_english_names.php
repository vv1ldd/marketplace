<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ((array) config('mapping_country_labels.en', []) as $code => $nameEn) {
            if (! is_string($nameEn) || trim($nameEn) === '') {
                continue;
            }

            DB::table('mapping_countries')
                ->where('code', $code)
                ->update(['name_en' => $nameEn]);
        }
    }

    public function down(): void
    {
        // Data backfill only.
    }
};
