<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('price_try', 'purchase_price');
            $table->string('purchase_currency')->default('TRY')->after('price_try'); // После старого имени
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('purchase_price', 'price_try');
            $table->dropColumn('purchase_currency');
        });
    }
};
