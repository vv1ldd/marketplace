<?php

namespace App\Filament\Partner\Resources\Warehouses;

use App\Http\Services\YmService;
use App\Jobs\DistributeStockToChannels;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;
    
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('is_main', true);
    }

    protected static bool $isScopedToTenant = true;

    /**
     * 🛡️ OVERRIDE: Disable automatic model-ownership interception on creation.
     * Warehouse has a HasOneThrough relationship to LegalEntity, which does not support ->save().
     */
    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
        // Disable automated creation hooks.
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    public static function getRelations(): array
    {
        return [
            \App\Filament\Partner\Resources\Warehouses\RelationManagers\StocksRelationManager::class,
        ];
    }

    public static function getNavigationLabel(): string { return 'Склады'; }
    public static function getLabel(): string { return 'Склад'; }
    public static function getPluralLabel(): string { return 'Склады'; }

    private static function channelOptions(): array
    {
        return [
            ''               => '⭐ Мастер-склад (источник)',
            'yandex_market'  => '🟡 Яндекс Маркет',
            'ozon'           => '🔵 Ozon',
            'wildberries'    => '🟣 Wildberries',
            'avito'          => '🟢 Авито',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Информация о складе')
                ->schema([
                    Select::make('shop_id')
                        ->label('Магазин')
                        ->relationship('shop', 'name', function ($query) {
                            // Only show shops that belong to the current Tenant (LegalEntity)
                            return $query->where('legal_entity_id', \Filament\Facades\Filament::getTenant()->id);
                        })
                        ->required()
                        ->searchable()
                        ->preload(),

                    TextInput::make('name')
                        ->label('Название')
                        ->default('Основной склад')
                        ->required(),
                        
                    \Filament\Forms\Components\Hidden::make('is_main')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Partner\Resources\Warehouses\Pages\ManageWarehouses::route('/'),
            'edit'  => \App\Filament\Partner\Resources\Warehouses\Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
