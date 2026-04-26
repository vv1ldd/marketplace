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
        Schema::table('shops', function (Blueprint $table) {
            $table->string('ym_base_card')->nullable()->after('ym_diff_hours')->comment('Базовая подложка для карточек');
            $table->string('ym_logo')->nullable()->after('ym_base_card')->comment('Логотип магазина для карточек');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['ym_base_card', 'ym_logo']);
        });
    }
};
