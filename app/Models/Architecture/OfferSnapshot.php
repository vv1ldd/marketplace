<?php

namespace App\Models\Architecture;

use App\Models\CanonicalProductIdentity;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferSnapshot extends Model
{
    public const KIND_SELLER_LISTING = 'seller_listing';

    public const KIND_PROVIDER_SUPPLY = 'provider_supply';

    public const KIND_FIRST_PARTY = 'first_party';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'snapshot_uuid',
        'canonical_product_identity_id',
        'entitlement_fingerprint',
        'shop_id',
        'product_id',
        'sku',
        'provider_id',
        'provider_product_id',
        'provider_sku',
        'offer_kind',
        'buyer_price_cents',
        'buyer_currency',
        'purchase_price_cents',
        'storage_price_cents',
        'fulfillment_mode',
        'stock_count',
        'ranking_score',
        'full_payload_json',
        'valid_from',
        'valid_until',
        'superseded_by_id',
        'created_at',
    ];

    protected $casts = [
        'full_payload_json' => 'array',
        'buyer_price_cents' => 'integer',
        'purchase_price_cents' => 'integer',
        'storage_price_cents' => 'integer',
        'stock_count' => 'integer',
        'ranking_score' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(CanonicalProductIdentity::class, 'canonical_product_identity_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function providerProduct(): BelongsTo
    {
        return $this->belongsTo(ProviderProduct::class);
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function executionRecords(): HasMany
    {
        return $this->hasMany(ExecutionRecord::class, 'offer_snapshot_id');
    }

    public function isOpen(): bool
    {
        return $this->valid_until === null;
    }
}
