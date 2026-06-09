<?php

namespace App\Http\Middleware;

use App\Services\MeanlyAnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackMeanlyAnalyticsRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = $request->headers->get('X-Request-Id') ?: (string) str()->uuid();
        $request->attributes->set('meanly_request_id', $requestId);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            app(MeanlyAnalyticsService::class)->trackException($exception, 'http.exception', [
                'query_keys' => array_keys($request->query()),
                'input_keys' => array_keys($request->except(['_token'])),
            ], [
                'event_type' => 'http',
                'surface' => $this->surface($request),
                'request_id' => $requestId,
                'status_code' => 500,
                'duration_ms' => $durationMs,
                'is_slow' => $durationMs >= 1200,
            ]);

            throw $exception;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $statusCode = $response->getStatusCode();

        if (! $this->shouldSkip($request)) {
            app(MeanlyAnalyticsService::class)->track($this->eventName($statusCode, $durationMs), [
                'query_keys' => array_keys($request->query()),
                'input_keys' => $request->isMethodSafe() ? [] : array_keys($request->except(['_token'])),
                'content_type' => $request->headers->get('content-type'),
                'accept' => $request->headers->get('accept'),
                'referer_path' => $this->refererPath($request),
            ], [
                'event_type' => 'http',
                'surface' => $this->surface($request),
                'request_id' => $requestId,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'is_slow' => $durationMs >= 1200,
            ]);
        }

        $response->headers->set('X-Meanly-Request-Id', $requestId);

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        return $path === 'up'
            || str_starts_with($path, '_debugbar')
            || str_starts_with($path, 'build/')
            || str_starts_with($path, 'storage/')
            || str_starts_with($path, 'vendor/')
            || str_ends_with($path, '.css')
            || str_ends_with($path, '.js')
            || str_ends_with($path, '.map')
            || str_ends_with($path, '.png')
            || str_ends_with($path, '.jpg')
            || str_ends_with($path, '.jpeg')
            || str_ends_with($path, '.svg')
            || str_ends_with($path, '.ico');
    }

    private function eventName(int $statusCode, int $durationMs): string
    {
        if ($statusCode >= 500) {
            return 'http.server_error';
        }

        if ($statusCode >= 400) {
            return 'http.client_error';
        }

        if ($durationMs >= 1200) {
            return 'http.slow_request';
        }

        return 'http.request';
    }

    private function surface(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            str_starts_with($path, '/meanly-ai') || str_contains($routeName, 'chat') => 'ai',
            str_starts_with($path, '/store') || str_starts_with($path, '/catalog') || $path === '/' => 'storefront',
            str_starts_with($path, '/merchant') || str_starts_with($path, '/partner') => 'b2b',
            str_starts_with($path, '/api') => 'api',
            str_starts_with($path, '/admin') || str_starts_with($path, '/ops') => 'ops',
            default => 'app',
        };
    }

    private function refererPath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }
}
