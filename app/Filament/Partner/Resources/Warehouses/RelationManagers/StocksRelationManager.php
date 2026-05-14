<?php

namespace App\Filament\Partner\Resources\Warehouses\RelationManagers;

use App\Http\Services\YmService;
use App\Models\Product;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $title = 'Остатки товаров';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('product_id')
                    ->label('Товар')
                    ->relationship('product', 'name', fn (Builder $query) => $query->whereHas('shop', fn($q) => $q->where('legal_entity_id', Filament::getTenant()?->id)))
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('count')
                    ->label('Количество')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('count')
                    ->label('Остаток')
                    ->sortable(),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Синхронизировано')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Manual addition disabled to ensure data integrity via automated procurement
            ])
            ->actions([
                // Ручное редактирование отключено, так как остатки управляются системой автоматически (закупка/виртуальные остатки)
            ])
            ->bulkActions([
                //
            ]);
    }
}
