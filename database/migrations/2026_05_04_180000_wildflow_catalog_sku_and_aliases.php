<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('wildflow_catalog_sku', 255)->nullable()->after('sku');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('wildflow_catalog_sku');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE products p
                INNER JOIN wildflow_catalogs w ON w.sku = p.sku
                SET p.wildflow_catalog_sku = w.sku
                WHERE p.wildflow_catalog_sku IS NULL
            ');
        } else {
            foreach (DB::table('wildflow_catalogs')->select('sku')->cursor() as $row) {
                DB::table('products')
                    ->where('sku', $row->sku)
                    ->whereNull('wildflow_catalog_sku')
                    ->update(['wildflow_catalog_sku' => $row->sku]);
            }
        }

        Schema::create('wildflow_sku_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias_sku', 255);
            $table->string('wildflow_catalog_sku', 255);
            $table->timestamps();

            $table->unique('alias_sku');
            $table->index('wildflow_catalog_sku');
        });

        DB::table('products')
            ->whereNotNull('wildflow_catalog_sku')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('wildflow_sku_aliases')->updateOrInsert(
                        ['alias_sku' => $row->sku],
                        [
                            'wildflow_catalog_sku' => $row->wildflow_catalog_sku,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('wildflow_sku_aliases');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['wildflow_catalog_sku']);
            $table->dropColumn('wildflow_catalog_sku');
        });
    }
};
