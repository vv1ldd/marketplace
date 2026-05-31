<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use SimpleLayer\Sl1e\AuthorizeRequest;
use SimpleLayer\Sl1e\IdentityProof;
use SimpleLayer\Sl1e\Intent;
use SimpleLayer\Sl1e\Sl1eClient;
use SimpleLayer\Sl1e\Sl1eConfig;
use SimpleLayer\Sl1e\VerificationContext;

class SimpleL1ProtocolClient
{
    private readonly string $identityUrl;
    private readonly string $gatewayUrl;

    public function __construct()
    {
        $this->identityUrl = rtrim((string) config('simple_l1.identity_provider_url'), '/');
        $this->gatewayUrl = rtrim((string) config('simple_l1.protocol_gateway_url'), '/');
    }

    /**
     * @param array<string, string|null> $intent
     */
    public function authorizationUrl(
        string $redirectUri,
        string $state,
        string $nonce,
        string $mode = 'login',
        string $scope = 'openid sl1e marketplace',
        array $intent = [],
        ?string $flow = null,
        ?string $identityHint = null,
        ?string $uiLocale = null,
    ): string {
        $url = $this->sl1e()->authorizationUrl(new AuthorizeRequest(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: $scope,
            responseMode: 'code',
            intent: new Intent(
                type: $intent['intent_type'] ?? null,
                title: $intent['intent_title'] ?? null,
                description: $intent['intent_description'] ?? null,
                cta: $intent['intent_cta'] ?? null,
                nonce: $intent['intent_nonce'] ?? null,
                resource: $intent['intent_resource'] ?? null,
            ),
        ));

        if ($flow !== null && $flow !== '') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator.'flow='.rawurlencode($flow);
        }

        if ($identityHint !== null && $identityHint !== '') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator.'identity_hint='.rawurlencode($identityHint);
        }

        if ($uiLocale !== null && $uiLocale !== '') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator.'ui_locale='.rawurlencode($uiLocale).'&alias_locale='.rawurlencode($uiLocale);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    public function introspectProof(string $proofToken): array
    {
        $response = $this->client($this->identityUrl)
            ->post((string) config('simple_l1.proof_introspection_path', '/api/sl1e/proofs/introspect'), [
                'proof_token' => $proofToken,
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Simple L1 proof could not be verified.');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code, string $clientId, string $redirectUri): array
    {
        return $this->sl1e()->exchangeAuthorizationCode($code, $redirectUri, $clientId);
    }

    /**
     * @param array<string, mixed> $proofResponse
     */
    public function validateProof(
        array $proofResponse,
        string $proofToken,
        string $clientId,
        string $redirectUri,
        string $state,
        string $nonce,
        string $mode,
    ): IdentityProof {
        return $this->sl1e()->validateProof($proofResponse, new VerificationContext(
            clientId: $clientId,
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
        ), $proofToken);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function decideCapability(string $proofToken, string $capability, string $scope, array $context = []): array
    {
        $response = $this->client($this->gatewayUrl)->post('/api/simple-l1/capabilities/decide', [
            'proof_token' => $proofToken,
            'capability' => $capability,
            'scope' => $scope,
            'context' => $context,
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Simple L1 capability decision failed.');
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitIntent(
        string $proofToken,
        string $capability,
        string $scope,
        array $payload,
        string $idempotencyKey,
    ): array {
        $response = $this->client($this->gatewayUrl)->post('/api/simple-l1/intents', [
            'proof_token' => $proofToken,
            'capability' => $capability,
            'scope' => $scope,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Simple L1 intent submission failed.');
        }

        return $response->json();
    }

    private function client(string $baseUrl): PendingRequest
    {
        $client = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout(10);

        if (! config('simple_l1.verify_tls', true)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function sl1e(): Sl1eClient
    {
        return new Sl1eClient(Sl1eConfig::fromArray([
            'identity_provider_url' => $this->identityUrl,
            'client_id' => config('simple_l1.client_id'),
            'client_name' => config('simple_l1.client_name', 'Meanly'),
            'ui_theme' => config('simple_l1.ui_theme', 'neobrutalism'),
            'verify_tls' => config('simple_l1.verify_tls', true),
        ]), new LaravelSl1eHttpClient());
    }
}
