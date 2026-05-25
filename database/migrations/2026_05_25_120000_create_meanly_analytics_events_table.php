<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meanly_analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 64)->index();
            $table->string('event_name', 160)->index();
            $table->string('surface', 64)->nullable()->index();
            $table->string('severity', 24)->default('info')->index();
            $table->string('request_id', 64)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('session_hash', 64)->nullable()->index();
            $table->string('visitor_hash', 64)->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('route_name')->nullable()->index();
            $table->string('route_action')->nullable();
            $table->string('method', 12)->nullable();
            $table->string('path', 1024)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable()->index();
            $table->boolean('is_slow')->default(false)->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->unsignedBigInteger('legal_entity_id')->nullable()->index();
            $table->string('provider_type', 64)->nullable()->index();
            $table->string('category', 128)->nullable()->index();
            $table->string('currency', 12)->nullable()->index();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_fingerprint', 64)->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['surface', 'event_name', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
            $table->index(['is_slow', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meanly_analytics_events');
    }
};
