<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sovereign_ledger', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable(false)->change();
        });
    }
};
