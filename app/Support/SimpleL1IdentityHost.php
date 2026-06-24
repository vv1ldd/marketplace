<?php

namespace App\Support;

class SimpleL1IdentityHost
{
    /**
     * Browser-facing OAuth URLs always use the public storefront host.
     * Next.js rewrites /authorize and /simple-l1/* to the Laravel API host internally.
     */
    public static function browserProviderUrl(?string $appHost = null): string
    {
        $appHost = StorefrontRequestHost::normalizeHost($appHost);

        if ($appHost !== null && ! StorefrontRequestHost::isApiHost($appHost)) {
            return 'https://'.$appHost;
        }

        $explicit = trim((string) config('simple_l1.identity_browser_url', ''));
        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        $configured = rtrim((string) config('simple_l1.identity_provider_url', ''), '/');
        $configuredHost = strtolower((string) parse_url($configured, PHP_URL_HOST));

        if ($configuredHost !== '' && str_starts_with($configuredHost, 'api.')) {
            return rtrim((string) config('storefront.frontend_url', ''), '/');
        }

        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('storefront.frontend_url', config('app.url', '')), '/');
    }
}
