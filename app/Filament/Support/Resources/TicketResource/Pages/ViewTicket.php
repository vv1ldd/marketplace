<?php

namespace App\Filament\Support\Resources\TicketResource\Pages;

use App\Filament\Support\Resources\TicketResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected string $view = 'filament.resources.tickets.pages.view-ticket';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->replyForm->fill([
            'status' => $this->record->status,
        ]);
    }

    protected function getForms(): array
    {
        return [
            'replyForm',
        ];
    }

    public function replyForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Textarea::make('message')
                    ->label('Ваш ответ')
                    ->placeholder('Введите ваш ответ...')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function createMessage(): void
    {
        $data = $this->replyForm->getState();

        $this->record->messages()->create([
            'user_id' => auth()->id(),
            'message' => $data['message'],
            'is_admin_reply' => true,
        ]);

        $this->record->update([
            'status' => 'in_progress',
            'last_reply_at' => now(),
        ]);

        $this->replyForm->fill([
            'message' => null,
        ]);

        Notification::make()
            ->title('Ответ отправлен')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('resolve')
                ->label('Решить тикет')
                ->color('success')
                ->icon('heroicon-m-check-circle')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'closed']);
                    
                    Notification::make()
                        ->title('Тикет помечен как решенный')
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->status !== 'closed'),

            \Filament\Actions\Action::make('status_badge')
                ->label(fn () => match ($this->record->status) {
                    'open' => 'НОВЫЙ',
                    'in_progress' => 'В РАБОТЕ',
                    'closed' => 'ЗАКРЫТ',
                    default => $this->record->status,
                })
                ->color(fn () => match ($this->record->status) {
                    'open' => 'danger',
                    'in_progress' => 'warning',
                    'closed' => 'success',
                    default => 'gray',
                })
                ->badge()
                ->disabled(),
        ];
    }
}
