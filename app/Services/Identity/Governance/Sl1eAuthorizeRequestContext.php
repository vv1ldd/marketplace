<?php

namespace App\Services\Identity\Governance;

use App\Support\StorefrontRegionalSl1e;
use App\Support\StorefrontRequestHost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * OAuth / SL1e authorize parameters carried through options, verify, and handoff.
 */
final class Sl1eAuthorizeRequestContext
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientName,
        public readonly string $redirectUri,
        public readonly string $state,
        public readonly string $nonce,
        public readonly string $mode,
        public readonly string $scope,
        public readonly ?string $handoffId = null,
        public readonly ?string $handoffToken = null,
        public readonly ?string $username = null,
        public readonly ?string $requestHost = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'clientId' => 'nullable|string|max:128',
            'client_id' => 'nullable|string|max:128',
            'clientName' => 'nullable|string|max:128',
            'client_name' => 'nullable|string|max:128',
            'redirectUri' => 'nullable|string|max:2048',
            'redirect_uri' => 'nullable|string|max:2048',
            'state' => 'nullable|string|max:256',
            'nonce' => 'nullable|string|max:256',
            'mode' => 'nullable|string|max:32',
            'scope' => 'nullable|string|max:256',
            'handoffId' => 'nullable|string|max:80',
            'handoff_id' => 'nullable|string|max:80',
            'handoffToken' => 'nullable|string|max:256',
            'handoff_token' => 'nullable|string|max:256',
            'username' => 'nullable|string|max:80',
            'usernameCandidate' => 'nullable|string|max:80',
            'username_candidate' => 'nullable|string|max:80',
            'requestHost' => 'nullable|string|max:128',
            'request_host' => 'nullable|string|max:128',
        ]);

        self::mergeQueryParameters($request, $data);
        self::hydrateFromConnectState($data);

        $requestHost = self::resolveRequestHost($request, $data);
        $regional = StorefrontRegionalSl1e::forHost($requestHost);

        $clientId = trim((string) (
            $data['clientId']
            ?? $data['client_id']
            ?? $regional->clientId
        ));
        $redirectUri = self::resolveRedirectUri($data, $requestHost, $regional);
        $state = trim((string) ($data['state'] ?? ''));
        $nonce = trim((string) ($data['nonce'] ?? ''));
        $mode = strtolower(trim((string) ($data['mode'] ?? 'login'))) === 'register' ? 'register' : 'login';

        abort_if($clientId === '', 422, 'clientId is required.');
        abort_if($redirectUri === '', 422, 'redirectUri is required.');
        abort_if($state === '', 422, 'state is required.');
        abort_if($nonce === '', 422, 'nonce is required.');

        if ($requestHost !== null) {
            $clientId = $regional->clientId;
            $regional->assertMatchesRedirectUri($redirectUri);
        }

        $username = \App\Models\User::normalizeUsername(
            $data['username']
            ?? $data['usernameCandidate']
            ?? $data['username_candidate']
            ?? null,
        );

        return new self(
            clientId: $clientId,
            clientName: trim((string) (
                $data['clientName']
                ?? $data['client_name']
                ?? $regional->clientName
            )),
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: trim((string) ($data['scope'] ?? 'openid sl1e marketplace')),
            handoffId: self::nullableTrim($data['handoffId'] ?? $data['handoff_id'] ?? null),
            handoffToken: self::nullableTrim($data['handoffToken'] ?? $data['handoff_token'] ?? null),
            username: $username,
            requestHost: $requestHost,
        );
    }

    public function rpId(): string
    {
        return IdentityGovernanceWebAuthnCredentialSourceFactory::rpIdForHost($this->requestHost);
    }

    public function storefrontOrigin(): string
    {
        if ($this->requestHost !== null && $this->requestHost !== '') {
            return 'https://'.$this->requestHost;
        }

        $redirectHost = parse_url($this->redirectUri, PHP_URL_HOST);
        $redirectScheme = parse_url($this->redirectUri, PHP_URL_SCHEME) ?: 'https';

        if (is_string($redirectHost) && $redirectHost !== '') {
            return rtrim($redirectScheme.'://'.$redirectHost, '/');
        }

        return rtrim((string) config('storefront.frontend_url', ''), '/');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function mergeQueryParameters(Request $request, array &$data): void
    {
        $keys = [
            'client_id', 'clientId', 'client_name', 'clientName',
            'redirect_uri', 'redirectUri', 'state', 'nonce', 'mode', 'scope',
            'handoff_id', 'handoffId', 'handoff_token', 'handoffToken',
            'request_host', 'requestHost',
        ];

        foreach ($keys as $key) {
            if (! empty($data[$key])) {
                continue;
            }

            $fromQuery = $request->query($key);
            if (is_string($fromQuery) && trim($fromQuery) !== '') {
                $data[$key] = $fromQuery;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function hydrateFromConnectState(array &$data): void
    {
        $cached = self::connectStateForData($data);
        if ($cached === []) {
            return;
        }

        foreach ([
            'client_id' => ['client_id', 'clientId'],
            'redirect_uri' => ['redirect_uri', 'redirectUri'],
            'nonce' => ['nonce'],
            'mode' => ['mode'],
            'scope' => ['scope'],
            'host' => ['request_host', 'requestHost'],
        ] as $cachedKey => $targets) {
            $cachedValue = trim((string) ($cached[$cachedKey] ?? ''));
            if ($cachedValue === '') {
                continue;
            }

            foreach ($targets as $target) {
                if (empty($data[$target])) {
                    $data[$target] = $cachedValue;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveRequestHost(Request $request, array $data): ?string
    {
        $explicit = StorefrontRequestHost::normalizeHost(
            (string) ($data['requestHost'] ?? $data['request_host'] ?? ''),
        );

        return StorefrontRequestHost::resolve($request, $explicit);
    }

    private static function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveRedirectUri(
        array $data,
        ?string $requestHost,
        StorefrontRegionalSl1e $regional,
    ): string {
        $redirectUri = trim((string) ($data['redirectUri'] ?? $data['redirect_uri'] ?? ''));
        if ($redirectUri !== '') {
            return $redirectUri;
        }

        $cached = self::connectStateForData($data);
        $cachedRedirect = trim((string) ($cached['redirect_uri'] ?? ''));
        if ($cachedRedirect !== '') {
            return $cachedRedirect;
        }

        if ($requestHost === null) {
            return '';
        }

        $redirectUri = 'https://'.$regional->storefrontHost.route('meanly.simple_l1.callback', [], false);
        if ((bool) ($cached['popup'] ?? false)) {
            $redirectUri .= (str_contains($redirectUri, '?') ? '&' : '?').'popup=1';
        }

        return $redirectUri;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function connectStateForData(array $data): array
    {
        $state = trim((string) ($data['state'] ?? ''));
        if ($state === '') {
            return [];
        }

        $cached = Cache::get('simple_l1:connect_state:'.hash('sha256', $state));

        return is_array($cached) ? $cached : [];
    }
}
