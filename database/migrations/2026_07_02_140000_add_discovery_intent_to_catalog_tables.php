<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'provider_products', 'wildflow_catalogs', 'canonical_product_identities'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'discovery_intent')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $column = $table->string('discovery_intent', 50)->nullable()->index();

                if (Schema::hasColumn($tableName, 'canonical_category')) {
                    $column->after('canonical_category');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'provider_products', 'wildflow_catalogs', 'canonical_product_identities'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'discovery_intent')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('discovery_intent');
            });
        }
    }
};
