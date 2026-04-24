<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->integer('ps_tax')->default(35)->after('voucher_prefix');
            $table->integer('ps_tax_for_sites')->default(35)->after('ps_tax');
        });
    }

    public function down()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['ps_tax', 'ps_tax_for_sites']);
        });
    }
};
