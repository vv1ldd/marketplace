<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\SovereignLedger;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\LaravelPasskeys\Models\Passkey;

class IntentLedgerService
{
    public function record(
        string $eventType,
        string $intentType,
        ?Model $entity,
        array $payload = [],
        ?Request $request = null,
        ?Passkey $passkey = null,
        ?User $user = null,
        ?string $scope = null,
        ?string $resource = null,
        ?\App\Models\Shop $shop = null,
        ?\App\Models\LegalEntity $legalEntity = null,
        ?string $triggerSource = null,
    ): SovereignLedger {
        $request ??= request();
        $user ??= $this->userFromEntity($entity);
        $identity = $user ? $this->identity($user, $passkey) : ['entity_l1_address' => null, 'key_l1_address' => null];

        return app(LedgerService::class)->record(
            shop: $shop,
            eventType: $eventType,
            entity: $entity,
            payload: array_filter([
                'protocol' => 'simple-l1',
                'intent_type' => $intentType,
                'scope' => $scope,
                'resource' => $resource,
                'entity_l1_address' => $identity['entity_l1_address'],
                'key_l1_address' => $identity['key_l1_address'],
                'passkey_id' => $passkey?->id,
                'session_id_hash' => $request->hasSession() ? hash('sha256', (string) $request->session()->getId()) : null,
                'ip_hash' => hash('sha256', (string) $request->ip()),
                'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                'recorded_at' => now()->toIso8601String(),
            ] + $payload, fn ($value) => $value !== null),
            legalEntity: $legalEntity,
            triggerSource: $triggerSource ?? $this->triggerSource($user, $passkey),
        );
    }

    public function recordForOrder(
        Order $order,
        string $eventType,
        string $intentType,
        array $payload = [],
        ?Request $request = null,
        ?Passkey $passkey = null,
        ?User $user = null,
        ?string $scope = 'order.safe',
        ?string $resource = null,
    ): SovereignLedger {
        $user ??= $request?->user();

        return $this->record(
            eventType: $eventType,
            intentType: $intentType,
            entity: $order,
            payload: [
                'order_id' => $order->id,
                'order_uuid_hash' => hash('sha256', (string) $order->uuid),
                'order_reference_hash' => hash('sha256', (string) $order->order_id),
            ] + $payload,
            request: $request,
            passkey: $passkey,
            user: $user instanceof User ? $user : null,
            scope: $scope,
            resource: $resource ?? 'order:'.$order->id,
            shop: $order->shop,
            legalEntity: $order->shop?->legalEntity,
        );
    }

    /**
     * @return array{entity_l1_address: string|null, key_l1_address: string|null}
     */
    public function identity(User $user, ?Passkey $passkey = null): array
    {
        $meta = $user->meta ?? [];
        $entityAddress = data_get($meta, 'entity_l1_address', data_get($meta, 'l1_address'));
        $keyAddress = data_get($meta, 'key_l1_address');

        if ((! is_string($keyAddress) || $keyAddress === '') && $passkey) {
            try {
                $keyAddress = app(L1IdentityService::class)->keyAddressFromPublicKey($passkey->data->credentialPublicKey ?? null);
            } catch (\Throwable) {
                $keyAddress = null;
            }
        }

        return [
            'entity_l1_address' => is_string($entityAddress) && $entityAddress !== '' ? strtolower($entityAddress) : null,
            'key_l1_address' => is_string($keyAddress) && $keyAddress !== '' ? strtolower($keyAddress) : null,
        ];
    }

    private function userFromEntity(?Model $entity): ?User
    {
        if ($entity instanceof User) {
            return $entity;
        }

        return null;
    }

    private function triggerSource(?User $user, ?Passkey $passkey): string
    {
        if ($passkey) {
            return 'DID:PASSKEY:#'.$passkey->id;
        }

        if ($user) {
            return 'DID:USER:#'.$user->id;
        }

        return 'DID:SYS | GUEST';
    }
}
