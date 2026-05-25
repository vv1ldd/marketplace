<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zero_layer_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source')->index();
            $table->string('status')->default('active')->index();
            $table->json('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('zero_layer_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zero_layer_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->index();
            $table->string('source_key', 64);
            $table->string('signal_type')->index();
            $table->date('signal_date')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->text('query_text')->nullable();
            $table->text('page_url')->nullable();
            $table->text('displayed_url')->nullable();
            $table->text('title')->nullable();
            $table->text('snippet')->nullable();
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaign')->nullable();
            $table->string('ad_group_id')->nullable()->index();
            $table->string('ad_group')->nullable();
            $table->string('ad_id')->nullable()->index();
            $table->string('ad')->nullable();
            $table->decimal('position', 10, 4)->nullable();
            $table->decimal('impressions', 18, 4)->nullable();
            $table->decimal('clicks', 18, 4)->nullable();
            $table->decimal('link_clicks', 18, 4)->nullable();
            $table->decimal('cost', 18, 4)->nullable();
            $table->decimal('conversions', 18, 4)->nullable();
            $table->decimal('revenue', 18, 4)->nullable();
            $table->decimal('roas', 18, 4)->nullable();
            $table->decimal('video_views', 18, 4)->nullable();
            $table->decimal('video_watched_6s', 18, 4)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zero_layer_signals');
        Schema::dropIfExists('zero_layer_integrations');
    }
};
