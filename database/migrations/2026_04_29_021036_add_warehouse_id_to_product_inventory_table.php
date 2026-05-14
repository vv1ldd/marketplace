<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_inventory', function (Blueprint $blueprint) {
            $blueprint->foreignId('warehouse_id')->nullable()->after('shop_id')->constrained()->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('product_inventory', function (Blueprint $blueprint) {
            $blueprint->dropConstrainedForeignId('warehouse_id');
        });
    }
};
