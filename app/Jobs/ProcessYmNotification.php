<?php

namespace App\Jobs;

use App\Http\Controllers\OrderController;
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

        $orderController = new OrderController($type);

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
    }
}
