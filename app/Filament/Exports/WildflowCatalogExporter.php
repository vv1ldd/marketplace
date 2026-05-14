<?php

namespace App\Filament\Exports;

use App\Models\Shop;
use App\Models\WildflowCatalog;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class WildflowCatalogExporter extends Exporter
{
    protected static ?string $model = WildflowCatalog::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('sku')
                ->label('SKU')
                ->state(fn ($record) => 'ITEM-' . strtoupper(substr(md5($record->sku), 0, 8))),
            ExportColumn::make('title')
                ->label('Название товара')
                ->state(function ($record, array $options) {
                    $shop = Shop::find($options['tenant_id'] ?? null);

                    return $record->getTitleForShop($shop);
                }),
            ExportColumn::make('brand.name')
                ->label('Бренд'),
            ExportColumn::make('category')
                ->label('Категория'),
            ExportColumn::make('reward_type')
                ->label('Тип вознаграждения'),
            ExportColumn::make('price_for_me')
                ->label('Ваша цена')
                ->state(function ($record, array $options) {
                    $shop = Shop::find($options['tenant_id'] ?? null);

                    return $shop ? $record->getPurchasePriceForShop($shop) : null;
                })
                ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, '.', '') : null),
            ExportColumn::make('retail_price')
                ->label('Розничная цена')
                ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, '.', '') : null),
            ExportColumn::make('currency_code')
                ->label('Валюта'),
            ExportColumn::make('region.name_ru')
                ->label('Регион'),
            ExportColumn::make('activation_url')
                ->label('Ссылка на активацию'),
            ExportColumn::make('upc')
                ->label('UPC')
                ->state(function ($record, array $options) {
                    $shop = Shop::find($options['tenant_id'] ?? null);

                    return $record->getUpcForShop($shop);
                }),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your wildflow catalog export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
