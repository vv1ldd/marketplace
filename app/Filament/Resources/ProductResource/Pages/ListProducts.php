<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('generate_images')
                ->label('Сгенерировать картинки')
                ->icon('heroicon-o-photo')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
                    $products = \App\Models\Product::whereNull('image')->limit(50)->get();
                    $generator = new \App\Services\ImageGenerator();
                    $count = 0;
                    foreach ($products as $product) {
                        try {
                            $data = $product->data['data'] ?? [];
                            $generateData = [
                                'sku' => $product->sku,
                                'price' => $data['price'] ?? 0,
                                'symbol' => $data['product']['currency']['symbol'] ?? ($product->type === 'playstation' ? ' TL' : ''),
                                'category' => $product->data['category'] ?? ($product->type === 'playstation' ? 'ps' : 'other'),
                                'region_code' => $data['product']['regions'][0]['code'] ?? 'TR',
                            ];
                            
                            $path = $generator->generate($generateData);
                            $product->update([
                                'image' => $path,
                                'image_updated_at' => now(),
                            ]);
                            $count++;
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Image generation failed for product {$product->sku}: " . $e->getMessage());
                        }
                    }
                    \Filament\Notifications\Notification::make()->title("Сгенерировано картинок: $count")->success()->send();
                })
        ];
    }
}
