<?php

namespace SimpleLayer\Sl1e;

use SimpleLayer\Sl1e\Contracts\HttpClientInterface;

final readonly class Sl1eClient
{
    public function __construct(
        private Sl1eConfig $config,
        private ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function authorizationUrl(AuthorizeRequest $request): string
    {
        return (new AuthorizationUrlBuilder($this->config))->build($request);
    }

    public function authorizationDeepLink(AuthorizeRequest $request): string
    {
        return (new AuthorizationDeepLinkBuilder($this->config))->build($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri, ?string $clientId = null): array
    {
        return (new AuthorizationCodeClient($this->config, $this->httpClient))->exchange($code, $redirectUri, $clientId);
    }

    /**
     * @param array<string, mixed> $proofResponse
     */
    public function validateProof(array $proofResponse, VerificationContext $context, string $proofToken = ''): IdentityProof
    {
        return (new ProofValidator())->validate($proofResponse, $context, $proofToken);
    }
}
