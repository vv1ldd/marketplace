<?php

namespace App\Console\Commands;

use App\Exceptions\DuplicateMutationException;
use App\Models\Order\OrderItems;
use App\Services\Mutation\MutationContext;
use App\Services\Mutation\MutationDedupGuard;
use App\Services\Mutation\MutationIdentityResolver;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedPurchases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:retry-failed-purchases {--limit=10 : Max items to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically retry failed auto-purchases for order items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $orderService = new OrderService();

        // 1. Find items that failed and haven't exceeded retry limit
        // We only retry if at least 30 minutes passed since the last update
        $failedItems = OrderItems::where('purchase_status', 'failed')
            ->where('purchase_retry_count', '<', 5)
            ->where('updated_at', '<=', now()->subMinutes(30))
            ->limit($limit)
            ->get();

        if ($failedItems->isEmpty()) {
            $this->info('No failed purchases found for retry.');
            return;
        }

        $this->info("Found {$failedItems->count()} items to retry. Processing...");

        foreach ($failedItems as $item) {
            $this->info("Retrying Item ID: {$item->id} (SKU: {$item->sku}, Attempt: " . ($item->purchase_retry_count + 1) . ")");
            
            try {
                $identity = app(MutationIdentityResolver::class)->resolve(
                    actor: 'cli:retry-failed-purchases',
                    action: 'payment.retry',
                    entityType: 'order_item',
                    entityId: $item->id,
                    idempotencyKey: 'retry:order_item:'.$item->id,
                    context: [
                        'order_id' => $item->order_id,
                        'sku' => (string) $item->sku,
                        'attempt' => (int) $item->purchase_retry_count + 1,
                    ],
                    mutationPath: 'payment.retry_job',
                );

                $decision = app(MutationDedupGuard::class)->check(
                    identity: $identity,
                    mutationPath: 'payment.retry_job',
                    mode: (string) config('mutation.retry_guard_mode', 'shadow'),
                    guardKey: 'retry:order_item:'.$item->id,
                    metadata: ['command' => $this->getName()],
                );

                $result = MutationContext::bind($identity, function () use ($item, $orderService): array {
                    // Increment retry count before attempt to avoid stuck items if it crashes
                    $item->increment('purchase_retry_count');

                    return $orderService->retryAutozakup($item);
                });

                app(MutationDedupGuard::class)->complete($decision['guard_key']);

                if ($result['success']) {
                    $this->info("✅ Successfully purchased: {$item->sku}");
                    Log::info("Automated Retry Success: Item ID {$item->id}");

                    // Логируем успех ретрая в историю заказа
                    $item->order?->comments()->create([
                        'user_id' => null, // Робот
                        'comment' => "🔄 Автоматический ретрай: Код успешно куплен и выдан."
                    ]);

                    // Проверяем, остались ли другие проблемные айтемы
                    $hasOtherFailed = OrderItems::where('order_id', $item->order_id)
                        ->where('purchase_status', 'failed')
                        ->exists();

                    if (!$hasOtherFailed) {
                        $item->order?->update(['is_problem' => false]);
                    }

                    // ПРОВЕРЯЕМ: Если все товары в заказе теперь имеют коды — закрываем заказ (статус 4)
                    $allItemsCompleted = OrderItems::where('order_id', $item->order_id)
                        ->whereNull('original_code')
                        ->doesntExist();

                    if ($allItemsCompleted) {
                        $item->order?->update(['progress_id' => 4]);
                    }

                } else {
                    $this->error("❌ Failed again: " . ($result['error'] ?? 'Unknown error'));
                    Log::warning("Automated Retry Failed: Item ID {$item->id} - " . ($result['error'] ?? 'Unknown error'));
                    
                    // Помечаем как проблемный
                    $item->order?->update(['is_problem' => true]);
                }
            } catch (\Exception $e) {
                if ($e instanceof DuplicateMutationException) {
                    $this->warn("Duplicate retry rejected for item {$item->id}: {$e->mutationId}");

                    continue;
                }

                $this->error("💥 Exception during retry: " . $e->getMessage());
                Log::error("Automated Retry Exception: Item ID {$item->id} - " . $e->getMessage());
            }
        }

        $this->info('Auto-retry process completed.');
    }
}
