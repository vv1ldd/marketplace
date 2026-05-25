<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'is_test')) {
            return;
        }

        Schema::table('orders', function (Blueprint $column) {
            $after = Schema::hasColumn('orders', 'shop_id') ? 'shop_id' : 'id';

            $column->boolean('is_test')->default(false)->after($after);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'is_test')) {
            return;
        }

        Schema::table('orders', function (Blueprint $column) {
            $column->dropColumn('is_test');
        });
    }
};
