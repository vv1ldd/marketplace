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
        Schema::create('provider_brand_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->string('external_name')->index(); // Exact string from JSON categories
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('set null'); // Our Master Brand
            $table->timestamps();

            $table->unique(['provider_id', 'external_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_brand_mappings');
    }
};
