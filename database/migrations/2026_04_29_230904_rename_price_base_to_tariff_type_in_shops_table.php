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
            $table->renameColumn('price_base', 'tariff_type');
        });

        // Migrate existing values
        DB::table('shops')->where('tariff_type', 'purchase')->update(['tariff_type' => 'privileged']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->renameColumn('tariff_type', 'price_base');
        });

        // Reverse values
        DB::table('shops')->where('price_base', 'privileged')->update(['price_base' => 'purchase']);
    }
};
