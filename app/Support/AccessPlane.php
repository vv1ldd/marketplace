<?php

namespace App\Support;

class AccessPlane
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $url,
        public readonly string $authority,
        public readonly string $description,
        public readonly bool $available,
        public readonly ?string $reason,
        public readonly array $metadata = [],
    ) {}
}
