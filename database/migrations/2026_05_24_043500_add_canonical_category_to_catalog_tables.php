<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'provider_products', 'wildflow_catalogs'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'canonical_category')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $after = match ($tableName) {
                    'products', 'provider_products' => 'category',
                    default => 'type',
                };

                $column = $table->string('canonical_category', 64)->nullable()->index();
                if (Schema::hasColumn($tableName, $after)) {
                    $column->after($after);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'provider_products', 'wildflow_catalogs'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'canonical_category')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('canonical_category');
            });
        }
    }
};
