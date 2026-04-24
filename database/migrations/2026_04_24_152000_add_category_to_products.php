<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type');
        });

        // Базовое распределение для существующих товаров
        Product::where('name', 'like', '%Card%')
            ->orWhere('name', 'like', '%Карта%')
            ->orWhere('name', 'like', '%Пополнение%')
            ->orWhere('name', 'like', '%TL%')
            ->update(['category' => 'gift-card']);

        Product::whereNull('category')->update(['category' => 'game']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
