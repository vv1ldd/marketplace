<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_bindings', function (Blueprint $table) {
            if (! Schema::hasColumn('identity_bindings', 'binding_source')) {
                $table->string('binding_source', 32)
                    ->default('external')
                    ->after('binding_key');
                $table->index(['vault_id', 'binding_key', 'binding_source'], 'identity_bindings_vault_key_source_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('identity_bindings', function (Blueprint $table) {
            if (Schema::hasColumn('identity_bindings', 'binding_source')) {
                $table->dropIndex('identity_bindings_vault_key_source_idx');
                $table->dropColumn('binding_source');
            }
        });
    }
};
