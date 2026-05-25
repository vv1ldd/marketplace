<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\SellerTerminalResource;
use App\Models\SellerTerminal;
use Filament\Resources\Pages\CreateRecord;

class CreateSellerTerminal extends CreateRecord
{
    protected static string $resource = SellerTerminalResource::class;

    protected ?string $generatedPin = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->generatedPin = SellerTerminal::generatePin();
        $data['terminal_pin'] = $this->generatedPin;
        return $data;
    }

    protected function afterCreate(): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Терминал успешно создан!')
            ->body("ID Терминала: **{$this->record->terminal_id}**\nPIN: **{$this->generatedPin}**\n\nПожалуйста, сохраните эти данные. PIN зашифрован в базе данных и больше никогда не будет показан.")
            ->success()
            ->persistent()
            ->send();
    }
}
