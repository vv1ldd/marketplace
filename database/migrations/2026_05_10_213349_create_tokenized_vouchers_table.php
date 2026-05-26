<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tokenized_vouchers')) {
            Schema::create('tokenized_vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                
                // The actual encrypted voucher key stored in HashiCorp Vault
                $table->text('encrypted_key');
                $table->string('key_bidx')->index(); // Blind index for searching
                
                // Web3 Asset Data
                $table->string('owner_wallet')->index(); // EVM Address (0x...)
                $table->string('token_id')->nullable()->index(); // ERC-1155 or ERC-721 Token ID
                $table->string('tx_hash')->nullable(); // Minting or Burn transaction hash
                $table->string('network')->default('polygon'); // e.g., polygon, ethereum, bsc
                
                // Asset Value
                $table->decimal('nominal_usd', 10, 2);
                
                // State Machine
                $table->enum('status', ['locked', 'minted', 'burned', 'redeemed'])->default('locked');
                
                $table->timestamps();
            });
        }
        
        // Add wallet address to shops table
        if (! Schema::hasColumn('shops', 'web3_wallet')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('web3_wallet')->nullable()->after('is_sandbox');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('web3_wallet');
        });
        Schema::dropIfExists('tokenized_vouchers');
    }
};
