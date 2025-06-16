<?php

namespace Database\Seeders;

use App\Models\PlayStation\PlayStationCategory;
use App\Models\PlayStation\PlayStationRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use PlaystationStoreApi\Enum\CategoryEnum;
use PlaystationStoreApi\Enum\RegionEnum;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $ps_categories = array_column(CategoryEnum::cases(), 'value', 'name');

        foreach ($ps_categories as $key => $value) {
            PlayStationCategory::insertOrIgnore([
                'id' => $value,
                'name' => $key
            ]);
        }

        $ps_regions = array_column(RegionEnum::cases(), 'value', 'name');

        foreach ($ps_regions as $key => $value) {
            PlayStationRegion::insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'name' => $key,
                'slug' => $value
            ]);
        }
    }
}
