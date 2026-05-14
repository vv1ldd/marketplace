<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            $blueprint->boolean('is_voucher_generation_enabled')->default(true)->after('voucher_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            $blueprint->dropColumn('is_voucher_generation_enabled');
        });
    }
};
