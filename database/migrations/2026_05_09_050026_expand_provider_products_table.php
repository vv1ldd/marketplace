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
            if (Schema::hasColumn('provider_products', 'price')) {
                $table->renameColumn('price', 'purchase_price');
            }
            
            if (!Schema::hasColumn('provider_products', 'brand_id')) {
                $table->foreignId('brand_id')->after('provider_id')->nullable()->constrained('brands')->nullOnDelete();
            }

            if (Schema::hasColumn('provider_products', 'region_id')) {
                $table->dropColumn('region_id');
            }
            $after = Schema::hasColumn('provider_products', 'brand_id') ? 'brand_id' : 'provider_id';
            // mapping_countries.id may be legacy INT on existing production databases.
            $table->unsignedBigInteger('region_id')->after($after)->nullable()->index();

            if (!Schema::hasColumn('provider_products', 'category')) {
                $table->string('category')->after('name')->nullable();
            }
            if (!Schema::hasColumn('provider_products', 'reward_type')) {
                $table->string('reward_type')->after('category')->nullable();
            }
            if (!Schema::hasColumn('provider_products', 'retail_price')) {
                $table->decimal('retail_price', 15, 2)->after('purchase_price')->nullable();
            }
            if (!Schema::hasColumn('provider_products', 'image')) {
                $table->string('image')->after('retail_price')->nullable();
            }
            if (!Schema::hasColumn('provider_products', 'activation_url')) {
                $table->text('activation_url')->nullable();
            }
            if (!Schema::hasColumn('provider_products', 'redemption_instructions')) {
                $table->text('redemption_instructions')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_products', function (Blueprint $table) {
            if (Schema::hasColumn('provider_products', 'purchase_price')) {
                $table->renameColumn('purchase_price', 'price');
            }
            if (Schema::hasColumn('provider_products', 'brand_id')) {
                $table->dropForeign(['brand_id']);
                $table->dropColumn('brand_id');
            }
            if (Schema::hasColumn('provider_products', 'region_id')) {
                $table->dropColumn('region_id');
            }
            $table->dropColumn(['category', 'reward_type', 'retail_price', 'image', 'activation_url', 'redemption_instructions']);
        });
    }
};
