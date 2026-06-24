<?php

namespace App\Support;

use Illuminate\Http\Request;

final class StorefrontRequestHost
{
    public static function resolve(Request $request, ?string $explicitHost = null): ?string
    {
        $explicitHost = self::normalizeHost($explicitHost);
        if ($explicitHost !== null) {
            return $explicitHost;
        }

        $fromHeader = self::normalizeHost($request->header('X-Storefront-Host'));
        if ($fromHeader !== null) {
            return $fromHeader;
        }

        $requestHost = self::normalizeHost($request->getHost());
        $forwarded = self::normalizeHost($request->header('X-Forwarded-Host'));

        if ($forwarded !== null && $forwarded !== $requestHost && self::isApiHost($requestHost)) {
            return $forwarded;
        }

        if ($forwarded !== null && ! str_starts_with($forwarded, 'api.')) {
            return $forwarded;
        }

        if ($requestHost !== null && ! self::isApiHost($requestHost)) {
            return $requestHost;
        }

        return null;
    }

    public static function isApiHost(?string $host): bool
    {
        $host = self::normalizeHost($host);
        if ($host === null) {
            return false;
        }

        if (str_starts_with($host, 'api.')) {
            return true;
        }

        return in_array($host, (array) config('storefront.api_hosts', []), true);
    }

    public static function normalizeHost(?string $host): ?string
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return null;
        }

        $parsedHost = parse_url(str_contains($host, '://') ? $host : 'https://'.$host, PHP_URL_HOST);

        $host = strtolower(trim((string) ($parsedHost ?: $host)));

        return $host !== '' ? $host : null;
    }
}
