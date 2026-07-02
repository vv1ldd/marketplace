<?php

namespace App\DTO\Architecture;

use App\Models\Architecture\OfferSnapshot;

readonly class OfferSnapshotData
{
    public function __construct(
        public string $id,
        public string $snapshotUuid,
        public int $canonicalProductIdentityId,
        public string $entitlementFingerprint,
        public int $shopId,
        public int $productId,
        public string $sku,
        public int $providerId,
        public int $providerProductId,
        public string $providerSku,
        public string $offerKind,
        public int $buyerPriceCents,
        public string $buyerCurrency,
        public int $purchasePriceCents,
        public int $storagePriceCents,
        public string $fulfillmentMode,
        public ?int $stockCount,
        public ?string $rankingScore,
        public array $fullPayloadJson,
    ) {}

    public static function fromModel(OfferSnapshot $snapshot): self
    {
        return new self(
            id: (string) $snapshot->id,
            snapshotUuid: (string) $snapshot->snapshot_uuid,
            canonicalProductIdentityId: (int) $snapshot->canonical_product_identity_id,
            entitlementFingerprint: (string) $snapshot->entitlement_fingerprint,
            shopId: (int) $snapshot->shop_id,
            productId: (int) $snapshot->product_id,
            sku: (string) $snapshot->sku,
            providerId: (int) $snapshot->provider_id,
            providerProductId: (int) $snapshot->provider_product_id,
            providerSku: (string) $snapshot->provider_sku,
            offerKind: (string) $snapshot->offer_kind,
            buyerPriceCents: (int) $snapshot->buyer_price_cents,
            buyerCurrency: (string) $snapshot->buyer_currency,
            purchasePriceCents: (int) $snapshot->purchase_price_cents,
            storagePriceCents: (int) $snapshot->storage_price_cents,
            fulfillmentMode: (string) $snapshot->fulfillment_mode,
            stockCount: $snapshot->stock_count !== null ? (int) $snapshot->stock_count : null,
            rankingScore: $snapshot->ranking_score !== null ? (string) $snapshot->ranking_score : null,
            fullPayloadJson: (array) $snapshot->full_payload_json,
        );
    }
}
