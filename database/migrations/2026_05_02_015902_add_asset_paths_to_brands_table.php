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
            $table->string('logo_source')->nullable()->after('logo');
            $table->string('logo_svg')->nullable()->after('logo_source');
            $table->string('logo_enhanced')->nullable()->after('logo_svg');
            $table->string('logo_png')->nullable()->after('logo_enhanced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['logo_source', 'logo_svg', 'logo_enhanced', 'logo_png']);
        });
    }
};
