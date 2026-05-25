<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meanly_operational_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('alert_key')->unique();
            $table->string('type', 80)->index();
            $table->string('severity', 24)->index();
            $table->string('surface', 64)->nullable()->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->unsignedInteger('threshold')->nullable();
            $table->unsignedBigInteger('last_analytics_event_id')->nullable()->index();
            $table->unsignedBigInteger('last_sovereign_ledger_id')->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('first_seen_at')->index();
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'severity', 'last_seen_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meanly_operational_alerts');
    }
};
