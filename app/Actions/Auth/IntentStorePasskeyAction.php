<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\IntentLedgerService;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class IntentStorePasskeyAction extends StorePasskeyAction
{
    public function execute(
        Authenticatable $authenticatable,
        string $passkeyJson,
        string $passkeyOptionsJson,
        string $hostName,
        array $additionalProperties = [],
    ): Passkey {
        $passkey = parent::execute($authenticatable, $passkeyJson, $passkeyOptionsJson, $hostName, $additionalProperties);

        if ($authenticatable instanceof User) {
            $challenge = (string) data_get(json_decode($passkeyOptionsJson, true), 'challenge', '');

            app(IntentLedgerService::class)->record(
                eventType: 'PASSKEY_ADD_INTENT',
                intentType: 'passkey.add',
                entity: $passkey,
                payload: [
                    'passkey_name_hash' => hash('sha256', (string) ($additionalProperties['name'] ?? $passkey->name)),
                    'credential_id_hash' => hash('sha256', (string) $passkey->credential_id),
                    'challenge_hash' => $challenge !== '' ? hash('sha256', $challenge) : null,
                    'host_hash' => hash('sha256', $hostName),
                    'added_at' => now()->toIso8601String(),
                ],
                request: request(),
                passkey: $passkey,
                user: $authenticatable,
                scope: 'identity.devices',
                resource: 'passkey:'.$passkey->id,
            );
        }

        return $passkey;
    }
}
