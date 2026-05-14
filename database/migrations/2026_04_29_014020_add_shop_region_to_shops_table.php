<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            $blueprint->string('shop_region')->nullable()->default('RU')->after('allowed_regions');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            $blueprint->dropColumn('shop_region');
        });
    }
};
