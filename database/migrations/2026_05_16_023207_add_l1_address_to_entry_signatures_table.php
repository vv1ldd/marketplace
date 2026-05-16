<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entry_signatures', function (Blueprint $table) {
            $table->string('l1_address')->after('passkey_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('entry_signatures', function (Blueprint $table) {
            $table->dropColumn('l1_address');
        });
    }
};
