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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('game'); // game, voucher, service
            $table->integer('price_rub')->nullable();
            $table->integer('price_try')->nullable();
            $table->integer('base_price')->nullable();
            $table->foreignId('type_form_id')->nullable();
            $table->json('data')->nullable(); // For parser-specific data
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('send_to_ym_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
