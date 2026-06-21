<?php

namespace App\Contracts;

use App\Models\User;
use App\Support\SettlementNetwork;

interface SettlementNetworkAdapter
{
    public function network(): SettlementNetwork;

    /**
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    public function walletPreview(array $identity, ?User $user = null): array;

    public function traceNetworkLabel(): string;
}
