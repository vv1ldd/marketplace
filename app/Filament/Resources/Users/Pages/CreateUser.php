<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\StaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = StaffResource::class;
}
