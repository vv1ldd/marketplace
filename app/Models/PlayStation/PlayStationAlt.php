<?php

namespace App\Models\PlayStation;

use Illuminate\Database\Eloquent\Model;

class PlayStationAlt extends Model
{
    protected $fillable = [
        'sku',
        'data',
        'base_price',
        'region_id',
        'concept_id',
        'price_with_discount',
        'name',
        'updated_at',
        'is_group',
        'send_to_ym_at',
        'category_id',
        'is_manual',
        'type_form_id',
        'woo_price_rub',
        'woo_price_try',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
    ];

    public function typeForm()
    {
        return $this->belongsTo(PlayStationTypeForm::class, 'type_form_id', 'id');
    }

    public static function getPrice(string $sku, string $value): float|int
    {
        return static::where('sku', $sku)->value($value) / 100;
    }
}
