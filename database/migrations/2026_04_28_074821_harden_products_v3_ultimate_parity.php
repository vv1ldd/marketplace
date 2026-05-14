<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('market_category_id');
            $table->unsignedBigInteger('purchase_price_rub')->nullable()->after('price_rub');
            $table->unsignedBigInteger('additional_expenses_rub')->nullable()->after('purchase_price_rub');
            $table->string('price_competitiveness')->nullable()->after('additional_expenses_rub');

            $table->decimal('weight_kg', 10, 3)->nullable()->after('weight');
            $table->decimal('length_cm', 10, 2)->nullable()->after('dimensions');
            $table->decimal('width_cm', 10, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 10, 2)->nullable()->after('width_cm');

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'category_id',
                'purchase_price_rub',
                'additional_expenses_rub',
                'price_competitiveness',
                'weight_kg',
                'length_cm',
                'width_cm',
                'height_cm',
            ]);
        });
    }
};
