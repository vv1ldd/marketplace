<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('market_category_id')->nullable()->after('category');
            $table->boolean('downloadable')->default(false)->after('market_category_id');
            $table->boolean('adult')->default(false)->after('downloadable');
            $table->json('age')->nullable()->after('adult');
            $table->json('shelf_life')->nullable()->after('age');
            $table->json('life_time')->nullable()->after('shelf_life');
            $table->json('guarantee_period')->nullable()->after('life_time');
            $table->json('manufacturer_countries')->nullable()->after('vendor');
            $table->json('params')->nullable()->after('manufacturer_countries');
            $table->json('videos')->nullable()->after('pictures');
            $table->string('group_id')->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'market_category_id',
                'downloadable',
                'adult',
                'age',
                'shelf_life',
                'life_time',
                'guarantee_period',
                'manufacturer_countries',
                'params',
                'videos',
                'group_id',
            ]);
        });
    }
};
