<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_accounts')) {
            Schema::create('wallet_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->string('l1_address', 80)->nullable()->index();
                $table->string('asset', 16);
                $table->unsignedBigInteger('available_minor')->default(0);
                $table->unsignedBigInteger('reserved_minor')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'asset'], 'wallet_accounts_user_asset_unique');
                $table->index(['asset', 'updated_at'], 'wallet_accounts_asset_updated_idx');
            });
        }

        if (! Schema::hasTable('wallet_ledger_entries')) {
            Schema::create('wallet_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->string('asset', 16);
                $table->string('direction', 16);
                $table->string('entry_type', 64);
                $table->unsignedBigInteger('amount_minor');
                $table->unsignedBigInteger('balance_after_minor');
                $table->string('idempotency_key')->unique();
                $table->string('tx_hash', 128)->nullable()->index();
                $table->unsignedBigInteger('nonce')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'asset', 'created_at'], 'wallet_ledger_user_asset_created_idx');
                $table->index(['entry_type', 'created_at'], 'wallet_ledger_entry_type_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_accounts');
    }
};
