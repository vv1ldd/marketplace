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
        Schema::create('provider_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->string('sku')->index();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('data')->nullable();
            $table->timestamps();

            // Add unique index for provider_id and sku
            $table->unique(['provider_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_products');
    }
};
