<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('legal_entity_migration_pills');
    }

    public function down(): void
    {
        Schema::create('legal_entity_migration_pills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_hash')->unique();
            $table->string('target_domain');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by_passkey_id')->nullable();
            $table->ipAddress('used_ip')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
};
