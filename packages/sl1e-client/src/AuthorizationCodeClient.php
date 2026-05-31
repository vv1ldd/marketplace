<?php

namespace SimpleLayer\Sl1e;

use SimpleLayer\Sl1e\Contracts\HttpClientInterface;
use SimpleLayer\Sl1e\Exception\Sl1eTransportException;

final readonly class AuthorizationCodeClient
{
    public function __construct(
        private Sl1eConfig $config,
        private ?HttpClientInterface $httpClient = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function exchange(string $code, string $redirectUri, ?string $clientId = null): array
    {
        if (trim($code) === '') {
            throw new Sl1eTransportException('SL1E authorization code is required.');
        }

        $clientId ??= $this->config->clientId;
        if ($clientId === '' || $redirectUri === '') {
            throw new Sl1eTransportException('SL1E client_id and redirect_uri are required for code exchange.');
        }

        $response = ($this->httpClient ?? new CurlHttpClient())->postJson(
            $this->config->providerUrl($this->config->codeExchangePath),
            [
                'code' => $code,
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
            ],
            $this->config->verifyTls,
        );

        if (! $response->ok()) {
            throw new Sl1eTransportException('SL1E authorization code could not be exchanged.');
        }

        return $response->json;
    }
}
