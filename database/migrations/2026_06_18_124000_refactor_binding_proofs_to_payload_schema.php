<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('binding_proofs') || ! Schema::hasColumn('binding_proofs', 'transaction_hash')) {
            return;
        }

        Schema::table('binding_proofs', function (Blueprint $table) {
            $table->string('proof_reference', 128)->nullable()->after('binding_key');
            $table->string('verification_state', 16)->default('verified')->after('proof_reference');
            $table->json('proof_payload')->nullable()->after('verification_state');
        });

        DB::table('binding_proofs')->orderBy('id')->lazy()->each(function (object $row): void {
            $transactionHash = strtolower((string) $row->transaction_hash);
            $proofType = (string) $row->proof_type;

            DB::table('binding_proofs')->where('id', $row->id)->update([
                'proof_reference' => $proofType.':'.$transactionHash,
                'verification_state' => 'verified',
                'proof_payload' => json_encode([
                    'chain_id' => (int) $row->chain_id,
                    'token_contract' => strtolower((string) $row->token_contract),
                    'transaction_hash' => $transactionHash,
                    'sender' => strtolower((string) $row->sender),
                    'recipient' => strtolower((string) $row->recipient),
                    'amount' => (string) $row->amount,
                    'block_number' => (int) $row->block_number,
                ], JSON_THROW_ON_ERROR),
            ]);
        });

        Schema::table('binding_proofs', function (Blueprint $table) {
            $table->dropUnique('binding_proofs_vault_tx_unique');
            $table->dropIndex(['binding_key', 'transaction_hash']);
            $table->dropColumn([
                'chain_id',
                'token_contract',
                'transaction_hash',
                'sender',
                'recipient',
                'amount',
                'block_number',
            ]);
            $table->unique(['vault_id', 'proof_reference'], 'binding_proofs_vault_reference_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('binding_proofs') || ! Schema::hasColumn('binding_proofs', 'proof_payload')) {
            return;
        }

        Schema::table('binding_proofs', function (Blueprint $table) {
            $table->unsignedInteger('chain_id')->nullable();
            $table->string('token_contract', 66)->nullable();
            $table->string('transaction_hash', 66)->nullable();
            $table->string('sender', 66)->nullable();
            $table->string('recipient', 66)->nullable();
            $table->string('amount', 78)->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
        });

        DB::table('binding_proofs')->orderBy('id')->lazy()->each(function (object $row): void {
            $payload = json_decode((string) $row->proof_payload, true);
            if (! is_array($payload)) {
                return;
            }

            DB::table('binding_proofs')->where('id', $row->id)->update([
                'chain_id' => (int) ($payload['chain_id'] ?? 0),
                'token_contract' => (string) ($payload['token_contract'] ?? ''),
                'transaction_hash' => (string) ($payload['transaction_hash'] ?? ''),
                'sender' => (string) ($payload['sender'] ?? ''),
                'recipient' => (string) ($payload['recipient'] ?? ''),
                'amount' => (string) ($payload['amount'] ?? '0'),
                'block_number' => (int) ($payload['block_number'] ?? 0),
            ]);
        });

        Schema::table('binding_proofs', function (Blueprint $table) {
            $table->dropUnique('binding_proofs_vault_reference_unique');
            $table->dropColumn(['proof_reference', 'verification_state', 'proof_payload']);
            $table->unique(['vault_id', 'transaction_hash'], 'binding_proofs_vault_tx_unique');
            $table->index(['binding_key', 'transaction_hash']);
        });
    }
};
