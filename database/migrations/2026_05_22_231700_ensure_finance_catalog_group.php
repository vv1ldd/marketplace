<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('catalog_groups')->where('slug', 'finansy')->exists();

        if ($exists) {
            DB::table('catalog_groups')->where('slug', 'finansy')->update([
                'name' => 'Финансы',
                'icon' => '💸',
                'sort_order' => 4,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('catalog_groups')->insert([
                'name' => 'Финансы',
                'icon' => '💸',
                'slug' => 'finansy',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            'igry' => 1,
            'podpiski' => 2,
            'popolnenie-sceta' => 3,
            'finansy' => 4,
            'soft' => 5,
            'riteil' => 6,
        ] as $slug => $sortOrder) {
            DB::table('catalog_groups')
                ->where('slug', $slug)
                ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('catalog_groups')->where('slug', 'finansy')->delete();
    }
};
