<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_search_query_signals', function (Blueprint $table) {
            $table->id();
            $table->string('signal_hash', 64)->unique();
            $table->text('query');
            $table->string('normalized_query', 512)->index();
            $table->string('source')->index();
            $table->string('country', 16)->nullable()->index();
            $table->string('locale', 16)->nullable()->index();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->nullable();
            $table->unsignedBigInteger('volume')->nullable();
            $table->text('landing_url')->nullable();
            $table->timestamp('observed_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_search_query_signals');
    }
};
