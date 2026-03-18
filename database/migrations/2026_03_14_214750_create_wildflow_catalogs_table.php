<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wildflow_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('service_sku', 16)->unique();
            $table->string('sku')->nullable()->unique();
            $table->json('data');
            $table->string('type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wildflow_catalogs');
    }
};
