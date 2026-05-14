<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Change columns to TEXT for encryption
        DB::statement('ALTER TABLE providers MODIFY credentials TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE providers MODIFY settings TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE wildflow_catalogs MODIFY data TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE provider_products MODIFY data TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE products MODIFY data TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE products MODIFY params TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE products MODIFY ym_errors TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE users MODIFY meta TEXT DEFAULT NULL');
        DB::statement('ALTER TABLE customers MODIFY meta TEXT DEFAULT NULL');

        $vault = app(\App\Services\VaultTransitService::class);

        // Migrate Providers
        DB::table('providers')->chunkById(100, function ($providers) use ($vault) {
            foreach ($providers as $p) {
                $updates = [];
                foreach (['credentials', 'settings'] as $field) {
                    if ($p->$field && !str_starts_with($p->$field, 'vault:')) {
                        $updates[$field] = $vault->encrypt($p->$field);
                    }
                }
                if (!empty($updates)) {
                    DB::table('providers')->where('id', $p->id)->update($updates);
                }
            }
        });

        // Migrate Users & Customers
        foreach (['users', 'customers'] as $table) {
            DB::table($table)->chunkById(500, function ($rows) use ($vault, $table) {
                foreach ($rows as $row) {
                    if ($row->meta && !str_starts_with($row->meta, 'vault:')) {
                        DB::table($table)->where('id', $row->id)->update([
                            'meta' => $vault->encrypt($row->meta)
                        ]);
                    }
                }
            });
        }

        // Note: Catalog and Product data are migrated lazily on save 
        // to avoid heavy DB locks, but existing sensitive blobs should be encrypted.
    }

    public function down(): void
    {
    }
};
