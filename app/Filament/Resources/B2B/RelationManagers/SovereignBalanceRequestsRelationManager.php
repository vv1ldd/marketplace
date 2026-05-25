<?php

namespace App\Filament\Resources\B2B\RelationManagers;

use App\Models\SovereignBalanceRequest;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class SovereignBalanceRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'sovereignRequests';

    protected static ?string $title = 'Суверенные L1 Запросы Баланса';

    protected static ?string $label = 'Суверенный запрос';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID Запроса')
                    ->formatStateUsing(fn ($state) => "REQ-{$state}")
                    ->fontFamily('monospace')
                    ->weight('bold'),
                    
                TextColumn::make('type')
                    ->label('Тип запроса')
                    ->formatStateUsing(fn ($state) => $state === 'top_up' ? '💳 Пополнение (Replenish)' : '📈 Кредит (JIT Credit)')
                    ->weight('bold'),
                    
                TextColumn::make('amount')
                    ->label('Сумма (RUB)')
                    ->money('RUB')
                    ->color('primary')
                    ->weight('black'),
                    
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'Ожидание ⏳',
                        'approved' => 'Исполнен ✅',
                        'rejected' => 'Отклонен ❌',
                        default => $state,
                    }),
                    
                TextColumn::make('signature_verified')
                    ->label('L1 Signature')
                    ->html()
                    ->getStateUsing(fn () => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; font-weight: 800; font-size: 0.65rem; box-shadow: 0 0 10px rgba(16,185,129,0.15);">Verified ✅</span>'),
                    
                TextColumn::make('l1_address')
                    ->label('L1 Адрес')
                    ->fontFamily('monospace')
                    ->copyable()
                    ->limit(15),
                    
                TextColumn::make('comment')
                    ->label('Комментарий')
                    ->limit(30),
                    
                TextColumn::make('created_at')
                    ->label('Дата создания')
                    ->dateTime(),
            ])
            ->actions([
                Action::make('viewProof')
                    ->label('View Proof')
                    ->icon('heroicon-m-shield-check')
                    ->color('success')
                    ->modalHeading('🛡️ Sovereign L1 Cryptographic Proof')
                    ->modalWidth('3xl')
                    ->modalContent(fn (SovereignBalanceRequest $record) => view('filament.pages.signature-proof-modal', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть'),
                    
                Action::make('approve')
                    ->label('Approve & Execute')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (SovereignBalanceRequest $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Подтверждение и проведение транзакции')
                    ->modalDescription('Вы собираетесь криптографически подписать и провести данный запрос в сети L1 Aggregator. Средства будут синхронизированы JIT в режиме реального времени.')
                    ->action(function (SovereignBalanceRequest $record) {
                        try {
                            // 1. Execute Remote Transaction via WildflowService
                            if ($record->type === 'top_up') {
                                (new \App\Services\WildflowService())->topUp(
                                    (string)$record->legal_entity_id, 
                                    $record->amount, 
                                    $record->comment ?: "Sovereign top_up REQ-{$record->id}"
                                );
                            } else {
                                (new \App\Services\WildflowService())->grantCredit(
                                    $record->amount, 
                                    $record->comment ?: "Sovereign grant_credit REQ-{$record->id}", 
                                    (string)$record->legal_entity_id
                                );
                            }

                            // 2. Perform local Atomic updates
                            $record->legalEntity->increment('balance', $record->amount);
                            $record->legalEntity->increment('available_balance', $record->amount);

                            // 3. Mark request as Approved
                            $record->update([
                                'status' => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            // 4. Record transaction in local Sovereign Ledger
                            app(\App\Services\LedgerService::class)->record(
                                shop: null,
                                eventType: $record->type === 'top_up' ? 'FINANCE_DEPOSIT' : 'FINANCE_CREDIT_GRANTED',
                                entity: $record,
                                payload: [
                                    'request_id' => $record->id,
                                    'type' => $record->type,
                                    'asset' => 'RUBT',
                                    'amount' => $record->amount,
                                    'amount_rub' => $record->amount,
                                    'token_amount' => $record->amount,
                                    'currency' => $record->currency,
                                    'token_currency' => 'RUBT',
                                    'backing_currency' => 'RUB',
                                    'backing_ratio' => 1,
                                    'l1_address' => $record->l1_address,
                                    'passkey_id' => $record->passkey_id,
                                    'comment' => $record->comment ?: "Sovereign request cleared",
                                    'approved_by' => auth()->id(),
                                    'approved_at' => now()->toDateTimeString(),
                                ],
                                legalEntity: $record->legalEntity,
                                triggerSource: "DID:PASSKEY:{$record->l1_address}"
                            );

                            Notification::make()
                                ->title('Запрос успешно исполнен')
                                ->body("Криптографическая транзакция на сумму {$record->amount} ₽ успешно проведена в Кернеле.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Ошибка проведения транзакции')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                    
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (SovereignBalanceRequest $record) => $record->status === 'pending')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Причина отклонения')
                            ->required(),
                    ])
                    ->action(function (SovereignBalanceRequest $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'comment' => ($record->comment ? $record->comment . "\n" : "") . "Отклонено: " . $data['rejection_reason'],
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Запрос отклонен')
                            ->body('Суверенный запрос баланса успешно отклонен.')
                            ->danger()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
