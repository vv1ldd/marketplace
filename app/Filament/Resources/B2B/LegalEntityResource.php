<?php

namespace App\Filament\Resources\B2B;

use App\Filament\Resources\B2B\Pages\CreateLegalEntity;
use App\Filament\Resources\B2B\Pages\EditLegalEntity;
use App\Filament\Resources\B2B\Pages\ListLegalEntities;
use App\Filament\Resources\B2B\RelationManagers\ShopsRelationManager;
use App\Filament\Resources\B2B\RelationManagers\SovereignBalanceRequestsRelationManager;
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
                    ->label('Локальный баланс')
                    ->money('RUB')
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('kernel_balance')
                    ->label('Баланс в Кернеле (USD)')
                    ->getStateUsing(function (LegalEntity $record) {
                        try {
                            $data = (new \App\Services\WildflowService())->getPartner((string)$record->id);
                            return $data['balance'] ?? 0;
                        } catch (\Exception $e) {
                            return 'Error';
                        }
                    })
                    ->money('USD')
                    ->color('success'),
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
                        $reference = $data['comment'] ?? "Top-up via Marketplace Admin";

                        try {
                            // 1. Update Remote Sovereign Ledger (Kernel)
                            (new \App\Services\WildflowService())->topUp((string)$record->id, $amount, $reference);
                            
                            // 2. Update Local Mirror
                            $record->increment('available_balance', $amount);
                            $record->increment('balance', $amount);
                            
                            // 3. Local Ledger Record
                            $shop = $record->shops()->first() ?? \App\Models\Shop::first();
                            if ($shop) {
                                app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_TOPUP', $record, [
                                    'asset' => 'RUBT',
                                    'amount' => $amount,
                                    'amount_rub' => $amount,
                                    'token_amount' => $amount,
                                    'currency' => 'RUB',
                                    'token_currency' => 'RUBT',
                                    'backing_currency' => 'RUB',
                                    'backing_ratio' => 1,
                                    'comment' => $reference,
                                    'new_balance' => $record->available_balance,
                                    'admin_id' => auth()->id(),
                                ]);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Баланс пополнен')
                                ->body("Успешно добавлено {$amount} RUB в Кернел и локально.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Ошибка пополнения')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\Action::make('syncWithKernel')
                    ->label('Синхронизировать')
                    ->icon('heroicon-m-arrow-path')
                    ->color('info')
                    ->action(function (LegalEntity $record) {
                        try {
                            (new \App\Services\WildflowService())->syncPartner($record, $record->vendor_credentials ?? []);
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Синхронизация успешна')
                                ->body("Терминал для {$record->name} подтвержден в Кернеле.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Ошибка синхронизации')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\Action::make('migrationPill')
                    ->label('Migration pill')
                    ->icon('heroicon-m-key')
                    ->color('warning')
                    ->action(function (LegalEntity $record) {
                        [$pill, $token] = app(\App\Services\LegalEntityMigrationPillService::class)->issueForOwner(
                            legalEntity: $record,
                            targetDomain: config('app.production_domain', config('app.domain')),
                            issuedBy: auth()->user(),
                            issuedIp: request()->ip(),
                            expiresAt: now()->addMinutes(30),
                        );

                        $url = app(\App\Services\LegalEntityMigrationPillService::class)->migrationUrl($token, $pill->target_domain);

                        \Filament\Notifications\Notification::make()
                            ->title('Одноразовая таблетка выпущена')
                            ->body($url)
                            ->success()
                            ->persistent()
                            ->send();
                    })
                    ->visible(fn (): bool => ! app()->isProduction() && config('app.env') !== 'production'),
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
            SovereignBalanceRequestsRelationManager::class,
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
