<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\IntentLedgerService;
use Spatie\LaravelPasskeys\Livewire\PasskeysComponent as BasePasskeysComponent;

class PasskeysComponent extends BasePasskeysComponent
{
    public function deletePasskey(int|string $passkeyId): void
    {
        $user = $this->currentUser();
        $passkey = $user->passkeys()->where('id', $passkeyId)->first();

        if ($passkey && $user instanceof User) {
            app(IntentLedgerService::class)->record(
                eventType: 'PASSKEY_REMOVE_INTENT',
                intentType: 'passkey.remove',
                entity: $passkey,
                payload: [
                    'credential_id_hash' => hash('sha256', (string) $passkey->credential_id),
                    'removed_at' => now()->toIso8601String(),
                ],
                request: request(),
                passkey: $passkey,
                user: $user,
                scope: 'identity.devices',
                resource: 'passkey:'.$passkey->id,
            );
        }

        parent::deletePasskey($passkeyId);
    }
}
