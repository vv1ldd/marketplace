<?php

namespace App\Filament\Kernel\Resources\ProviderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class ProviderProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'providerProducts';

    protected static ?string $title = 'Сырые товары провайдера';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')
                ->label('Provider SKU')
                ->required(),
            TextInput::make('name')
                ->label('Название'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('Provider SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('market_sku')
                    ->label('Market SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->description(fn ($record) => $record->category ?? $record->brand?->name)
                    ->formatStateUsing(function ($record) {
                        $name = $record->name;
                        $region = $record->region?->code ?? $record->region?->name_ru;
                        $nominal = $record->retail_price > 0 ? $record->retail_price : $record->purchase_price;
                        $currency = $record->currency;
                        
                        // Чистое число без лишних нулей для сравнения
                        $nominalClean = (string) (float) $nominal;
                        $nominalInt = (string) (int) $nominal;
                        
                        // Если в названии уже есть номинал (как число или как инт), не дублируем
                        if (str_contains($name, $nominalClean) || str_contains($name, $nominalInt)) {
                             return $name . ($region ? " ({$region})" : "");
                        }

                        // Если это явно топап и в имени уже есть цифры - скорее всего номинал там уже есть
                        if (preg_match('/\d+/', $name) && ($record->provider_id == 4 || str_contains(strtolower($name), 'topup'))) {
                             return $name . ($region ? " ({$region})" : "");
                        }

                        return "{$name}" . ($region ? " ({$region})" : "") . " — {$nominal} {$currency}";
                    })
                    ->searchable(['name', 'sku'])
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('Закупка (ориг)')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // We usually sync them, no manual creation
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
