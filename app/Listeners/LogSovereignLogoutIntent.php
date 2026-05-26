<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\IntentLedgerService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogSovereignLogoutIntent
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if ($event->user) {
            if ($event->user instanceof User) {
                app(IntentLedgerService::class)->record(
                    eventType: 'AUTH_LOGOUT_INTENT',
                    intentType: 'auth.logout',
                    entity: $event->user,
                    payload: [
                        'guard' => $event->guard,
                        'logged_out_at' => now()->toIso8601String(),
                    ],
                    request: request(),
                    user: $event->user,
                    scope: 'auth.session',
                    resource: 'session',
                    triggerSource: 'DID:SYS | AUTH_LOGOUT:#'.$event->user->id,
                );
            }

            Log::channel('daily')->info('SOVEREIGN_INTENT: Logout', [
                'user_id' => $event->user->getAuthIdentifier(),
                'guard' => $event->guard,
                'ts' => now()->toIso8601String()
            ]);
        }
    }
}
