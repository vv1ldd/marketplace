<?php

namespace App\Services\ManagedWallet;

use App\Contracts\ManagedKeyMaterialGenerator;
use Illuminate\Validation\ValidationException;

class ManagedKeyMaterialGeneratorRegistry
{
    /**
     * @param  iterable<ManagedKeyMaterialGenerator>  $generators
     */
    public function __construct(
        private readonly iterable $generators,
    ) {}

    public function forProtocol(string $protocol): ManagedKeyMaterialGenerator
    {
        $protocol = strtolower(trim($protocol));

        foreach ($this->generators as $generator) {
            if ($generator->protocol() === $protocol) {
                return $generator;
            }
        }

        throw ValidationException::withMessages([
            'binding_key' => 'Managed wallet provisioning is not available for this network protocol.',
        ]);
    }
}
