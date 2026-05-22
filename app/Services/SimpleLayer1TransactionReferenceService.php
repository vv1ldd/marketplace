<?php

namespace App\Services;

use App\Models\SovereignLedger;
use Illuminate\Database\Eloquent\Model;

class SimpleLayer1TransactionReferenceService
{
    public const PUBLIC_PREFIX = 'SL1';

    public function forModel(Model $entity): string
    {
        if ($entity instanceof SovereignLedger) {
            return $this->fromFingerprint($entity->fingerprint);
        }

        $fingerprint = SovereignLedger::query()
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->oldest('id')
            ->value('fingerprint');

        if ($fingerprint) {
            return $this->fromFingerprint($fingerprint);
        }

        return $this->fromFingerprint($this->fallbackFingerprint($entity));
    }

    public function fromFingerprint(?string $fingerprint): string
    {
        $fingerprint = strtoupper(preg_replace('/[^A-F0-9]/i', '', (string) $fingerprint));

        if (strlen($fingerprint) < 16) {
            $fingerprint = strtoupper(hash('sha256', $fingerprint ?: 'missing-sl1-fingerprint'));
        }

        return self::PUBLIC_PREFIX.'-'.substr($fingerprint, 0, 8).'-'.substr($fingerprint, 8, 8);
    }

    public function fingerprintPrefixFromReference(string $reference): ?string
    {
        if (! preg_match('/^(?:S?L1)-([A-F0-9]{8})-([A-F0-9]{8})$/i', trim($reference), $matches)) {
            return null;
        }

        return strtolower($matches[1].$matches[2]);
    }

    private function fallbackFingerprint(Model $entity): string
    {
        $payload = [
            'class' => $entity::class,
            'id' => $entity->getKey(),
            'uuid' => $entity->getAttribute('uuid'),
            'order_id' => $entity->getAttribute('order_id'),
            'sku' => $entity->getAttribute('sku'),
            'reservation_reference' => $entity->getAttribute('reservation_reference'),
        ];

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), config('app.key', 'sovereign-fallback'));
    }
}
