<?php

namespace App\Models;

use App\Models\PlayStation\PlayStationTypeForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'type',
        'category',
        'price_rub',
        'purchase_price',
        'purchase_currency',
        'base_price',
        'type_form_id',
        'data',
        'is_manual',
        'is_active',
        'image',
        'image_updated_at',
        'send_to_ym_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_manual' => 'boolean',
        'is_active' => 'boolean',
        'send_to_ym_at' => 'datetime',
    ];

    public function typeForm(): BelongsTo
    {
        return $this->belongsTo(PlayStationTypeForm::class, 'type_form_id', 'id');
    }

    public static function getPrice(string $sku, string $value): float|int
    {
        return (int)static::where('sku', $sku)->value($value) / 100;
    }
}
