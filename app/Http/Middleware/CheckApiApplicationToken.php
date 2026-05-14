<?php

namespace App\Http\Middleware;

use App\Models\ApiApplication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiApplicationToken
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$scopes  `shop` — только токен магазина (redeem); `ledger` — магазин (данные своего магазина) или platform (полный леджер).
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthorized: Token missing'], 401);
        }

        $application = ApiApplication::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Unauthorized: Invalid or inactive token'], 401);
        }

        $mode = $scopes[0] ?? '';

        if ($mode === 'shop') {
            if ($application->type !== ApiApplication::TYPE_SHOP || ! $application->shop_id) {
                return response()->json([
                    'message' => 'Этот API доступен только токеном магазина (настройки продавца). Платформенный токен сюда не подходит.',
                ], 403);
            }
        }

        if ($mode === 'ledger') {
            if ($application->type === ApiApplication::TYPE_SHOP) {
                if (! $application->shop_id) {
                    return response()->json(['message' => 'У приложения не указан магазин'], 403);
                }
                $request->attributes->set('ledger_shop_id', (int) $application->shop_id);
            } elseif ($application->type !== ApiApplication::TYPE_PLATFORM) {
                return response()->json(['message' => 'Недопустимый тип токена для леджера'], 403);
            }
        }

        $request->attributes->set('api_application', $application);

        return $next($request);
    }
}
