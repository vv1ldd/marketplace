<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->expandSensitiveColumnsForVault();

        if (! Schema::hasTable('marketplace_transition_outbox')) {
            Schema::create('marketplace_transition_outbox', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_uuid')->unique();
                $table->string('scope')->index();
                $table->string('aggregate_type')->nullable()->index();
                $table->string('aggregate_id')->nullable()->index();
                $table->string('transition_type')->index();
                $table->string('transition_id')->nullable()->index();
                $table->string('transition_hash', 64)->index();
                $table->unsignedBigInteger('authority_decision_id')->nullable()->index();
                $table->string('authority_decision_hash', 64)->nullable()->index();
                $table->string('idempotency_key')->nullable();
                $table->longText('payload')->nullable();
                $table->string('payload_hash', 64)->nullable()->index();
                $table->string('anchor_status', 32)->default('pending')->index();
                $table->string('anchor_hash', 128)->nullable()->index();
                $table->string('status', 32)->default('pending')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('available_at')->nullable()->index();
                $table->timestamp('processed_at')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(['scope', 'idempotency_key'], 'transition_outbox_scope_idempotency_unique');
                $table->index(['scope', 'status', 'created_at'], 'transition_outbox_scope_status_created_idx');
            });
        }

        if (! Schema::hasTable('writer_authority_readiness')) {
            Schema::create('writer_authority_readiness', function (Blueprint $table) {
                $table->id();
                $table->string('scope')->unique();
                $table->string('authority_holder')->nullable()->index();
                $table->string('authority_epoch')->nullable()->index();
                $table->string('fencing_status', 32)->default('unknown')->index();
                $table->string('conflict_status', 32)->default('no_holder')->index();
                $table->timestamp('last_heartbeat_at')->nullable()->index();
                $table->string('last_transition_id')->nullable();
                $table->string('last_transition_hash', 64)->nullable();
                $table->string('last_anchor_hash', 128)->nullable();
                $table->longText('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('projection_rebuild_registry')) {
            Schema::create('projection_rebuild_registry', function (Blueprint $table) {
                $table->id();
                $table->string('projection_name')->unique();
                $table->string('classification', 64)->default('class_b_rebuildable_projection')->index();
                $table->longText('source_transitions')->nullable();
                $table->longText('source_authority_decisions')->nullable();
                $table->string('required_anchor_range')->nullable();
                $table->string('rebuild_command')->nullable();
                $table->string('verify_command')->nullable();
                $table->timestamp('last_rebuilt_at')->nullable()->index();
                $table->timestamp('last_verified_at')->nullable()->index();
                $table->string('verification_result', 32)->default('unknown')->index();
                $table->string('source_revision')->nullable();
                $table->string('anchor_range')->nullable();
                $table->longText('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_rebuild_registry');
        Schema::dropIfExists('writer_authority_readiness');
        Schema::dropIfExists('marketplace_transition_outbox');
    }

    private function expandSensitiveColumnsForVault(): void
    {
        $driver = DB::getDriverName();
        $tables = [
            'sovereign_ledger' => ['payload', 'input_data', 'output_state'],
            'wallet_ledger_entries' => ['payload'],
            'wildflow_kernel_orders' => ['request_payload', 'response_payload'],
            'zero_layer_integrations' => ['credentials', 'settings'],
            'legal_entities' => [
                'meanly_api_token',
                'wildflow_api_token',
                'meanly_ip_whitelist',
                'wildflow_ip_whitelist',
                'agreement_metadata',
                'vendor_credentials',
            ],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($driver === 'sqlite') {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                    foreach ($columns as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $blueprint->longText($column)->nullable()->change();
                        }
                    }
                });

                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::statement("ALTER TABLE {$table} MODIFY {$column} LONGTEXT DEFAULT NULL");
                }
            }
        }
    }
};
