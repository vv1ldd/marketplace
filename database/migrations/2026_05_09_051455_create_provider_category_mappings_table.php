<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_category_mappings', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $blueprint->string('provider_category_name'); // Оригинальная строка от провайдера
            $blueprint->foreignId('catalog_group_id')->constrained('catalog_groups')->cascadeOnDelete();
            $blueprint->timestamps();

            $blueprint->unique(['provider_id', 'provider_category_name'], 'provider_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_category_mappings');
    }
};
