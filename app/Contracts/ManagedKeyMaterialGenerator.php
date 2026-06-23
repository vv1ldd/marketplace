<?php

namespace App\Contracts;

interface ManagedKeyMaterialGenerator
{
    public function protocol(): string;

    /**
     * @return array{
     *     address: string,
     *     secret: string,
     *     secret_format: string,
     *     key_reference: string,
     *     public_key_hex?: string|null
     * }
     */
    public function generate(): array;
}
