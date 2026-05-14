<?php

namespace App\Support;

/**
 * URL к файлам из public, сохранённые в БД с продакшен-хостом (копия БД на local).
 * При включённом rewrite отдаём относительный путь под локальный public.
 */
final class PublicAssetUrl
{
    /**
     * @return non-falsy-string|null путь вида img/card/... без ведущего /
     */
    public static function relativePathFromRemoteOwnHost(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return null;
        }

        if (! config('app.rewrite_remote_asset_urls', false)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        $allowed = config('app.remote_asset_hosts', []);
        $allowed = array_map('strtolower', $allowed);
        if (! in_array($host, $allowed, true)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || $path === '/') {
            return null;
        }

        $relative = ltrim($path, '/');

        return $relative !== '' ? $relative : null;
    }
}
