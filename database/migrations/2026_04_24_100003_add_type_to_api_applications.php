<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('api_applications', function (Blueprint $table) {
            $table->string('type')->default('shop')->after('shop_id');
        });
    }

    public function down()
    {
        Schema::table('api_applications', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
