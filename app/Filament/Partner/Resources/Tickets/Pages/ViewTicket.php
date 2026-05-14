<?php

namespace App\Filament\Partner\Resources\Tickets\Pages;

use App\Filament\Partner\Resources\Tickets\TicketResource;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected string $view = 'filament.partner.resources.tickets.view-ticket';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Textarea::make('message')
                    ->label('')
                    ->placeholder('Введите ваше сообщение...')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function createMessage(): void
    {
        $data = $this->form->getState();

        $this->record->messages()->create([
            'seller_id' => auth()->id(),
            'message' => $data['message'],
            'is_admin_reply' => false,
        ]);

        // Если тикет был закрыт, возвращаем его в работу при новом сообщении селлера
        $this->record->update([
            'status' => 'in_progress',
            'last_reply_at' => now(),
        ]);

        $this->form->fill();

        \Filament\Notifications\Notification::make()
            ->title('Сообщение отправлено')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('status_badge')
                ->label(fn () => match ($this->record->status) {
                    'open' => 'НОВЫЙ',
                    'in_progress' => 'В РАБОТЕ',
                    'closed' => 'РЕШЕН',
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
