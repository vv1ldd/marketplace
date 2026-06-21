<?php

namespace App\Contracts;

use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;

interface SettlementAdapter
{
    public function adapterKey(): string;

    public function mode(): string;

    public function isEnabled(): bool;

    public function allowsWrite(): bool;

    /**
     * @return array{valid: bool, binding?: array<string, mixed>, reason?: string}
     */
    public function verifyAttachment(VaultIdentity $vault, IdentityBinding $binding): array;

    /**
     * @return array<string, mixed>
     */
    public function observeBalance(VaultIdentity $vault, IdentityBinding $binding): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listEvents(VaultIdentity $vault, int $limit = 50): array;

    /**
     * @return array{status: string, healthy: bool, adapter: string, mode: string, failures: list<string>, checks: array<string, mixed>}
     */
    public function healthCheck(): array;

    /**
     * Materialize a verified settlement proof for an external rail event.
     *
     * @param array<string, mixed> $input
     */
    public function verifyProof(VaultIdentity $vault, array $input): VaultSettlementProof;
}
