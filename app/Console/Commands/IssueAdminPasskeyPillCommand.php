<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\LegalEntityMigrationPill;
use App\Models\User;
use App\Services\LegalEntityMigrationPillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class IssueAdminPasskeyPillCommand extends Command
{
    protected $signature = 'meanly:admin-passkey-pill
        {email? : Admin email. Defaults to the first super_admin or admin@meanly.local}
        {--domain= : Domain or URL where the pill should be opened}
        {--expires-days=7 : Pill lifetime in days}
        {--name=Main Admin : Display name for a newly created admin user}';

    protected $description = 'Issue a one-time Passkey enrollment pill for the main admin panel.';

    public function handle(LegalEntityMigrationPillService $pills): int
    {
        $targetDomain = $this->option('domain') ?: config('app.production_domain', config('app.domain', 'localhost'));
        $expiresDays = max(1, min(30, (int) $this->option('expires-days')));
        $admin = $this->adminUser((string) ($this->argument('email') ?: ''));
        $legalEntity = $this->adminLegalEntity($admin);

        [$pill, $token] = $pills->issueForOwner(
            legalEntity: $legalEntity,
            targetDomain: $targetDomain,
            issuedBy: $admin,
            issuedIp: 'cli',
            expiresAt: now()->addDays($expiresDays),
        );

        $pill->forceFill([
            'metadata' => array_merge($pill->metadata ?? [], [
                'purpose' => 'main_admin_passkey_enrollment',
                'panel' => 'ops',
                'redirect_url' => $this->adminPanelUrl($targetDomain),
            ]),
        ])->save();

        $this->info('Admin Passkey enrollment pill issued.');
        $this->line('Admin: '.$admin->email);
        $this->line('Panel: ops');
        $this->line('Expires: '.$pill->expires_at?->toIso8601String());
        $this->newLine();
        $this->line($pills->migrationUrl($token, $pill->target_domain));

        return self::SUCCESS;
    }

    private function adminUser(string $email): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = $email !== ''
            ? User::findByEmail($email)
            : User::role('super_admin')->oldest('id')->first();

        if (! $user) {
            $email = $email !== '' ? $email : 'admin@meanly.local';
            $name = trim((string) $this->option('name')) ?: 'Main Admin';
            $parts = preg_split('/\s+/', $name, 2) ?: ['Main', 'Admin'];

            $user = User::create([
                'first_name' => $parts[0] ?? 'Main',
                'last_name' => $parts[1] ?? 'Admin',
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'password_login_enabled' => false,
            ]);
        }

        if (! $user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }

        return $user->refresh();
    }

    private function adminLegalEntity(User $admin): LegalEntity
    {
        $entity = LegalEntity::query()
            ->where('user_id', $admin->id)
            ->where('status', 'admin_console')
            ->first();

        if ($entity) {
            return $entity;
        }

        return LegalEntity::create([
            'user_id' => $admin->id,
            'name' => 'Meanly Main Admin Console',
            'short_name' => 'Meanly Admin',
            'inn' => '000000000001',
            'email' => $admin->email,
            'director_name' => trim(($admin->first_name ?? '').' '.($admin->last_name ?? '')) ?: $admin->email,
            'available_balance' => 0,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'tariff_type' => 'internal',
            'is_active' => true,
            'status' => 'admin_console',
        ]);
    }

    private function adminPanelUrl(?string $targetDomain): string
    {
        $domain = trim((string) $targetDomain);
        $base = $domain === '' ? config('app.url', 'http://localhost') : $domain;
        $base = str_starts_with($base, 'http://') || str_starts_with($base, 'https://')
            ? $base
            : 'https://'.$base;

        return rtrim($base, '/').'/ops';
    }
}
