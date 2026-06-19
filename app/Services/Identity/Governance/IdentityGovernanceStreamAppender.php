<?php

namespace App\Services\Identity\Governance;

use App\Models\IdentityGovernanceStreamEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Phase A append boundary: optimistic concurrency + event id idempotency.
 *
 * append(stream_id, expected_version, event_id, event)
 */
final class IdentityGovernanceStreamAppender
{
    public function headVersion(string $streamId): int
    {
        $head = IdentityGovernanceStreamEvent::query()
            ->where('stream_id', $streamId)
            ->max('version');

        return $head === null ? 0 : (int) $head;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(
        string $streamId,
        int $expectedVersion,
        string $eventId,
        string $eventType,
        array $payload = [],
    ): IdentityGovernanceStreamAppendResult {
        IdentityGovernanceStreamEventId::assertValid($eventId);
        $payload = GovernanceEventPayloadNormalizer::forAppend($eventType, $payload);

        return DB::transaction(function () use ($streamId, $expectedVersion, $eventId, $eventType, $payload): IdentityGovernanceStreamAppendResult {
            $existing = IdentityGovernanceStreamEvent::query()
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $this->assertIdempotentReplay($existing, $streamId, $eventType, $payload);

                return new IdentityGovernanceStreamAppendResult(
                    streamId: $existing->stream_id,
                    version: (int) $existing->version,
                    eventId: $existing->event_id,
                    eventType: $existing->event_type,
                    idempotentReplay: true,
                );
            }

            $head = IdentityGovernanceStreamEvent::query()
                ->where('stream_id', $streamId)
                ->lockForUpdate()
                ->max('version');

            $actualHead = $head === null ? 0 : (int) $head;

            if ($expectedVersion !== $actualHead) {
                throw new IdentityGovernanceStreamConcurrencyException(
                    streamId: $streamId,
                    expectedVersion: $expectedVersion,
                    actualVersion: $actualHead,
                );
            }

            $nextVersion = $actualHead + 1;
            $event = new GovernanceEvent(
                type: $eventType,
                entity: $streamId,
                sequence: $nextVersion,
                payload: $payload,
            );

            IdentityGovernanceStreamAppendRules::validate(
                $actualHead === 0 ? null : $actualHead,
                $event,
            );

            try {
                $row = IdentityGovernanceStreamEvent::query()->create([
                    'stream_id' => $streamId,
                    'version' => $nextVersion,
                    'event_id' => $eventId,
                    'event_type' => GovernanceEventTypes::normalize($eventType),
                    'payload' => $payload,
                    'created_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueConstraintViolation($exception)) {
                    $raced = IdentityGovernanceStreamEvent::query()->where('event_id', $eventId)->first();
                    if ($raced !== null) {
                        $this->assertIdempotentReplay($raced, $streamId, $eventType, $payload);

                        return new IdentityGovernanceStreamAppendResult(
                            streamId: $raced->stream_id,
                            version: (int) $raced->version,
                            eventId: $raced->event_id,
                            eventType: $raced->event_type,
                            idempotentReplay: true,
                        );
                    }

                    throw new IdentityGovernanceStreamConcurrencyException(
                        streamId: $streamId,
                        expectedVersion: $expectedVersion,
                        actualVersion: $this->headVersion($streamId),
                    );
                }

                throw $exception;
            }

            return new IdentityGovernanceStreamAppendResult(
                streamId: $row->stream_id,
                version: (int) $row->version,
                eventId: $row->event_id,
                eventType: $row->event_type,
                idempotentReplay: false,
            );
        });
    }

    /**
     * @return list<GovernanceEvent>
     */
    public function loadEvents(string $streamId): array
    {
        return IdentityGovernanceStreamEvent::query()
            ->where('stream_id', $streamId)
            ->orderBy('version')
            ->get()
            ->map(static fn (IdentityGovernanceStreamEvent $row): GovernanceEvent => new GovernanceEvent(
                type: $row->event_type,
                entity: $row->stream_id,
                sequence: (int) $row->version,
                payload: (array) $row->payload,
            ))
            ->all();
    }

    /**
     * @return list<string>
     */
    public function listStreamIds(): array
    {
        return IdentityGovernanceStreamEvent::query()
            ->distinct()
            ->orderBy('stream_id')
            ->pluck('stream_id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertIdempotentReplay(
        IdentityGovernanceStreamEvent $existing,
        string $streamId,
        string $eventType,
        array $payload,
    ): void {
        $normalizedType = GovernanceEventTypes::normalize($eventType);

        if (
            $existing->stream_id !== $streamId
            || GovernanceEventTypes::normalize($existing->event_type) !== $normalizedType
            || $existing->payload !== $payload
        ) {
            throw new IdentityGovernanceStreamIdempotencyConflictException($existing->event_id);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505', '19'], true);
    }
}
