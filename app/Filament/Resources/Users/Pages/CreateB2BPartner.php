<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\B2BPartnerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateB2BPartner extends CreateRecord
{
    protected static string $resource = B2BPartnerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(64));
        $data['password_login_enabled'] = false;

        return $data;
    }
}
