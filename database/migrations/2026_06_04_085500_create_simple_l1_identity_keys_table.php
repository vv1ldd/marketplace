<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('simple_l1_identity_keys')) {
            return;
        }

        Schema::create('simple_l1_identity_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_l1_address', 44)->index();
            $table->string('key_l1_address', 44)->unique();
            $table->string('key_type', 64)->default('native_macos_p256');
            $table->longText('public_key');
            $table->string('public_key_hash', 64)->index();
            $table->string('trust_level', 64)->default('device_user_presence');
            $table->string('device_name')->nullable();
            $table->string('enrolled_via', 64)->default('native_direct_bootstrap');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_l1_address', 'revoked_at'], 'sl1_identity_keys_entity_revoked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simple_l1_identity_keys');
    }
};
