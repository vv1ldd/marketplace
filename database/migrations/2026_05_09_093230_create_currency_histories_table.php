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
        if (Schema::hasTable('currency_histories')) {
            return;
        }

        Schema::create('currency_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('official_rate', 16, 4)->nullable();
            $table->decimal('p2p_bybit', 16, 4)->nullable();
            $table->decimal('spread_percent', 8, 2)->nullable();
            $table->date('record_date');
            $table->timestamps();

            // Мы хотим хранить только один слепок в день для каждой валюты, чтобы не раздувать базу
            $table->unique(['currency_id', 'record_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_histories');
    }
};
