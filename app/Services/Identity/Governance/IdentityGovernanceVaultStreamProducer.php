<?php

namespace App\Services\Identity\Governance;

use Illuminate\Support\Str;

/**
 * First real producer: vault creation event sequence on the governance stream.
 */
final class IdentityGovernanceVaultStreamProducer
{
    public function __construct(
        private readonly IdentityGovernanceStreamWriter $writer,
        private readonly IdentityGovernanceStreamAppender $appender,
    ) {}

    /**
     * @param  array<string, mixed>  $credentialPayload  factor_id, class, type, purpose, metadata
     */
    public function recordVaultCreation(
        string $streamId,
        string $creationId,
        string $username,
        array $credentialPayload,
    ): IdentityGovernanceDualProjection {
        $streamId = strtolower($streamId);

        if ($this->appender->headVersion($streamId) === 0) {
            $this->writer->append(
                streamId: $streamId,
                expectedVersion: 0,
                eventId: $this->eventId($creationId, 'identity.created'),
                eventType: GovernanceEventTypes::IDENTITY_CREATED,
            );
        }

        $projection = $this->writer->read($streamId);

        if ($projection->registry->username === null && $username !== '') {
            $this->writer->append(
                streamId: $streamId,
                expectedVersion: $this->appender->headVersion($streamId),
                eventId: $this->eventId($creationId, 'identity.username_assigned'),
                eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
                payload: ['username' => $username],
            );
        }

        $projection = $this->writer->read($streamId);
        $factorId = (string) ($credentialPayload['factor_id'] ?? '');

        if ($factorId !== '' && ! $this->hasBinding($projection, $factorId)) {
            $this->writer->append(
                streamId: $streamId,
                expectedVersion: $this->appender->headVersion($streamId),
                eventId: $this->eventId($creationId, 'credential.bound'),
                eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
                payload: $credentialPayload,
            );
        }

        return $this->writer->read($streamId);
    }

    public static function deterministicFactorId(string $kind, string $reference): string
    {
        $hash = hash('sha256', $kind.':'.$reference);

        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            hexdec(substr($hash, 0, 8)),
            hexdec(substr($hash, 8, 4)),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x4000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            hexdec(substr($hash, 20, 12)),
        );
    }

    private function eventId(string $creationId, string $suffix): string
    {
        return Str::lower($creationId.':'.$suffix);
    }

    private function hasBinding(IdentityGovernanceDualProjection $projection, string $factorId): bool
    {
        foreach ($projection->registry->bindings as $binding) {
            if (($binding['factor_id'] ?? null) === $factorId) {
                return true;
            }
        }

        return false;
    }
}
