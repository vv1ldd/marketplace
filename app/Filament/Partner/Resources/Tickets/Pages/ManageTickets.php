<?php

namespace App\Filament\Partner\Resources\Tickets\Pages;

use App\Filament\Partner\Resources\Tickets\TicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTickets extends ManageRecords
{
    protected static string $resource = TicketResource::class;

    public function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('shop_id', \Filament\Facades\Filament::getTenant()->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Создать обращение')
                ->modalHeading('Новое обращение в поддержку')
                ->form([
                    \Filament\Forms\Components\TextInput::make('subject')
                        ->label('Тема')
                        ->required(),
                    \Filament\Forms\Components\Select::make('priority')
                        ->label('Приоритет')
                        ->options([
                            'low' => 'Низкий',
                            'medium' => 'Средний',
                            'high' => 'Высокий',
                        ])
                        ->default('medium')
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('message')
                        ->label('Сообщение')
                        ->required()
                        ->rows(5),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data['seller_id'] = auth()->id();
                    $data['shop_id'] = \Filament\Facades\Filament::getTenant()->id;
                    return $data;
                })
                ->after(function (\App\Models\Ticket $record, array $data): void {
                    $record->messages()->create([
                        'seller_id' => auth()->id(),
                        'message' => $data['message'],
                        'is_admin_reply' => false,
                    ]);
                }),
        ];
    }
}
