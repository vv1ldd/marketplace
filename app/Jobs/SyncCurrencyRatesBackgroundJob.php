<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Filament\Notifications\Notification;

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
                Notification::make()
                    ->title('🎉 Обновление валют завершено!')
                    ->body('Все котировки успешно синхронизированы через Sovereign Liquidity Engine.')
                    ->success()
                    ->sendToDatabase($user);
            } else {
                Notification::make()
                    ->title('🚨 Ошибка обновления валют')
                    ->body('В ходе фоновой синхронизации произошел сбой. Проверьте системные логи.')
                    ->danger()
                    ->sendToDatabase($user);
            }
        }
    }
}
