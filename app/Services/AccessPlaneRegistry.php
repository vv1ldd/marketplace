<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccessPlane;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class AccessPlaneRegistry
{
    /**
     * @return Collection<int, AccessPlane>
     */
    public function forUser(?User $user): Collection
    {
        return collect(config('access_planes.planes', []))
            ->map(fn (array $config, string $key): AccessPlane => $this->plane($key, $config, $user))
            ->values();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function plane(string $key, array $config, ?User $user): AccessPlane
    {
        $evaluation = $this->evaluate($config, $user);

        return new AccessPlane(
            key: $key,
            label: (string) ($config['label'] ?? $key),
            url: $this->url($config),
            authority: (string) ($config['authority'] ?? 'unknown'),
            description: (string) ($config['description'] ?? ''),
            available: $evaluation['available'],
            reason: $evaluation['reason'],
            metadata: [
                'required_roles' => $config['required_roles'] ?? [],
                'requires_auth' => (bool) ($config['requires_auth'] ?? false),
                'requires_sovereign_identity' => (bool) ($config['requires_sovereign_identity'] ?? false),
                'required_legal_entity' => (bool) ($config['required_legal_entity'] ?? false),
            ],
        );
    }

    /**
     * @param array<string, mixed> $config
     * @return array{available: bool, reason: string|null}
     */
    private function evaluate(array $config, ?User $user): array
    {
        if (! (bool) ($config['requires_auth'] ?? false)) {
            return ['available' => true, 'reason' => null];
        }

        if ($user === null) {
            return ['available' => false, 'reason' => 'Authentication required'];
        }

        if ((bool) ($config['requires_sovereign_identity'] ?? false) && ! $user->hasSovereignIdentity()) {
            return ['available' => false, 'reason' => 'Sovereign identity required'];
        }

        $requiredRoles = (array) ($config['required_roles'] ?? []);
        if ($requiredRoles !== [] && ! $user->hasAnyRole($requiredRoles)) {
            return ['available' => false, 'reason' => 'Required role: '.implode(' or ', $requiredRoles)];
        }

        if ((bool) ($config['required_legal_entity'] ?? false) && ! $this->hasActiveLegalEntity($user)) {
            return ['available' => false, 'reason' => 'Active legal entity required'];
        }

        return ['available' => true, 'reason' => null];
    }

    private function hasActiveLegalEntity(User $user): bool
    {
        return $user->legalEntities()->where('is_active', true)->exists()
            || $user->managedLegalEntities()->where('legal_entities.is_active', true)->exists();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function url(array $config): string
    {
        $route = (string) ($config['route'] ?? '');
        $params = (array) ($config['route_params'] ?? []);

        if ($route !== '' && Route::has($route)) {
            return route($route, $params);
        }

        return (string) ($config['url'] ?? '#');
    }
}
