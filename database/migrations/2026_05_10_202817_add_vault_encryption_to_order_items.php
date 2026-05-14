<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $indexes = Schema::getIndexes('order_items');
            $indexNames = collect($indexes)->pluck('name');

            // Drop existing index on 'key' if any
            if ($indexNames->contains('order_items_key_index')) {
                $table->dropIndex('order_items_key_index');
            }
            if ($indexNames->contains('order_items_key_unique')) {
                $table->dropUnique('order_items_key_unique');
            }

            // Expand to TEXT for encrypted Vault payload
            $table->text('key')->nullable()->change();
            $table->text('original_code')->nullable()->change();

            // Blind index on 'key' for secure lookup without decryption
            $table->string('key_bidx', 64)->nullable()->unique()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('key_bidx');
            $table->string('key')->nullable()->change();
            $table->string('original_code')->nullable()->change();
        });
    }
};
