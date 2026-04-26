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

    public function toYmOffer(int $marketCategoryId, ?int $shopId = null): array
    {
        $data = $this->data;
        $name = $this->name;
        $description = $this->description ?? '';
        $pictures = [];
        $params = [];
        $vendor = 'Нет бренда';

        if ($shopId) {
            $pictures = [config('app.url') . "/img/card/sh_$shopId/{$this->sku}.png"];
        }

        if ($this->type === 'playstation') {
            // PlayStation specific logic
            if (empty($pictures) && ($media = data_get($data, 'media'))) {
                foreach ($media as $m) {
                    if ($m['type'] !== 'VIDEO') $pictures[] = $m['url'];
                }
            }
            
            $vendor = data_get($data, 'publisherName', 'Sony Interactive Entertainment');
            
            if ($platforms = data_get($data, 'platforms')) {
                $params[] = ["parameterId" => 45128695, "value" => implode(' & ', $platforms)];
            }
            
            if (empty($description) && !empty($data['descriptions'])) {
                foreach ($data['descriptions'] as $desc) {
                    if ($desc['type'] === 'LONG') $description .= $desc['value'];
                }
            }
            $description = str_replace('facebook', '(соц. сеть)', $description);
            
            $params[] = ["parameterId" => 37693330, "value" => "электронный ключ"];
            $params[] = ["parameterId" => 37972050, "value" => "без сервиса активации"];
            $params[] = ["parameterId" => 45132091, "value" => "цифровое"];
            $params[] = ["parameterId" => 45130810, "value" => $this->name];
            $params[] = ["parameterId" => 37919810, "value" => "все страны"];

        } elseif ($this->type === 'wildflow') {
            // Wildflow specific logic
            $wfItem = $data;
            $wfProduct = $wfItem['data']['product'] ?? $wfItem;
            
            $pictures = [$wfProduct['image'] ?? ''];
            if ($this->image) $pictures = [config('app.url') . '/' . $this->image];
            
            $params[] = ["parameterId" => 37821410, "value" => (int)($wfItem['data']['price'] ?? 0)];
            $params[] = ["parameterId" => 37919770, "value" => "в течение 1 месяца"];
            $params[] = ["parameterId" => 37978250, "value" => "пополнение счета"];
            $params[] = ["parameterId" => 37693330, "value" => "электронный ключ"];
            $params[] = ["parameterId" => 37919810, "value" => data_get($wfProduct, 'regions.0.name', 'все страны')];
        }

        return [
            "offerId" => $this->sku,
            "name" => $name,
            "marketCategoryId" => $marketCategoryId,
            "pictures" => array_filter($pictures),
            "vendor" => $vendor,
            "description" => mb_substr(strip_tags($description), 0, 3000),
            "parameterValues" => $params,
            "downloadable" => true,
            "basicPrice" => [
                "value" => $this->price_rub,
                "currencyId" => "RUR"
            ]
        ];
    }
}
