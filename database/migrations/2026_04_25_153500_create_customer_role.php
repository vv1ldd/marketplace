<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the customer role if it doesn't exist
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // 2. Assign 'customer' role to all users who are NOT system users
        $systemRoles = User::SYSTEM_ROLES;

        User::whereDoesntHave('roles', function ($query) use ($systemRoles) {
            $query->whereIn('name', $systemRoles);
        })->get()->each(function (User $user) use ($customerRole) {
            $user->assignRole($customerRole);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional: remove assignments or role
    }
};
