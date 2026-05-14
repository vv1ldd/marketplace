<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyLedgerIntegrityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ledger-verify {--shop= : ID конкретного магазина для проверки}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверка криптографической целостности Sovereign Ledger (MDK Guard)';

    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledgerService)
    {
        $shopId = $this->option('shop');
        $shops = $shopId ? Shop::where('id', $shopId)->get() : Shop::where('is_active', true)->get();

        $this->info("Начинаю аудит " . $shops->count() . " магазинов...");

        foreach ($shops as $shop) {
            $this->comment("Проверка магазина: {$shop->name} (ID: {$shop->id})");
            
            $report = $ledgerService->verifyIntegrity($shop);

            if ($report['valid']) {
                $this->info("✅ Целостность подтверждена. Проверено событий: {$report['count']}");
            } else {
                $this->error("❌ ОБНАРУЖЕНО НАРУШЕНИЕ ЦЕЛОСТНОСТИ!");
                foreach ($report['errors'] as $error) {
                    $this->line("  - $error");
                }

                // Логируем критическую ошибку
                Log::critical("MDK INTEGRITY VIOLATION for Shop {$shop->id}", [
                    'shop' => $shop->name,
                    'errors' => $report['errors']
                ]);

                // Здесь можно добавить отправку уведомления в Telegram админу
            }
        }

        $this->info("Аудит завершен.");
    }
}
