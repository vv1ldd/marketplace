<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            $blueprint->string('woo_api_url')->nullable()->after('domain');
            $blueprint->string('woo_consumer_key')->nullable()->after('woo_api_url');
            $blueprint->string('woo_consumer_secret')->nullable()->after('woo_consumer_key');
            $blueprint->string('woo_connection')->nullable()->comment('Legacy DB connection name')->after('woo_consumer_secret');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['woo_api_url', 'woo_consumer_key', 'woo_consumer_secret', 'woo_connection']);
        });
    }
};
