<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProviderProduct extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function ($item) {
            if (empty($item->canonical_category)) {
                $item->canonical_category = app(\App\Services\CanonicalCategoryResolver::class)->fromPayload($item->data ?? [], [
                    $item->name,
                    $item->category,
                    $item->reward_type,
                ]);
            }

            $item->discovery_intent = app(\App\Services\CanonicalCategoryResolver::class)->discoveryIntent(
                (string) $item->canonical_category,
                [
                    $item->brand?->name,
                    $item->name,
                    $item->category,
                    $item->reward_type,
                ],
            );
        });

        static::updated(function ($item) {
            $changes = $item->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            app(\App\Services\LedgerService::class)->recordGlobal('PROVIDER_PRODUCT_MAPPING_UPDATED', $item, [
                'sku' => $item->sku,
                'provider_id' => $item->provider_id,
                'changes' => $changes,
                'original' => array_intersect_key($item->getOriginal(), $changes)
            ]);
        });
    }

    protected $fillable = [
        'provider_id',
        'brand_id',
        'region_id',
        'sku',
        'market_sku',
        'name',
        'category',
        'canonical_category',
        'discovery_intent',
        'reward_type',
        'purchase_price',
        'retail_price',
        'min_price',
        'max_price',
        'currency',
        'image',
        'activation_url',
        'redemption_instructions',
        'is_active',
        'data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'data' => \App\Casts\VaultEncryptedJson::class,
        'purchase_price' => 'decimal:2',
        'retail_price'   => 'decimal:2',
        'min_price'      => 'decimal:2',
        'max_price'      => 'decimal:2',
        'sku' => \App\Casts\VaultEncrypted::class . ':sku_bidx',
        'market_sku' => \App\Casts\VaultEncrypted::class . ':market_sku_bidx',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function region()
    {
        return $this->belongsTo(MappingCountry::class, 'region_id');
    }

    public function canonicalIdentitySource()
    {
        return $this->hasOne(CanonicalProductIdentitySource::class, 'source_id')
            ->where('source_type', CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT);
    }

    /**
     * Generate a deterministic 12-digit UPC for a seller shop.
     * Same algorithm as WildflowCatalog::getUpcForShop() — keyed on sku + shop_id.
     */
    public function getUpcForShop($shop): ?string
    {
        if ($shop instanceof \App\Models\LegalEntity) {
            $shop = $shop->shops()->first();
        }

        if (! $shop) {
            return null;
        }

        $hash    = hash('sha256', $this->sku . '-shop-' . $shop->id);
        $numbers = preg_replace('/[^0-9]/', '', $hash);
        $upc     = substr(str_pad($numbers, 12, '1', STR_PAD_RIGHT), 0, 12);

        if ($upc[0] === '0') {
            $upc[0] = '4';
        }

        return $upc;
    }

    /**
     * Return name (same for all shops — no shop-specific naming for ProviderProduct).
     */
    public function getTitleForShop($shop): string
    {
        return $this->name ?? '';
    }
}
