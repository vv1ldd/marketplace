<?php

namespace App\Filament\Partner\Resources\ProviderProductResource\Pages;

use App\Filament\Partner\Resources\ProviderProductResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProviderProducts extends ListRecords
{
    protected static string $resource = ProviderProductResource::class;

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Все товары')
                ->icon('heroicon-m-squares-2x2'),

            'gaming' => Tab::make('Игры')
                ->icon('heroicon-m-device-phone-mobile')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('brand_id', \App\Models\Brand::whereIn('name', [
                    'PlayStation', 'Xbox', 'Nintendo', 'Steam', 'Roblox', 'Minecraft', 'Fortnite', 'PUBG', 'Free Fire', 'Mobile Legends', 'Genshin Impact', 'Valorant', 'League of Legends', 'Riot Games', 'Blizzard', 'Electronic Arts', 'Ubisoft',
                ])->pluck('id'))),

            'software' => Tab::make('Сервисы и Софт')
                ->icon('heroicon-m-cpu-chip')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('brand_id', \App\Models\Brand::whereIn('name', [
                    'Microsoft', 'Apple', 'Google Play', 'Netflix', 'Spotify', 'Disney+', 'Hulu', 'Crunchyroll', 'Discord', 'Tinder', 'Bumble', 'Paramount+',
                ])->pluck('id'))),

            'shopping' => Tab::make('Шопинг и Еда')
                ->icon('heroicon-m-shopping-bag')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('brand_id', \App\Models\Brand::whereIn('name', [
                    'Amazon', 'eBay', 'Walmart', 'Target', 'Best Buy', 'Nike', 'Adidas', 'Sephora', 'IKEA', 'H&M', 'Zara', 'SHEIN', 'Starbucks', 'Uber', 'Airbnb',
                ])->pluck('id'))),

            'crypto' => Tab::make('Крипто и Финансы')
                ->icon('heroicon-m-currency-dollar')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('brand_id', \App\Models\Brand::whereIn('name', [
                    'Binance', 'Crypto.com', 'Visa', 'Mastercard', 'American Express',
                ])->pluck('id'))),
        ];

        return $tabs;
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
