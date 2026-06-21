<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('vault_id')->constrained('vault_identities')->cascadeOnDelete();
            $table->string('identity_id', 128);
            $table->string('adapter_key', 64);
            $table->string('event_type', 64);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['vault_id', 'occurred_at']);
            $table->index(['identity_id', 'occurred_at']);
            $table->index(['adapter_key', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_audit_events');
    }
};
