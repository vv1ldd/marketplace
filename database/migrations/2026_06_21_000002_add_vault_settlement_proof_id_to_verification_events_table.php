<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('verification_events')) {
            return;
        }

        Schema::table('verification_events', function (Blueprint $table) {
            if (! Schema::hasColumn('verification_events', 'vault_settlement_proof_id')) {
                $table->foreignId('vault_settlement_proof_id')
                    ->nullable()
                    ->after('binding_proof_id')
                    ->constrained('vault_settlement_proofs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('verification_events')) {
            return;
        }

        Schema::table('verification_events', function (Blueprint $table) {
            if (Schema::hasColumn('verification_events', 'vault_settlement_proof_id')) {
                $table->dropConstrainedForeignId('vault_settlement_proof_id');
            }
        });
    }
};
