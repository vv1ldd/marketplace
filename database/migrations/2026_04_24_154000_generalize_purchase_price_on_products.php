<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'price_try') && ! Schema::hasColumn('products', 'purchase_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('price_try', 'purchase_price');
            });
        }

        if (Schema::hasColumn('products', 'purchase_currency')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $after = Schema::hasColumn('products', 'purchase_price') ? 'purchase_price' : 'price_rub';

            $table->string('purchase_currency')->default('TRY')->after($after);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'purchase_price') && ! Schema::hasColumn('products', 'price_try')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('purchase_price', 'price_try');
            });
        }

        if (! Schema::hasColumn('products', 'purchase_currency')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('purchase_currency');
        });
    }
};
