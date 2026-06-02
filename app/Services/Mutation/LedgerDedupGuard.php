<?php

namespace App\Services\Mutation;

class LedgerDedupGuard
{
    public function __construct(
        private readonly MutationDedupGuard $dedup,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{allowed:bool,duplicate:bool,mode:string,guard_key:string,mutation_id:string}
     */
    public function check(string $mutationId, string $eventType, array $metadata = []): array
    {
        $identity = MutationContext::all() + [
            'mutation_id' => $mutationId,
            'action' => 'ledger.'.$eventType,
        ];

        return $this->dedup->check(
            identity: $identity,
            mutationPath: 'ledger.'.$eventType,
            mode: (string) config('mutation.ledger_guard_mode', 'shadow'),
            guardKey: 'ledger:'.$eventType.':'.$mutationId,
            metadata: $metadata,
        );
    }
}
