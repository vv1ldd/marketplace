<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class SyncCurrencyRatesBackgroundJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $initiatorUserId,
        public ?string $specificCurrencyCode = null
    ) {}

    public function handle(): void
    {
        // Increase execution time safely just in case the command takes longer on queue
        set_time_limit(300);

        $params = [];
        if ($this->specificCurrencyCode) {
            $params['--code'] = $this->specificCurrencyCode;
        }

        // Execute the heavy command
        $exitCode = Artisan::call('app:update-currency-rates', $params);
        
        // Find the user who triggered this to send them the personalized completion toast
        $user = User::find($this->initiatorUserId);
        
        if ($user) {
            if ($exitCode === 0) {
                Log::info('Currency rates sync completed', ['user_id' => $user->id]);
            } else {
                Log::warning('Currency rates sync failed', ['user_id' => $user->id, 'exit_code' => $exitCode]);
            }
        }
    }
}
