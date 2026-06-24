<?php

namespace App\Services;

use App\Support\SimpleL1IdentityHost;
use App\Support\StorefrontRegionalSl1e;
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
        $url = $this->sl1e()->authorizationUrl($this->authorizeRequest(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: $scope,
            intent: $intent,
        ));

        $url = $this->appendOptionalAuthorizeParams($url, $flow, $identityHint, $uiLocale);

        return $url;
    }

    /**
     * Build an authorization URL using the per-host Sl1e client config.
     * Maestrooo requests will get client_id=maestrooo.test and ui_theme=dark.
     *
     * @param array<string, string|null> $intent
     */
    public function authorizationUrlForHost(
        string $host,
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
        $url = $this->sl1eForHost($host)->authorizationUrl($this->authorizeRequest(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: $scope,
            intent: $intent,
        ));

        return $this->appendOptionalAuthorizeParams($url, $flow, $identityHint, $uiLocale);
    }

    /**
     * @param array<string, string|null> $intent
     */
    public function authorizationDeepLinkUrl(
        string $redirectUri,
        string $state,
        string $nonce,
        string $mode = 'login',
        string $scope = 'openid sl1e marketplace',
        array $intent = [],
        ?string $flow = null,
        ?string $identityHint = null,
        ?string $uiLocale = null,
    ): ?string {
        if (! config('simple_l1.prefer_native_deep_link', true)) {
            return null;
        }

        $url = $this->sl1e()->authorizationDeepLink($this->authorizeRequest(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: $scope,
            intent: $intent,
        ));

        return $this->appendOptionalAuthorizeParams($url, $flow, $identityHint, $uiLocale);
    }

    /**
     * Same as authorizationDeepLinkUrl() but uses the per-host Sl1e client.
     *
     * @param array<string, string|null> $intent
     */
    public function authorizationDeepLinkUrlForHost(
        string $host,
        string $redirectUri,
        string $state,
        string $nonce,
        string $mode = 'login',
        string $scope = 'openid sl1e marketplace',
        array $intent = [],
        ?string $flow = null,
        ?string $identityHint = null,
        ?string $uiLocale = null,
    ): ?string {
        if (! config('simple_l1.prefer_native_deep_link', true)) {
            return null;
        }

        $url = $this->sl1eForHost($host)->authorizationDeepLink($this->authorizeRequest(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: $scope,
            intent: $intent,
        ));

        return $this->appendOptionalAuthorizeParams($url, $flow, $identityHint, $uiLocale);
    }

    /**
     * @return array<string, mixed>
     */
    public function introspectProof(string $proofToken): array
    {
        $baseUrl = config('identity_governance.stream_authorize_enabled')
            ? rtrim((string) config('app.url'), '/')
            : $this->protocolApiBaseUrl();

        $response = $this->client($baseUrl)
            ->post((string) config('simple_l1.proof_introspection_path', '/api/sl1e/proofs/introspect'), [
                'proof_token' => $proofToken,
            ]);

        if (! $response->ok()) {
            $message = (string) data_get($response->json(), 'message', '');
            throw new \RuntimeException($message !== ''
                ? "Simple L1 proof could not be verified. {$message}"
                : 'Simple L1 proof could not be verified.');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code, string $clientId, string $redirectUri): array
    {
        return $this->postAuthorizationCodeExchange($code, $clientId, $redirectUri);
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

        if (! config('simple_l1.verify_tls', true) || str_starts_with(strtolower($baseUrl), 'http://')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Browser-facing authorize URLs use the public identity host. Server-side
     * protocol calls should reach the SL1 runtime directly when configured.
     */
    private function protocolApiBaseUrl(): string
    {
        $runtimeUrl = trim((string) config('simple_l1.runtime_url', ''));

        return $runtimeUrl !== '' ? rtrim($runtimeUrl, '/') : $this->identityUrl;
    }

    /**
     * @return array<string, mixed>
     */
    private function postAuthorizationCodeExchange(string $code, string $clientId, string $redirectUri): array
    {
        $response = $this->client($this->protocolApiBaseUrl())
            ->post('/api/sl1e/authorization-code/exchange', [
                'code' => $code,
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->ok()) {
            $message = (string) data_get($response->json(), 'message', '');
            throw new \RuntimeException($message !== ''
                ? "Simple L1 authorization code could not be exchanged. {$message}"
                : 'Simple L1 authorization code could not be exchanged.');
        }

        $payload = $response->json();
        if (! is_array($payload) || data_get($payload, 'active') !== true) {
            throw new \RuntimeException('Simple L1 authorization code could not be exchanged.');
        }

        return $payload;
    }

    private function sl1e(): Sl1eClient
    {
        return $this->sl1eForClient(
            clientId: config('simple_l1.client_id'),
            clientName: config('simple_l1.client_name', 'Meanly'),
            uiTheme: config('simple_l1.ui_theme', 'neobrutalism'),
            appHost: request()?->getHost(),
        );
    }

    public function sl1eForHost(string $host): Sl1eClient
    {
        // Per-host client configuration. Each entry maps one or more host
        // patterns to a specific OAuth client identity registered with the
        // Simple Layer identity provider.
        $hostClients = array_merge(
            (array) config('simple_l1.host_clients', []),
            [
                // Maestrooo uses the registered meanly.test client_id so the
                // SL1E identity provider accepts the request (maestrooo.test is
                // not a separately registered client). The client_name and
                // ui_theme are sent as display params and control what the
                // auth page looks like — Maestrooo branding, dark theme.
                'maestrooo.test'     => ['client_id' => config('simple_l1.client_id'), 'client_name' => 'Maestrooo', 'ui_theme' => 'dark'],
                'api.maestrooo.test' => ['client_id' => config('simple_l1.client_id'), 'client_name' => 'Maestrooo', 'ui_theme' => 'dark'],
                'maestrooo.one'      => ['client_id' => config('simple_l1.client_id'), 'client_name' => 'Maestrooo', 'ui_theme' => 'dark'],
                'api.maestrooo.one'  => ['client_id' => config('simple_l1.client_id'), 'client_name' => 'Maestrooo', 'ui_theme' => 'dark'],
            ],
        );

        $overrides = $hostClients[$host] ?? [];
        $regional = StorefrontRegionalSl1e::forHost($host);

        return $this->sl1eForClient(
            clientId: $overrides['client_id'] ?? $regional->clientId,
            clientName: $overrides['client_name'] ?? $regional->clientName,
            uiTheme: $overrides['ui_theme'] ?? config('simple_l1.ui_theme', 'neobrutalism'),
            appHost: $host,
        );
    }

    private function sl1eForClient(string $clientId, string $clientName, string $uiTheme, ?string $appHost = null): Sl1eClient
    {
        return new Sl1eClient(Sl1eConfig::fromArray([
            'identity_provider_url' => SimpleL1IdentityHost::browserProviderUrl($appHost),
            'client_id' => $clientId,
            'client_name' => $clientName,
            'ui_theme' => $uiTheme,
            'verify_tls' => config('simple_l1.verify_tls', true),
            'native_deep_link_scheme' => config('simple_l1.native_deep_link_scheme', 'simplel1'),
        ]), new LaravelSl1eHttpClient());
    }

    /**
     * @param array<string, string|null> $intent
     */
    private function authorizeRequest(
        string $redirectUri,
        string $state,
        string $nonce,
        string $mode,
        string $scope,
        array $intent,
    ): AuthorizeRequest {
        return new AuthorizeRequest(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: $scope,
            responseMode: (string) config('simple_l1.authorize_response_mode', 'query'),
            intent: new Intent(
                type: $intent['intent_type'] ?? null,
                title: $intent['intent_title'] ?? null,
                description: $intent['intent_description'] ?? null,
                cta: $intent['intent_cta'] ?? null,
                nonce: $intent['intent_nonce'] ?? null,
                resource: $intent['intent_resource'] ?? null,
            ),
        );
    }

    private function appendOptionalAuthorizeParams(
        string $url,
        ?string $flow,
        ?string $identityHint,
        ?string $uiLocale,
    ): string {
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
}
