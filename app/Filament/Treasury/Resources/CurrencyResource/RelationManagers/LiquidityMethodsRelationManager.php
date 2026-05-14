<?php

namespace App\Filament\Treasury\Resources\CurrencyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LiquidityMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'liquidityMethods';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('liquidity_method_id')
                    ->relationship('liquidityMethods', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn ($operation) => $operation === 'edit'),
                Select::make('direction')
                    ->options([
                        'inbound' => 'Inbound (Entry)',
                        'outbound' => 'Outbound (Exit)',
                        'both' => 'Both Ways',
                    ])
                    ->required()
                    ->default('both'),
                TextInput::make('fee_percent')
                    ->label('Fee %')
                    ->numeric()
                    ->default(0)
                    ->suffix('%'),
                Forms\Components\Slider::make('risk_score')
                    ->label('Risk Score (0-100)')
                    ->min(0)
                    ->max(100)
                    ->step(5)
                    ->default(10),
                TextInput::make('latency_hours')
                    ->label('Latency (Hours)')
                    ->numeric()
                    ->default(0.5)
                    ->suffix('h'),
                TextInput::make('success_rate')
                    ->label('Success Rate %')
                    ->numeric()
                    ->default(99.9)
                    ->suffix('%'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'inbound' => 'info',
                        'outbound' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fee_percent')
                    ->label('Fee')
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('risk_score')
                    ->label('Risk')
                    ->badge()
                    ->color(fn (int $state): string => $state > 70 ? 'danger' : ($state > 30 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('latency_hours')
                    ->label('Latency')
                    ->suffix('h'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->form(fn (\Filament\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('direction')
                            ->options([
                                'inbound' => 'Inbound (Entry)',
                                'outbound' => 'Outbound (Exit)',
                                'both' => 'Both Ways',
                            ])
                            ->required()
                            ->default('both'),
                        TextInput::make('fee_percent')
                            ->numeric()
                            ->default(0),
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DetachAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DetachBulkAction::make(),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
