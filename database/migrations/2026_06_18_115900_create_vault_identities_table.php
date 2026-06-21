<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('anchor_address', 128)->unique();
            $table->string('vault_kind', 32)->default('personal');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'vault_kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_identities');
    }
};
