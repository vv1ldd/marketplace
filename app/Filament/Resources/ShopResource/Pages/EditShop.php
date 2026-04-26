<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShop extends EditRecord
{
    protected static string $resource = ShopResource::class;

    protected function afterSave(): void
    {
        $this->record->syncLegalEntityManager();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('update_ym_prices')
                ->label('Обновить цены YM')
                ->color('info')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $controller = app(\App\Http\Controllers\Ym\MainController::class);
                    $request = new \Illuminate\Http\Request(['business_id' => $this->record->business_id]);
                    
                    $response = $controller->sendItemsWildflow($request);
                    
                    if ($response->isSuccessful()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Цены успешно обновлены')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Ошибка при обновлении цен')
                            ->danger()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('update_ym_stocks')
                ->label('Обновить остатки YM')
                ->color('warning')
                ->icon('heroicon-o-archive-box')
                ->requiresConfirmation()
                ->action(function () {
                    $controller = app(\App\Http\Controllers\Ym\MainController::class);
                    $request = new \Illuminate\Http\Request(['business_id' => $this->record->business_id]);
                    
                    $response = $controller->prepareSendStockItemsWildflow($request);
                    
                    if ($response->isSuccessful()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Остатки успешно обновлены')
                            ->success()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Ошибка при обновлении остатков')
                            ->danger()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('upload_provider_products')
                ->label('Залить товары провайдера')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('provider')
                        ->label('Провайдер')
                        ->options([
                            'playstation' => 'PlayStation',
                            'wildflow' => 'Wildflow',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $shop = $this->record;
                    $provider = $data['provider'];
                    
                    $products = \App\Models\Product::where('type', $provider)
                        ->where('is_active', true)
                        ->get();
                    
                    $generator = new \App\Services\ImageGenerator();
                    $service = new \App\Http\Services\YmService($shop);
                    $categoryId = (int)($shop->ym_category_id ?? \App\Models\Settings::get('YM_CATEGORY_ID', 70301474));
                    
                    $offers = [];
                    foreach ($products as $p) {
                        try {
                            // 1. Generate Shop-Specific Image
                            $itemData = $p->data['data'] ?? [];
                            $genData = [
                                'sku' => $p->sku,
                                'price' => $itemData['price'] ?? ($p->price_rub / 100),
                                'symbol' => $itemData['product']['currency']['symbol'] ?? ($p->type === 'playstation' ? ' TL' : ''),
                                'category' => $p->category ?? ($p->type === 'playstation' ? 'ps' : 'other'),
                                'region_code' => $itemData['product']['regions'][0]['code'] ?? 'TR',
                            ];
                            
                            $generator->generate($genData, $shop->ym_base_card, $shop->ym_logo, $shop->id);

                            // 2. Prepare Offer with shopId
                            $offers[] = ["offer" => $p->toYmOffer($categoryId, $shop->id)];
                        } catch (\Exception $e) {
                             \Illuminate\Support\Facades\Log::error("Bulk Provider Sync failed for SKU {$p->sku}: " . $e->getMessage());
                        }
                    }

                    $chunks = array_chunk($offers, 50);
                    foreach ($chunks as $chunk) {
                        $service->offerMappingsUpdate($chunk);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Выгрузка завершена')
                        ->body("Активные товары ({$provider}) отправлены в магазин: {$shop->name}")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            DeleteAction::make(),
        ];
    }
}
