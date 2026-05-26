<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\IntentLedgerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPartnerActionIntent
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('POST') || $response->getStatusCode() >= 400) {
            return $response;
        }

        $routeName = (string) $request->route()?->getName();
        $intentType = $this->intentType($routeName);
        $user = $request->user();

        if ($intentType && $user instanceof User) {
            app(IntentLedgerService::class)->record(
                eventType: 'PARTNER_ACTION_INTENT',
                intentType: $intentType,
                entity: $user,
                payload: [
                    'route_name' => $routeName,
                    'request_payload_hash' => hash('sha256', json_encode($request->except(['_token', 'assertion', 'passkey_assertion', 'password']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                    'response_status' => $response->getStatusCode(),
                    'performed_at' => now()->toIso8601String(),
                ],
                request: $request,
                user: $user,
                scope: 'partner.dashboard',
                resource: $routeName,
            );
        }

        return $response;
    }

    private function intentType(string $routeName): ?string
    {
        return match ($routeName) {
            'partner.dashboard.storefront.add_to_catalog',
            'partner.dashboard.catalog.toggle' => 'catalog.publish',
            'partner.dashboard.storefront.buy_once',
            'partner.dashboard.finance.sovereign_request.create' => 'stock.procure',
            'partner.dashboard.shop.yandex_market',
            'partner.dashboard.api_app.store' => 'provider.connect',
            'partner.dashboard.shop.create' => 'business.shop.create',
            'partner.dashboard.tickets.create' => 'support.ticket.open',
            'partner.dashboard.tickets.reply' => 'support.ticket.reply',
            'partner.dashboard.deposit_intent' => 'payout.request',
            'partner.dashboard.invite_intent' => 'legal_entity.invite_member',
            default => null,
        };
    }
}
