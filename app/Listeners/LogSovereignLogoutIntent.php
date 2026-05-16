<?php

namespace App\Listeners;

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
            Log::channel('daily')->info('SOVEREIGN_INTENT: Logout', [
                'user_id' => $event->user->getAuthIdentifier(),
                'guard' => $event->guard,
                'ts' => now()->toIso8601String()
            ]);
        }
    }
}
