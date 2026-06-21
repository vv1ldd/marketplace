<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class StorefrontFrontendRedirect
{
    public static function baseUrl(): string
    {
        return rtrim((string) config('storefront.frontend_url', config('app.url')), '/');
    }

    public static function mappedPath(Request $request, ?string $path = null): string
    {
        $path = ltrim($path ?? $request->path(), '/');

        if (preg_match('#^store/orders/([^/]+)/safe(.*)$#', $path, $matches)) {
            return 'orders/'.$matches[1].'/safe'.$matches[2];
        }

        return $path;
    }

    public static function url(string $path = '', ?Request $request = null): string
    {
        $path = ltrim($path, '/');
        $url = $path === '' ? self::baseUrl() : self::baseUrl().'/'.$path;

        if ($request !== null && $request->getQueryString()) {
            $url .= '?'.$request->getQueryString();
        }

        return $url;
    }

    public static function toFrontend(Request $request, ?string $path = null, int $status = 302): RedirectResponse
    {
        $mapped = self::mappedPath($request, $path);
        $relative = $mapped === '' ? '/' : '/'.$mapped;
        $query = $request->getQueryString();
        $target = $relative.($query ? '?'.$query : '');

        if (in_array($request->getHost(), (array) config('storefront.api_hosts', []), true)) {
            return redirect()->away(self::baseUrl().$target, $status);
        }

        if (! app()->environment('testing') && self::wouldLoopToSameRequest($request, $target)) {
            abort(503, 'Storefront UI is served by Next.js on port 3001. Stop Laravel on that port and run: cd frontend && npm run dev -- -H 127.0.0.1 -p 3001');
        }

        return redirect($target, $status);
    }

    private static function wouldLoopToSameRequest(Request $request, string $target): bool
    {
        $current = '/'.ltrim($request->path(), '/');
        if ($current === '//') {
            $current = '/';
        }
        if ($current !== '/' && str_ends_with($current, '/')) {
            $current = rtrim($current, '/');
        }

        $normalizedTarget = $target === '' ? '/' : $target;
        if ($normalizedTarget !== '/' && str_contains($normalizedTarget, '?')) {
            [$path, $query] = explode('?', $normalizedTarget, 2);
            $normalizedTarget = ($path === '' ? '/' : $path).'?'.$query;
        }

        return $normalizedTarget === $current
            || $normalizedTarget === $request->getRequestUri();
    }

    public static function away(string $path = '', ?Request $request = null, int $status = 302): RedirectResponse
    {
        return redirect()->away(self::url($path, $request), $status);
    }

    public static function fromRequest(Request $request, int $status = 302): RedirectResponse
    {
        return self::toFrontend($request, null, $status);
    }
}
