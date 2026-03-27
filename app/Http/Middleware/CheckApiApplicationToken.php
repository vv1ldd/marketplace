<?php

namespace App\Http\Middleware;

use App\Models\ApiApplication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiApplicationToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized: Token missing'], 401);
        }

        $application = ApiApplication::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (!$application) {
            return response()->json(['message' => 'Unauthorized: Invalid or inactive token'], 401);
        }

        // Store the application in the request context
        $request->attributes->set('api_application', $application);

        return $next($request);
    }
}
