<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderCategoryMapping extends Model
{
    protected $fillable = [
        'provider_id',
        'provider_category_name',
        'catalog_group_id',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function catalogGroup()
    {
        return $this->belongsTo(CatalogGroup::class);
    }

    protected static function booted()
    {
        static::created(function ($mapping) {
            app(\App\Services\LedgerService::class)->recordGlobal('PROVIDER_MAPPING_CREATED', $mapping, $mapping->toArray());
        });

        static::updated(function ($mapping) {
            $changes = $mapping->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            app(\App\Services\LedgerService::class)->recordGlobal('PROVIDER_MAPPING_UPDATED', $mapping, [
                'changes' => $changes,
                'original' => array_intersect_key($mapping->getOriginal(), $changes)
            ]);
        });

        static::deleted(function ($mapping) {
            app(\App\Services\LedgerService::class)->recordGlobal('PROVIDER_MAPPING_DELETED', $mapping, [
                'provider_id' => $mapping->provider_id,
                'category' => $mapping->provider_category_name
            ]);
        });
    }
}
