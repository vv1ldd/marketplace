<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('redeem_requires_extended_profile')
                ->default(false)
                ->after('shop_region')
                ->comment('Redeem: ФИО + телефон RU. false = global (email + код)');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('redeem_requires_extended_profile');
        });
    }
};
