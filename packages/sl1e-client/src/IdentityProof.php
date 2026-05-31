<?php

namespace SimpleLayer\Sl1e;

final readonly class IdentityProof
{
    /**
     * @param array<string, mixed> $proof
     */
    public function __construct(
        public string $entityAddress,
        public ?string $keyAddress,
        public string $proofToken,
        public string $proofTokenHash,
        public string $mode,
        public array $proof,
    ) {
    }
}
