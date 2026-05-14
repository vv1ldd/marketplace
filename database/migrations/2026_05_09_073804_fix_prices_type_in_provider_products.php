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
        Schema::table('provider_products', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'retail_price']);
        });
        
        Schema::table('provider_products', function (Blueprint $table) {
            $table->decimal('purchase_price', 16, 4)->default(0)->after('reward_type');
            $table->decimal('retail_price', 16, 4)->default(0)->after('purchase_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_products', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'retail_price']);
        });
        
        Schema::table('provider_products', function (Blueprint $table) {
            $table->bigInteger('purchase_price')->unsigned()->default(0)->after('reward_type');
            $table->bigInteger('retail_price')->unsigned()->default(0)->after('purchase_price');
        });
    }
};
