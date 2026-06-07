<?php

namespace App\Http\Middleware;

use App\Services\StorefrontTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStorefrontToken
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $session = app(StorefrontTokenService::class)->resolve($request->bearerToken());
        if (! $session) {
            return response()->json([
                'message' => 'Unauthorized: Storefront token missing or invalid.',
            ], 401);
        }

        foreach ($scopes as $scope) {
            if (! in_array($scope, data_get($session, 'scopes', []), true)) {
                return response()->json([
                    'message' => 'Forbidden: Storefront token scope is insufficient.',
                ], 403);
            }
        }

        $request->attributes->set('storefront_token_session', $session);
        $request->attributes->set('storefront_identity', data_get($session, 'identity'));

        return $next($request);
    }
}
