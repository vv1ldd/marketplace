<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entity_migration_pills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('target_domain')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by_passkey_id')->nullable();
            $table->string('issued_ip')->nullable();
            $table->string('used_ip')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['legal_entity_id', 'used_at', 'expires_at'], 'le_migration_pills_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entity_migration_pills');
    }
};
