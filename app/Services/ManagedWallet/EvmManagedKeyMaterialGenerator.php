<?php

namespace App\Services\ManagedWallet;

use App\Contracts\ManagedKeyMaterialGenerator;
use App\Support\EvmManagedKeyGenerator;

class EvmManagedKeyMaterialGenerator implements ManagedKeyMaterialGenerator
{
    public function __construct(
        private readonly EvmManagedKeyGenerator $keyGenerator,
    ) {}

    public function protocol(): string
    {
        return 'evm';
    }

    public function generate(): array
    {
        $material = $this->keyGenerator->generate();

        return [
            'address' => $material['address'],
            'secret' => $material['private_key_hex'],
            'secret_format' => 'evm_private_key_hex',
            'key_reference' => $material['key_reference'],
            'public_key_hex' => $material['public_key_hex'],
        ];
    }
}
