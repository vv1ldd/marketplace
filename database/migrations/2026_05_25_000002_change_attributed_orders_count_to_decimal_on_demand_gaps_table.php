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
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->decimal('attributed_orders_count', 8, 2)->default(0.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->integer('attributed_orders_count')->default(0)->change();
        });
    }
};
