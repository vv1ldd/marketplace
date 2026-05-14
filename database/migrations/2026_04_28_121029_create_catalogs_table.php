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
        Schema::create('catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->enum('type', ['global', 'shop'])->default('shop')->index();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->timestamps();

            // Uniqueness constraints depending on business logic could be added here,
            // e.g., one 'shop' catalog per shop_id.
            $table->unique(['shop_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs');
    }
};
