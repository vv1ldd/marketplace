<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_normalization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('match_type'); // transliteration, alias, abbreviation, slang, synonym
            $table->string('source')->unique(); // raw text to match
            $table->string('target'); // canonical translation
            $table->integer('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_normalization_rules');
    }
};
