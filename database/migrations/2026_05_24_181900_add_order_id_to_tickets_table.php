<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'order_id')) {
                $table->unsignedBigInteger('order_id')
                    ->nullable()
                    ->after('shop_id');

                $table->unique('order_id', 'tickets_order_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'order_id')) {
                $table->dropUnique('tickets_order_id_unique');
                $table->dropColumn('order_id');
            }
        });
    }
};
