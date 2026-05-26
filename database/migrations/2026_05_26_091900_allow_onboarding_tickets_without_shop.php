<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->foreignId('shop_id')->nullable()->change();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->foreignId('shop_id')->nullable(false)->change();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }
};
