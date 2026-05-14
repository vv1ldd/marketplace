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
            $table->text('import_status')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            // Разрешаем NULL для старых данных, чтобы миграция не падала
            $table->string('slug', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('import_status', 255)->nullable()->change();
        });
    }
};
