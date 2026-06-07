<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\SimpleL1IdentityKey;
use App\Services\StorefrontTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontPartnerRegistrationController extends Controller
{
    public function state(Request $request, StorefrontTokenService $tokens): JsonResponse
    {
        $session = $tokens->resolve($request->bearerToken());
        $identity = in_array('storefront:partner-registration', data_get($session, 'scopes', []), true)
            ? (array) data_get($session, 'identity', [])
            : [];
        $hasIdentity = filled(data_get($identity, 'entity_l1_address'));
        $existingApplication = $hasIdentity ? $this->existingApplicationForIdentity($identity) : null;
        $nextAction = $this->nextAction($hasIdentity, $existingApplication);

        return response()->json([
            'contract' => [
                'name' => 'storefront-partner-registration-state',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'state' => [
                'type' => 'storefront_partner_registration_state',
                'registration_step_status' => $hasIdentity ? 'identity_verified' : 'identity_required',
                'allowed_actions' => $hasIdentity
                    ? ($existingApplication ? ['VIEW', 'VIEW_ONBOARDING_STATUS'] : ['VIEW', 'SUBMIT_BUSINESS_PROFILE'])
                    : ['VIEW', 'CONNECT_SIMPLE_L1'],
                'blocked_actions' => $hasIdentity ? [] : ['SUBMIT_BUSINESS_PROFILE'],
                'next_action' => $nextAction,
                'blocking_reason' => $hasIdentity ? null : 'simple_l1_identity_required',
                'existing_application' => $existingApplication,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $identity
     * @return array<string, mixed>|null
     */
    private function existingApplicationForIdentity(array $identity): ?array
    {
        $entityAddress = strtolower((string) data_get($identity, 'entity_l1_address'));
        $keyAddress = strtolower((string) data_get($identity, 'key_l1_address'));

        if ($entityAddress === '' && $keyAddress === '') {
            return null;
        }

        $identityKey = $keyAddress !== ''
            ? SimpleL1IdentityKey::query()->where('key_l1_address', $keyAddress)->latest('last_used_at')->first()
            : null;
        $identityKey ??= $entityAddress !== ''
            ? SimpleL1IdentityKey::query()->where('entity_l1_address', $entityAddress)->latest('last_used_at')->first()
            : null;
        $user = $identityKey?->user;

        if (!$user && $entityAddress !== '') {
            $user = \App\Models\User::query()
                ->where('meta->entity_l1_address', $entityAddress)
                ->orWhere('meta->l1_address', $entityAddress)
                ->first();
        }

        if (!$user) {
            return null;
        }

        $entity = $user->managedLegalEntities()->latest()->first()
            ?? $user->legalEntities()->latest()->first();

        if (!$entity) {
            return null;
        }

        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'status' => $entity->status,
            'is_active' => (bool) $entity->is_active,
            'next_href' => ($entity->status === 'active' && $entity->is_active) ? '/partner' : '/business/register/onboarding',
        ];
    }

    /**
     * @param array<string, mixed>|null $existingApplication
     */
    private function nextAction(bool $hasIdentity, ?array $existingApplication): string
    {
        if (!$hasIdentity) {
            return 'CONNECT_SIMPLE_L1';
        }

        if (!$existingApplication) {
            return 'SUBMIT_BUSINESS_PROFILE';
        }

        if (($existingApplication['status'] ?? null) === 'active' && ($existingApplication['is_active'] ?? false)) {
            return 'OPEN_PARTNER_WORKSPACE';
        }

        return 'VIEW_ONBOARDING_STATUS';
    }
}
