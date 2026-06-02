<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mutation_guard_entries')) {
            return;
        }

        Schema::create('mutation_guard_entries', function (Blueprint $table) {
            $table->id();
            $table->string('guard_key')->unique();
            $table->string('mutation_id', 128)->index();
            $table->string('mutation_path')->index();
            $table->string('actor')->nullable()->index();
            $table->string('action')->nullable()->index();
            $table->string('entity_type')->nullable()->index();
            $table->string('entity_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('context_fingerprint', 64)->nullable()->index();
            $table->string('mode', 24)->default('shadow')->index();
            $table->string('decision', 48)->default('allowed')->index();
            $table->string('status', 32)->default('started')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('first_seen_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['mutation_path', 'decision', 'created_at'], 'mutation_guard_path_decision_idx');
            $table->index(['entity_type', 'entity_id', 'mutation_path'], 'mutation_guard_entity_path_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutation_guard_entries');
    }
};
