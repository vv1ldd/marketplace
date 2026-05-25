<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_inventory', function (Blueprint $table) {
            $table->string('reservation_reference')->nullable()->unique()->after('order_item_id');
            $table->decimal('reserved_amount', 16, 2)->nullable()->after('reservation_reference');
            $table->string('reserve_currency', 10)->default('RUB')->after('reserved_amount');
            $table->timestamp('reserved_at')->nullable()->after('reserve_currency');
        });
    }

    public function down(): void
    {
        Schema::table('product_inventory', function (Blueprint $table) {
            $table->dropColumn([
                'reservation_reference',
                'reserved_amount',
                'reserve_currency',
                'reserved_at',
            ]);
        });
    }
};
