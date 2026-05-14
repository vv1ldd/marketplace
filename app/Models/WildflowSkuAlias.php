<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WildflowSkuAlias extends Model
{
    protected $table = 'wildflow_sku_aliases';

    protected $fillable = [
        'alias_sku',
        'wildflow_catalog_sku',
    ];

    public static function syncForProduct(Product $product): void
    {
        $canonical = trim((string) $product->wildflow_catalog_sku);
        if ($canonical === '') {
            return;
        }

        static::query()->updateOrCreate(
            ['alias_sku' => $product->sku],
            ['wildflow_catalog_sku' => $canonical]
        );
    }
}
