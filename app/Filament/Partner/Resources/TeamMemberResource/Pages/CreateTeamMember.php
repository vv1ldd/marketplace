<?php

namespace App\Filament\Partner\Resources\TeamMemberResource\Pages;

use App\Filament\Partner\Resources\TeamMemberResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class CreateTeamMember extends CreateRecord
{
    protected static string $resource = TeamMemberResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $role = $data['role'] ?? 'manager';
        unset($data['role']);

        // Check if user exists by email (using Blind Index — email field is encrypted)
        $user = User::findByEmail($data['email']);

        if (!$user) {
            // Create new user with a random password if it doesn't exist
            $user = User::create([
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'password' => Hash::make(Str::random(16)),
                'password_login_enabled' => false,
            ]);
            
            // Assign base manager role if using Spatie Permissions
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('manager');
            }
        }

        // Link to the current legal entity
        $tenant = Filament::getTenant();
        $user->managedLegalEntities()->syncWithoutDetaching([
            $tenant->id => ['role' => $role]
        ]);

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
