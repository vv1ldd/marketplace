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
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('supported_jurisdictions');
            $table->json('compliance_config')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('compliance_config');
            $table->json('supported_jurisdictions')->nullable()->after('name');
        });
    }
};
