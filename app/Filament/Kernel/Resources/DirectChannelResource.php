<?php

namespace App\Filament\Kernel\Resources;

use App\Filament\Kernel\Resources\DirectChannelResource\Pages;
use App\Filament\Resources\DirectChannels\RelationManagers\PostsRelationManager;
use App\Filament\Resources\DirectChannels\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\DirectChannels\Schemas\DirectChannelForm;
use App\Filament\Resources\DirectChannels\Tables\DirectChannelsTable;
use App\Models\DirectChannel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DirectChannelResource extends Resource
{
    protected static ?string $model = DirectChannel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Прямые каналы продаж';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System Topology';
    }

    public static function getLabel(): ?string
    {
        return 'Прямой канал продаж';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Прямые каналы продаж';
    }

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return DirectChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DirectChannelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PostsRelationManager::class,
            ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDirectChannels::route('/'),
            'create' => Pages\CreateDirectChannel::route('/create'),
            'edit' => Pages\EditDirectChannel::route('/{record}/edit'),
        ];
    }
}
