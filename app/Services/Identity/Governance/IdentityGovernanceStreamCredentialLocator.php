<?php

namespace App\Services\Identity\Governance;

use App\Models\IdentityGovernanceStreamEvent;
use Spatie\LaravelPasskeys\Models\Passkey;

final class IdentityGovernanceStreamCredentialLocator
{
    public function findStreamIdByRawCredentialId(string $rawCredentialId): ?string
    {
        if ($rawCredentialId === '') {
            return null;
        }

        $encoded = Passkey::encodeCredentialId($rawCredentialId);

        $streamId = IdentityGovernanceStreamEvent::query()
            ->where('event_type', GovernanceEventTypes::CREDENTIAL_BOUND)
            ->where('payload->webauthn->credential_id', $encoded)
            ->orderByDesc('version')
            ->value('stream_id');

        if (! is_string($streamId) || $streamId === '') {
            return null;
        }

        $streamId = strtolower($streamId);
        $projection = IdentityCredentialReducer::fold(
            app(IdentityGovernanceStreamAppender::class)->loadEvents($streamId),
        );

        foreach ($projection->activeCredentials as $material) {
            $storedRaw = IdentityGovernanceWebAuthnCredentialSourceFactory::decodeStoredBinary($material->credentialId);

            if (hash_equals($storedRaw, $rawCredentialId)) {
                return $streamId;
            }
        }

        return null;
    }
}
