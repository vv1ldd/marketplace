<?php

namespace App\Filament\Partner\Resources\TeamMemberResource\Pages;

use App\Filament\Partner\Resources\TeamMemberResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Facades\Filament;

class EditTeamMember extends EditRecord
{
    protected static string $resource = TeamMemberResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $role = $data['role'] ?? 'manager';
        unset($data['role']);

        $record->update($data);

        // Update the pivot table role for this legal entity
        $tenant = Filament::getTenant();
        $record->managedLegalEntities()->updateExistingPivot($tenant->id, [
            'role' => $role
        ]);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
