<?php

namespace SimpleLayer\Sl1e;

final readonly class VerificationContext
{
    public function __construct(
        public string $clientId,
        public string $redirectUri,
        public string $state,
        public string $nonce,
        public string $mode = 'login',
        public int $clockSkewSeconds = 60,
    ) {
    }
}
