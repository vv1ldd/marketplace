<?php

namespace App\Services\Identity\Governance;

use Illuminate\Http\Request;

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
            'redirectUri' => 'nullable|string|max:512',
            'redirect_uri' => 'nullable|string|max:512',
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
        ]);

        $clientId = trim((string) (
            $data['clientId']
            ?? $data['client_id']
            ?? config('simple_l1.client_id', 'meanly.one')
        ));
        $redirectUri = trim((string) ($data['redirectUri'] ?? $data['redirect_uri'] ?? ''));
        $state = trim((string) ($data['state'] ?? ''));
        $nonce = trim((string) ($data['nonce'] ?? ''));
        $mode = strtolower(trim((string) ($data['mode'] ?? 'login'))) === 'register' ? 'register' : 'login';

        abort_if($clientId === '', 422, 'clientId is required.');
        abort_if($redirectUri === '', 422, 'redirectUri is required.');
        abort_if($state === '', 422, 'state is required.');
        abort_if($nonce === '', 422, 'nonce is required.');

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
                ?? config('simple_l1.client_name', 'Meanly')
            )),
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            scope: trim((string) ($data['scope'] ?? 'openid sl1e marketplace')),
            handoffId: self::nullableTrim($data['handoffId'] ?? $data['handoff_id'] ?? null),
            handoffToken: self::nullableTrim($data['handoffToken'] ?? $data['handoff_token'] ?? null),
            username: $username,
            requestHost: self::browserHostFromRequest($request),
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

    private static function browserHostFromRequest(Request $request): ?string
    {
        $incoming = trim((string) $request->header('X-Forwarded-Host', ''));
        $host = strtolower((string) explode(':', $incoming)[0]);

        if ($host !== '' && ! str_starts_with($host, 'api.')) {
            return $host;
        }

        $storefrontHost = parse_url((string) config('storefront.frontend_url', ''), PHP_URL_HOST);

        return is_string($storefrontHost) && $storefrontHost !== '' ? strtolower($storefrontHost) : null;
    }

    private static function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
