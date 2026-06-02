<?php

namespace App\Services\Mutation;

use Illuminate\Console\Command;
use Illuminate\Http\Request;

class MutationIdentityResolver
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function resolve(
        string $actor,
        string $action,
        string $entityType,
        string|int|null $entityId,
        ?string $idempotencyKey,
        array $context = [],
        ?string $mutationPath = null,
        ?string $providedMutationId = null,
    ): array {
        $entityId = (string) ($entityId ?? 'unknown');
        $idempotencyKey = trim((string) $idempotencyKey);
        $contextFingerprint = $this->fingerprint($context);
        $basis = [
            'actor' => $actor,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'idempotency_key' => $idempotencyKey,
            'context_fingerprint' => $contextFingerprint,
        ];

        return $basis + [
            'mutation_id' => $providedMutationId ?: hash('sha256', $this->canonicalJson($basis)),
            'mutation_path' => $mutationPath ?: $action,
            'context' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function fromCli(
        Command $command,
        string $action,
        string $entityType,
        string|int|null $entityId,
        ?string $idempotencyKey,
        array $context = [],
        ?string $mutationPath = null,
    ): array {
        $operator = (string) ($command->option('operator') ?: get_current_user() ?: 'cli');

        return $this->resolve(
            actor: 'cli:'.$operator,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            idempotencyKey: $idempotencyKey,
            context: ['command' => $command->getName()] + $context,
            mutationPath: $mutationPath,
            providedMutationId: (string) ($command->option('mutation-id') ?: ''),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function fromWebhook(
        Request $request,
        string $provider,
        string $eventId,
        string $action,
        string $entityType,
        string|int|null $entityId,
        array $context = [],
        ?string $mutationPath = null,
    ): array {
        return $this->resolve(
            actor: 'webhook:'.$provider,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            idempotencyKey: $eventId,
            context: [
                'provider' => $provider,
                'route' => $request->path(),
                'method' => $request->method(),
            ] + $context,
            mutationPath: $mutationPath,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function fromJob(
        string $job,
        string $action,
        string $entityType,
        string|int|null $entityId,
        ?string $idempotencyKey,
        array $payload = [],
        array $context = [],
        ?string $mutationPath = null,
    ): array {
        return $this->resolve(
            actor: 'job:'.$job,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            idempotencyKey: $idempotencyKey,
            context: ['job' => $job, 'payload_hash' => $this->fingerprint($payload)] + $context,
            mutationPath: $mutationPath,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function fingerprint(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function canonicalJson(array $value): string
    {
        $this->ksortRecursive($value);

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function ksortRecursive(array &$value): void
    {
        ksort($value);

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->ksortRecursive($item);
            }
        }
    }
}
