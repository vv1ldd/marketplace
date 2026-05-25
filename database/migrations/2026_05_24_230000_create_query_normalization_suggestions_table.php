<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_normalization_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // raw text candidate
            $table->string('target'); // suggested canonical phrase
            $table->decimal('confidence', 5, 2)->default(0.50);
            $table->string('reason'); // similar clicked products, distance match, etc.
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_normalization_suggestions');
    }
};
