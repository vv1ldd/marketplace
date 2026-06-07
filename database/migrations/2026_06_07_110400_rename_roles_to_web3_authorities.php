<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        foreach (User::LEGACY_ROLE_RENAMES as $legacyRole => $canonicalRole) {
            $this->renameRoleAssignments($legacyRole, $canonicalRole, 'web');
        }

        $this->renameRoleAssignments('b2b_partner', User::ROLE_MERCHANT_NODE, 'sellers');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        foreach (array_flip(User::LEGACY_ROLE_RENAMES) as $canonicalRole => $legacyRole) {
            $this->renameRoleAssignments($canonicalRole, $legacyRole, 'web');
        }

        $this->renameRoleAssignments(User::ROLE_MERCHANT_NODE, 'b2b_partner', 'sellers');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function renameRoleAssignments(string $fromRoleName, string $toRoleName, string $guardName): void
    {
        $now = now();
        $fromRole = DB::table('roles')
            ->where('name', $fromRoleName)
            ->where('guard_name', $guardName)
            ->first();

        $toRole = DB::table('roles')
            ->where('name', $toRoleName)
            ->where('guard_name', $guardName)
            ->first();

        if (! $toRole) {
            $toRoleId = DB::table('roles')->insertGetId([
                'name' => $toRoleName,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $toRole = (object) ['id' => $toRoleId];
        }

        if (! $fromRole) {
            return;
        }

        if (Schema::hasTable('model_has_roles')) {
            $assignments = DB::table('model_has_roles')
                ->where('role_id', $fromRole->id)
                ->get();

            foreach ($assignments as $assignment) {
                $payload = [
                    'role_id' => $toRole->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ];

                if (property_exists($assignment, 'team_id')) {
                    $payload['team_id'] = $assignment->team_id;
                }

                DB::table('model_has_roles')->updateOrInsert($payload, $payload);
            }

            DB::table('model_has_roles')
                ->where('role_id', $fromRole->id)
                ->delete();
        }

        if (Schema::hasTable('role_has_permissions')) {
            $permissions = DB::table('role_has_permissions')
                ->where('role_id', $fromRole->id)
                ->get();

            foreach ($permissions as $permission) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permission->permission_id,
                    'role_id' => $toRole->id,
                ], [
                    'permission_id' => $permission->permission_id,
                    'role_id' => $toRole->id,
                ]);
            }

            DB::table('role_has_permissions')
                ->where('role_id', $fromRole->id)
                ->delete();
        }

        DB::table('roles')
            ->where('id', $fromRole->id)
            ->delete();
    }
};
