<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_favorites', function (Blueprint $table) {
            $table->id();
            $table->string('entity_l1_address')->index();
            $table->string('product_slug')->index();
            $table->string('product_name');
            $table->string('category_slug')->nullable()->index();
            $table->string('category_label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['entity_l1_address', 'product_slug'], 'storefront_favorites_identity_product_unique');
            $table->index(['entity_l1_address', 'category_slug', 'updated_at'], 'storefront_favorites_identity_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_favorites');
    }
};
