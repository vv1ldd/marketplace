<?php

namespace App\Filament\Resources\B2B;

use App\Filament\Resources\B2B\Pages\CreateLegalEntity;
use App\Filament\Resources\B2B\Pages\EditLegalEntity;
use App\Filament\Resources\B2B\Pages\ListLegalEntities;
use App\Filament\Resources\B2B\RelationManagers\ShopsRelationManager;
use App\Models\LegalEntity;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'admin.navigation.b2b';

    public static function getLabel(): ?string
    {
        return __('admin.b2b.legal_entity');
    }

    public static function getPluralLabel(): ?string
    {
        return __('admin.b2b.legal_entities');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(\App\Filament\Resources\B2B\Schemas\LegalEntitySchema::get());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inn')
                    ->label(__('admin.b2b.fields.inn'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('admin.b2b.fields.full_name'))
                    ->searchable()
                    ->limit(30)
                    ->sortable(),
                TextColumn::make('seller.email')
                    ->label(__('admin.b2b.fields.owner'))
                    ->searchable(),
                TextColumn::make('shops_count')
                    ->label(__('admin.b2b.fields.shops_count'))
                    ->counts('shops'),
                IconColumn::make('is_active')
                    ->label(__('admin.b2b.sections.status'))
                    ->boolean(),
                TextColumn::make('available_balance')
                    ->label('Баланс (RUB)')
                    ->money('RUB')
                    ->color('success')
                    ->sortable(),
                TextColumn::make('reserved_balance')
                    ->label('Холды (RUB)')
                    ->money('RUB')
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.orders.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\Action::make('topUp')
                    ->label('Пополнить баланс')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Сумма (RUB)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        \Filament\Forms\Components\Textarea::make('comment')
                            ->label('Комментарий')
                            ->placeholder('Напр. Оплата по счету №123'),
                    ])
                    ->action(function (LegalEntity $record, array $data) {
                        $amount = (float) $data['amount'];
                        
                        // Update balance
                        $record->increment('available_balance', $amount);
                        
                        // ⛓️ Sovereign Ledger: Record the top-up
                        // We pick the first shop of the entity to anchor the ledger event
                        $shop = $record->shops()->first() ?? \App\Models\Shop::first();
                        
                        if ($shop) {
                            app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_TOPUP', $record, [
                                'amount' => $amount,
                                'comment' => $data['comment'] ?? '',
                                'new_balance' => $record->available_balance,
                                'admin_id' => auth()->id(),
                                'admin_email' => auth()->user()?->email,
                            ]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Баланс пополнен')
                            ->body("Добавлено {$amount} RUB к счету {$record->name}")
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 10;

    public static function getRelations(): array
    {
        return [
            ShopsRelationManager::class,
            \App\Filament\Resources\B2B\RelationManagers\ManagersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalEntities::route('/'),
            'create' => CreateLegalEntity::route('/create'),
            'edit' => EditLegalEntity::route('/{record}/edit'),
        ];
    }
}
