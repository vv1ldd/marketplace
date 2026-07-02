<?php

namespace App\Services\Architecture;

use App\DTO\Architecture\OfferSnapshotData;
use App\Models\CanonicalProductIdentity;
use App\Models\Product;

interface OfferSnapshotServiceInterface
{
    /**
     * Create immutable snapshot from current Knowledge projections.
     * Called at checkout / pre-execution boundary.
     */
    public function createFromRuntimeContext(
        CanonicalProductIdentity $entitlement,
        int $productId,
        int $providerId,
        string $sku,
        ?string $selectedBy = 'checkout',
    ): OfferSnapshotData;

    /**
     * Resolve entitlement + provider context from a storefront Product and pin a snapshot.
     */
    public function createFromProduct(Product $product, ?string $selectedBy = 'checkout'): OfferSnapshotData;

    public function getOrFail(string $snapshotId): OfferSnapshotData;

    public function serviceSkuFromSnapshot(OfferSnapshotData $snapshot): string;

    public function catalogSkuFromSnapshot(OfferSnapshotData $snapshot): string;
}
