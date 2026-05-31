<?php

namespace SimpleLayer\Sl1e;

final readonly class AuthorizeRequest
{
    public function __construct(
        public string $redirectUri,
        public string $state,
        public string $nonce,
        public string $mode = 'login',
        public string $scope = 'openid sl1e',
        public string $responseMode = 'code',
        public ?Intent $intent = null,
    ) {
    }
}
