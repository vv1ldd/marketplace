<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('name');
        });

        Schema::table('api_applications', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('api_applications', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }
};
