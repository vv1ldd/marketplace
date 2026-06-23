<?php

namespace App\Services\Identity\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class IdentityGovernanceStreamHandoffService
{
    /**
     * @return array{success: true, handoffId: string, handoffToken: string, qrUrl: string, qrDataUrl?: string}
     */
    public function create(Sl1eAuthorizeRequestContext $context): array
    {
        $handoffId = (string) Str::uuid();
        $handoffToken = Str::lower(Str::random(32));
        $qrUrl = $this->buildAuthorizeUrl($context, $handoffId, $handoffToken);
        $storefrontOrigin = $context->storefrontOrigin();
        $shortUrl = rtrim($storefrontOrigin, '/').'/h/'.$handoffId;

        Cache::put($this->qrCacheKey($handoffId), $qrUrl, now()->addSeconds($this->ttlSeconds()));
        Cache::put($this->sessionCacheKey($handoffId), [
            'handoff_token' => $handoffToken,
            'status' => 'pending',
            'authorize' => [
                'client_id' => $context->clientId,
                'client_name' => $context->clientName,
                'redirect_uri' => $context->redirectUri,
                'state' => $context->state,
                'nonce' => $context->nonce,
                'mode' => $context->mode,
                'scope' => $context->scope,
            ],
        ], now()->addSeconds($this->ttlSeconds()));

        $payload = [
            'success' => true,
            'handoffId' => $handoffId,
            'handoffToken' => $handoffToken,
            'qrUrl' => $shortUrl,
        ];

        try {
            $svg = QrCode::format('svg')->size(512)->margin(1)->errorCorrection('L')->generate($shortUrl);
            $payload['qrDataUrl'] = 'data:image/svg+xml;base64,'.base64_encode($svg);
        } catch (\Throwable) {
            // QR rendering is optional; the client can render from qrUrl.
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function poll(string $handoffId): array
    {
        $session = Cache::get($this->sessionCacheKey($handoffId));

        if (! is_array($session)) {
            return ['status' => 'expired'];
        }

        $status = (string) ($session['status'] ?? 'pending');

        if ($status === 'completed') {
            return [
                'status' => 'completed',
                'redirectUrl' => (string) ($session['redirect_url'] ?? ''),
                'entityAddress' => (string) ($session['entity_address'] ?? ''),
            ];
        }

        return ['status' => 'pending'];
    }

    public function complete(string $handoffId, string $handoffToken, string $entityAddress, string $redirectUrl): void
    {
        $cacheKey = $this->sessionCacheKey($handoffId);
        $session = Cache::get($cacheKey);

        if (! is_array($session)) {
            throw new HttpException(422, 'Handoff session expired.');
        }

        $expectedToken = (string) ($session['handoff_token'] ?? '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $handoffToken)) {
            throw new HttpException(403, 'Handoff token mismatch.');
        }

        Cache::put($cacheKey, array_merge($session, [
            'status' => 'completed',
            'entity_address' => strtolower($entityAddress),
            'redirect_url' => $redirectUrl,
        ]), now()->addSeconds($this->ttlSeconds()));
    }

    private function buildAuthorizeUrl(
        Sl1eAuthorizeRequestContext $context,
        string $handoffId,
        string $handoffToken,
    ): string {
        $query = http_build_query(array_filter([
            'client_id' => $context->clientId,
            'client_name' => $context->clientName,
            'redirect_uri' => $context->redirectUri,
            'scope' => $context->scope,
            'state' => $context->state,
            'nonce' => $context->nonce,
            'mode' => $context->mode,
            'handoff_id' => $handoffId,
            'handoff_token' => $handoffToken,
        ], fn ($value) => is_string($value) && $value !== ''));

        return rtrim($context->storefrontOrigin(), '/').'/authorize?'.$query;
    }

    private function sessionCacheKey(string $handoffId): string
    {
        return 'identity-governance:handoff:'.$handoffId;
    }

    private function qrCacheKey(string $handoffId): string
    {
        return 'sl1e:handoff:qr:'.$handoffId;
    }

    private function ttlSeconds(): int
    {
        return (int) config('simple_l1.handoff_ttl_seconds', 180);
    }
}
