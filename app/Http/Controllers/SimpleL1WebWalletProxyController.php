<?php

namespace App\Http\Controllers;

use App\Support\SimpleL1IdentityHost;
use App\Support\StorefrontSl1Theme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SimpleL1WebWalletProxyController extends Controller
{
    public function authorize(Request $request): Response
    {
        return $this->proxy($request, 'authorize');
    }

    public function identity(Request $request): Response
    {
        return $this->proxy($request, 'identity');
    }

    public function wallet(Request $request): Response
    {
        return $this->proxy($request, 'wallet');
    }

    public function manifest(Request $request): Response
    {
        return $this->proxy($request, 'manifest.webmanifest');
    }

    public function identityIcon(Request $request): Response
    {
        return $this->proxy($request, 'identity-icon.svg');
    }

    public function deviceHandoff(Request $request, string $handoffId): Response
    {
        return $this->proxy($request, 'device-handoff/'.$handoffId);
    }

    public function handoffShortLink(string $handoffId): RedirectResponse
    {
        $target = Cache::get($this->handoffQrCacheKey($handoffId));

        if (! is_string($target) || $target === '') {
            abort(410, 'Handoff link expired.');
        }

        return redirect()->away($target);
    }

    public function devicePairing(Request $request, string $pairingId): Response
    {
        return $this->proxy($request, 'device-pairing/'.$pairingId);
    }

    public function sl1eApi(Request $request, string $path = ''): Response
    {
        return $this->proxy($request, 'api/sl1e/'.ltrim($path, '/'));
    }

    private function proxy(Request $request, string $path): Response
    {
        $runtimeUrl = rtrim((string) config('simple_l1.runtime_url', 'http://localhost:3000'), '/');
        $target = $runtimeUrl.'/'.ltrim($path, '/');
        $queryParams = $request->query();

        if (in_array($path, ['authorize', 'identity', 'wallet'], true)) {
            $queryParams = StorefrontSl1Theme::augmentAuthorizeQuery($queryParams, $request);
        }

        $query = http_build_query($queryParams);
        if ($query !== '') {
            $target .= '?'.$query;
        }

        $headers = collect([
            'Accept' => $request->header('Accept'),
            'Content-Type' => $request->header('Content-Type'),
            'Host' => $request->getHost(),
            'X-Forwarded-Host' => $this->forwardedBrowserHost($request),
            'X-Forwarded-Proto' => $request->header('X-Forwarded-Proto') ?: $request->getScheme(),
            'User-Agent' => $request->header('User-Agent'),
        ])->filter()->all();

        $requestBody = $this->maybeRewriteRegisterOptionsRequest($request, $path)
            ?? $request->getContent();
        $originalRequestBody = $request->getContent();

        $upstream = Http::withHeaders($headers)
            ->withOptions(['http_errors' => false])
            ->send($request->method(), $target, [
                'body' => $requestBody,
            ]);

        $body = $upstream->body();
        $contentType = $upstream->header('Content-Type') ?: 'text/plain; charset=utf-8';
        $body = $this->maybeRewriteHandoffResponse($body, $path, $request, $contentType);
        $body = $this->maybeRewriteRegisterOptionsResponse($body, $path, $originalRequestBody, $requestBody, $contentType);
        $body = $this->maybeInjectEmbeddedAuthorizeStyles($body, $contentType, $queryParams);

        return response($body, $upstream->status())
            ->header('Content-Type', $contentType);
    }

    /**
     * @param  array<string, mixed>  $queryParams
     */
    private function maybeInjectEmbeddedAuthorizeStyles(string $body, string $contentType, array $queryParams): string
    {
        if (($queryParams['iframe'] ?? '') !== '1') {
            return $body;
        }

        if (! str_contains(strtolower($contentType), 'text/html')) {
            return $body;
        }

        $style = StorefrontSl1Theme::embeddedAuthorizeStyleTag();
        if (str_contains($body, '</head>')) {
            return str_replace('</head>', $style.'</head>', $body);
        }

        return $style.$body;
    }

    private function forwardedBrowserHost(Request $request): string
    {
        $incoming = trim((string) $request->header('X-Forwarded-Host', ''));
        $incomingHost = strtolower((string) explode(':', $incoming)[0]);

        if ($incomingHost !== '' && ! str_starts_with($incomingHost, 'api.')) {
            return $incoming;
        }

        $storefrontHost = parse_url((string) config('storefront.frontend_url', ''), PHP_URL_HOST);

        if (is_string($storefrontHost) && $storefrontHost !== '') {
            return $storefrontHost;
        }

        return $request->getHost();
    }

    private function maybeRewriteHandoffResponse(string $body, string $path, Request $request, string $contentType): string
    {
        if (
            $request->method() !== 'POST'
            || $path !== 'api/sl1e/authorize/handoff'
            || ! str_contains(strtolower($contentType), 'json')
        ) {
            return $body;
        }

        $payload = json_decode($body, true);
        if (! is_array($payload) || empty($payload['qrUrl']) || ! is_string($payload['qrUrl'])) {
            return $body;
        }

        $storefrontOrigin = $this->storefrontOriginForHandoff($request, (string) data_get($payload, 'redirectUri', ''));
        if ($storefrontOrigin === null) {
            return $body;
        }

        $fixedUrl = $this->rewriteHandoffQrUrl($payload['qrUrl'], $storefrontOrigin);
        if ($fixedUrl === null) {
            return $body;
        }

        $handoffId = trim((string) ($payload['handoffId'] ?? ''));
        $qrEncodeUrl = $fixedUrl;

        if ($handoffId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $handoffId) === 1) {
            Cache::put(
                $this->handoffQrCacheKey($handoffId),
                $fixedUrl,
                now()->addSeconds((int) config('simple_l1.handoff_ttl_seconds', 180)),
            );
            $qrEncodeUrl = rtrim($storefrontOrigin, '/').'/h/'.$handoffId;
        }

        $payload['qrUrl'] = $qrEncodeUrl;

        try {
            $svg = QrCode::format('svg')->size(512)->margin(1)->errorCorrection('L')->generate($qrEncodeUrl);
            $payload['qrDataUrl'] = 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (\Throwable) {
            unset($payload['qrDataUrl']);
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $body;
    }

    private function maybeRewriteRegisterOptionsRequest(Request $request, string $path): ?string
    {
        if ($request->method() !== 'POST' || $path !== 'api/sl1e/authorize/register/options') {
            return null;
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return null;
        }

        if ($this->registerOptionsUsername($payload) !== null) {
            return null;
        }

        $username = $this->generatedRegisterUsername($payload);
        abort_if($username === null, 500, 'Could not generate Safe username.');

        $payload['username'] = $username;

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function registerOptionsUsername(array $payload): ?string
    {
        return User::normalizeUsername(
            data_get($payload, 'username')
            ?: data_get($payload, 'usernameCandidate')
            ?: data_get($payload, 'username_candidate')
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generatedRegisterUsername(array $payload): ?string
    {
        $entityAddress = trim((string) (
            data_get($payload, 'entityAddress')
            ?: data_get($payload, 'entity_address')
        ));

        if ($entityAddress !== '') {
            $fromEntity = User::usernameCandidateFromEntityAddress($entityAddress);
            if ($fromEntity !== null) {
                return $fromEntity;
            }
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = User::normalizeUsername('safe_'.bin2hex(random_bytes(4)));
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return User::normalizeUsername('safe_'.dechex(random_int(0, 0xffffff)));
    }

    private function maybeRewriteRegisterOptionsResponse(
        string $body,
        string $path,
        string $originalRequestBody,
        string $requestBody,
        string $contentType,
    ): string {
        if (
            $path !== 'api/sl1e/authorize/register/options'
            || ! str_contains(strtolower($contentType), 'json')
        ) {
            return $body;
        }

        $originalPayload = json_decode($originalRequestBody, true);
        $requestPayload = json_decode($requestBody, true);
        if (! is_array($requestPayload)) {
            return $body;
        }

        $explicitUsername = is_array($originalPayload)
            ? $this->registerOptionsUsername($originalPayload)
            : null;
        $resolvedUsername = $this->registerOptionsUsername($requestPayload);

        if ($resolvedUsername === null) {
            return $body;
        }

        $payload = json_decode($body, true);
        if (! is_array($payload) || ! is_array($payload['options'] ?? null)) {
            return $body;
        }

        $clientName = trim((string) (
            data_get($requestPayload, 'clientName')
            ?: data_get($requestPayload, 'client_name')
            ?: config('simple_l1.client_name', 'Meanly')
        ));
        $safeTitle = trim((string) config('simple_l1.client_safe_title', 'Digital Safe'));

        if ($explicitUsername !== null) {
            $handle = '@'.$resolvedUsername;
            $payload['username'] = $resolvedUsername;
            $payload['options']['user']['name'] = $handle;
            $payload['options']['user']['displayName'] = $clientName.' · '.$handle;
        } else {
            $payload['options']['user']['name'] = $safeTitle;
            $payload['options']['user']['displayName'] = $clientName.' · '.$safeTitle;
            unset($payload['username']);
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $body;
    }

    private function storefrontOriginForHandoff(Request $request, string $redirectUri): ?string
    {
        $storefrontUrl = rtrim((string) config('storefront.frontend_url', ''), '/');
        if ($storefrontUrl !== '') {
            return $storefrontUrl;
        }

        $forwardedHost = trim($this->forwardedBrowserHost($request));
        $browserProviderUrl = SimpleL1IdentityHost::browserProviderUrl($forwardedHost);
        if ($browserProviderUrl !== '') {
            return $browserProviderUrl;
        }

        $redirectHost = is_string($redirectUri) && $redirectUri !== ''
            ? parse_url($redirectUri, PHP_URL_HOST)
            : null;

        if (is_string($redirectHost) && $redirectHost !== '') {
            $redirectScheme = parse_url($redirectUri, PHP_URL_SCHEME) ?: 'https';

            return rtrim($redirectScheme.'://'.$redirectHost, '/');
        }

        $forwardedProto = trim((string) ($request->header('X-Forwarded-Proto') ?: $request->getScheme()));

        return $forwardedHost !== ''
            ? rtrim($forwardedProto.'://'.$forwardedHost, '/')
            : null;
    }

    private function rewriteHandoffQrUrl(string $qrUrl, string $storefrontOrigin): ?string
    {
        $parsed = parse_url($qrUrl);
        if (! is_array($parsed) || empty($parsed['host'])) {
            return null;
        }

        $storefrontHost = strtolower((string) parse_url($storefrontOrigin, PHP_URL_HOST));
        if ($storefrontHost === '') {
            return null;
        }

        $path = $parsed['path'] ?? '/authorize';
        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

        return rtrim($storefrontOrigin, '/').$path.$query;
    }

    private function handoffQrCacheKey(string $handoffId): string
    {
        return 'sl1e:handoff:qr:'.$handoffId;
    }
}
