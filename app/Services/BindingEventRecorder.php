<?php

namespace App\Services;

use App\Models\BindingChallenge;
use App\Models\BindingEvent;
use App\Models\IdentityBinding;
use Illuminate\Database\Eloquent\Collection;

class BindingEventRecorder
{
    /**
     * @param array<string, mixed> $context
     */
    public function recordWalletBound(IdentityBinding $binding, array $context = []): BindingEvent
    {
        return $this->record(
            vaultId: (string) $binding->vault_id,
            identityBindingId: (int) $binding->id,
            bindingType: (string) $binding->binding_type,
            bindingKey: (string) $binding->binding_key,
            bindingValueNormalized: (string) $binding->binding_value_normalized,
            eventType: BindingEvent::TYPE_WALLET_BOUND,
            verificationMethod: $binding->verification_method,
            payload: $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function recordWalletBindingFailed(
        BindingChallenge $challenge,
        string $code,
        string $message,
        array $context = [],
    ): BindingEvent {
        return $this->record(
            vaultId: (string) $challenge->vault_id,
            identityBindingId: null,
            bindingType: (string) $challenge->binding_type,
            bindingKey: (string) $challenge->binding_key,
            bindingValueNormalized: (string) $challenge->binding_value_normalized,
            eventType: BindingEvent::TYPE_WALLET_BINDING_FAILED,
            verificationMethod: $challenge->verification_method,
            payload: array_merge($context, [
                'challenge_id' => $challenge->id,
                'nonce' => $challenge->nonce,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
                'verification_attempt_count' => (int) $challenge->verification_attempt_count + 1,
            ]),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function recordWalletRevoked(IdentityBinding $binding, array $context = []): BindingEvent
    {
        return $this->record(
            vaultId: (string) $binding->vault_id,
            identityBindingId: (int) $binding->id,
            bindingType: (string) $binding->binding_type,
            bindingKey: (string) $binding->binding_key,
            bindingValueNormalized: (string) $binding->binding_value_normalized,
            eventType: BindingEvent::TYPE_WALLET_REVOKED,
            verificationMethod: $binding->verification_method,
            payload: $context,
        );
    }

    /**
     * @return Collection<int, BindingEvent>
     */
    public function listForVault(string $vaultId, ?string $eventType = null, int $limit = 50): Collection
    {
        $query = BindingEvent::query()
            ->where('vault_id', $vaultId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEvent(BindingEvent $event): array
    {
        return [
            'id' => $event->id,
            'vault_id' => $event->vault_id,
            'identity_binding_id' => $event->identity_binding_id,
            'binding_type' => $event->binding_type,
            'binding_key' => $event->binding_key,
            'binding_value' => $event->binding_value_normalized,
            'event_type' => $event->event_type,
            'verification_method' => $event->verification_method,
            'payload' => $event->payload ?? [],
            'occurred_at' => $event->occurred_at?->toJSON(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function record(
        string $vaultId,
        ?int $identityBindingId,
        string $bindingType,
        string $bindingKey,
        ?string $bindingValueNormalized,
        string $eventType,
        ?string $verificationMethod,
        array $payload = [],
    ): BindingEvent {
        return BindingEvent::query()->create([
            'vault_id' => $vaultId,
            'identity_binding_id' => $identityBindingId,
            'binding_type' => $bindingType,
            'binding_key' => $bindingKey,
            'binding_value_normalized' => $bindingValueNormalized,
            'event_type' => $eventType,
            'verification_method' => $verificationMethod,
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => now(),
        ]);
    }
}
