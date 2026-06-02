<?php

namespace App\Jobs;

use App\Exceptions\DuplicateMutationException;
use App\Http\Controllers\OrderController;
use App\Services\Mutation\MutationContext;
use App\Services\Mutation\MutationDedupGuard;
use App\Services\Mutation\MutationIdentityResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessYmNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $type = $this->data['notificationType'] ?? 'UNKNOWN';
        Log::info("Processing YM Notification in background: {$type}", ['orderId' => $this->data['orderId'] ?? null]);

        $identity = $this->data['_mutation_context'] ?? app(MutationIdentityResolver::class)->fromJob(
            job: static::class,
            action: 'provider.yandex.notification',
            entityType: 'provider_event',
            entityId: $this->data['orderId'] ?? ($this->data['notificationType'] ?? 'unknown'),
            idempotencyKey: implode(':', array_filter([
                'ym',
                (string) ($this->data['notificationType'] ?? 'unknown'),
                (string) ($this->data['campaignId'] ?? 'unknown'),
                (string) ($this->data['orderId'] ?? 'none'),
                (string) ($this->data['status'] ?? 'none'),
                (string) ($this->data['substatus'] ?? 'none'),
            ])),
            payload: $this->data,
            mutationPath: 'provider.yandex.payment_callback',
        );

        try {
            app(MutationDedupGuard::class)->check(
                identity: $identity,
                mutationPath: 'provider.yandex.payment_callback.job',
                mode: (string) config('mutation.webhook_guard_mode', 'shadow'),
                guardKey: 'job:yandex:'.($identity['mutation_id'] ?? sha1(json_encode($this->data))),
                metadata: ['job' => static::class],
            );
        } catch (DuplicateMutationException $e) {
            Log::warning('Duplicate YM notification job rejected by mutation guard', [
                'mutation_id' => $e->mutationId,
                'order_id' => $this->data['orderId'] ?? null,
            ]);

            return;
        }

        $orderController = new OrderController($type);

        MutationContext::bind($identity, function () use ($type, $orderController): void {
            switch ($type) {
                case 'ORDER_CREATED':
                    $orderController->created($this->data);
                    break;
                case 'ORDER_STATUS_UPDATED':
                    $orderController->updated($this->data);
                    break;
                case 'CHAT_ARBITRAGE_FINISHED':
                    $orderController->arbitrageFinished($this->data);
                    break;
                default:
                    Log::warning("Unhandled notification type in background job: {$type}");
                    break;
            }
        });
    }
}
