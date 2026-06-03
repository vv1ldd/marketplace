<?php

namespace App\Services\Continuity;

use App\Models\ProjectionRebuildRegistry;
use Illuminate\Support\Facades\Schema;

class ProjectionRebuildRegistryService
{
    /**
     * @return array<int, ProjectionRebuildRegistry>
     */
    public function ensureDefaults(): array
    {
        if (! Schema::hasTable('projection_rebuild_registry')) {
            return [];
        }

        return collect($this->defaults())
            ->map(fn (array $entry): ProjectionRebuildRegistry => ProjectionRebuildRegistry::updateOrCreate(
                ['projection_name' => $entry['projection_name']],
                $entry,
            ))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaults(): array
    {
        return [
            [
                'projection_name' => 'balances_projection',
                'classification' => 'class_b_rebuildable_projection',
                'source_transitions' => [
                    'sovereign_ledger:FINANCE_TOPUP',
                    'sovereign_ledger:FINANCE_DEPOSIT',
                    'sovereign_ledger:FINANCE_CREDIT_GRANTED',
                    'sovereign_ledger:FINANCE_HOLD',
                    'sovereign_ledger:FINANCE_CAPTURE',
                    'sovereign_ledger:FINANCE_RELEASE_HOLD',
                ],
                'source_authority_decisions' => ['balance_authority_decisions'],
                'required_anchor_range' => 'balance_anchor_range',
                'rebuild_command' => 'marketplace:rebuild-balances',
                'verify_command' => 'marketplace:verify-balances',
            ],
            [
                'projection_name' => 'buyer_wallet_projection',
                'classification' => 'class_b_rebuildable_projection',
                'source_transitions' => [
                    'wallet_ledger_entries',
                    'sovereign_ledger:BUYER_WALLET_TOPUP',
                    'sovereign_ledger:BUYER_WALLET_DEBIT',
                    'sovereign_ledger:BUYER_WALLET_RESERVE',
                    'sovereign_ledger:BUYER_WALLET_RESERVED_DEBIT',
                    'sovereign_ledger:BUYER_WALLET_RESERVATION_RELEASE',
                ],
                'source_authority_decisions' => ['wallet_authority_decisions'],
                'required_anchor_range' => 'wallet_anchor_range',
                'rebuild_command' => 'marketplace:rebuild-buyer-wallets',
                'verify_command' => 'marketplace:verify-buyer-wallets',
            ],
            [
                'projection_name' => 'marketplace_orders_projection',
                'classification' => 'class_b_rebuildable_projection',
                'source_transitions' => [
                    'orders',
                    'order_items',
                    'currencies',
                    'order_item_purchase_status_transitions',
                ],
                'source_authority_decisions' => ['order_authority_decisions'],
                'required_anchor_range' => 'order_anchor_range',
                'rebuild_command' => 'marketplace:rebuild-orders',
                'verify_command' => 'marketplace:verify-orders',
            ],
            [
                'projection_name' => 'canonical_product_identity_projection',
                'classification' => 'class_b_rebuildable_projection',
                'source_transitions' => [
                    'provider_products',
                    'products',
                    'canonical_product_identity_overrides',
                ],
                'source_authority_decisions' => ['catalog_authority_decisions'],
                'required_anchor_range' => 'catalog_identity_anchor_range',
                'rebuild_command' => 'catalog:rebuild-identities',
                'verify_command' => 'catalog:verify-identities',
            ],
            [
                'projection_name' => 'canonical_product_search_profile_projection',
                'classification' => 'class_b_rebuildable_projection',
                'source_transitions' => [
                    'canonical_product_identities',
                    'canonical_product_identity_sources',
                    'canonical_product_identity_overrides',
                ],
                'source_authority_decisions' => ['catalog_authority_decisions'],
                'required_anchor_range' => 'catalog_search_profile_anchor_range',
                'rebuild_command' => 'search-profile:rebuild',
                'verify_command' => 'search-profile:verify',
            ],
            [
                'projection_name' => 'catalog_search_projection',
                'classification' => 'class_b_aggregate_projection',
                'source_transitions' => [
                    'canonical_product_identity_projection',
                    'canonical_product_search_profile_projection',
                ],
                'source_authority_decisions' => ['catalog_authority_decisions'],
                'required_anchor_range' => 'catalog_anchor_range',
                'rebuild_command' => 'marketplace:rebuild-catalog-search',
                'verify_command' => 'marketplace:verify-catalog-search',
            ],
        ];
    }

    public function markVerified(
        string $projectionName,
        string $verificationResult,
        ?string $sourceRevision = null,
        ?string $anchorRange = null,
        ?array $metadata = null,
    ): ?ProjectionRebuildRegistry {
        if (! Schema::hasTable('projection_rebuild_registry')) {
            return null;
        }

        $projection = ProjectionRebuildRegistry::query()
            ->where('projection_name', $projectionName)
            ->first();

        if (! $projection) {
            $this->ensureDefaults();
            $projection = ProjectionRebuildRegistry::query()
                ->where('projection_name', $projectionName)
                ->first();
        }

        if (! $projection) {
            return null;
        }

        $projection->forceFill([
            'last_verified_at' => now(),
            'verification_result' => $verificationResult,
            'source_revision' => $sourceRevision,
            'anchor_range' => $anchorRange,
            'metadata' => $metadata ?? $projection->metadata,
        ])->save();

        return $projection;
    }

    public function markRebuilt(
        string $projectionName,
        ?string $sourceRevision = null,
        ?string $anchorRange = null,
        ?array $metadata = null,
    ): ?ProjectionRebuildRegistry {
        if (! Schema::hasTable('projection_rebuild_registry')) {
            return null;
        }

        $projection = ProjectionRebuildRegistry::query()
            ->where('projection_name', $projectionName)
            ->first();

        if (! $projection) {
            $this->ensureDefaults();
            $projection = ProjectionRebuildRegistry::query()
                ->where('projection_name', $projectionName)
                ->first();
        }

        if (! $projection) {
            return null;
        }

        $projection->forceFill([
            'last_rebuilt_at' => now(),
            'source_revision' => $sourceRevision,
            'anchor_range' => $anchorRange,
            'metadata' => $metadata ?? $projection->metadata,
        ])->save();

        return $projection;
    }
}
