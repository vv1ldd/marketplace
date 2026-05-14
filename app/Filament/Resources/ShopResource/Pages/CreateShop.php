<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShop extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $allSelected = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'cat_') && is_array($value)) {
                $allSelected = array_merge($allSelected, $value);
            }
        }
        $data['allowed_categories'] = array_values(array_unique($allSelected));

        return $data;
    }

    protected static string $resource = ShopResource::class;

    protected function afterCreate(): void
    {
        $this->record->syncLegalEntityManager();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
