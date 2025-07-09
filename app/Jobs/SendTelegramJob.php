<?php

namespace App\Jobs;

use App\Helpers\SendMessage;
use App\Http\Services\TelegramService;
use App\Models\Order;
use App\Models\OrderItems;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;

class SendTelegramJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly int|string $order_id, private readonly string $status = 'new', private readonly int|string|null $order_item_id = null)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order_id = $this->order_id;
        $status = $this->status;
        $order_item = null;

        if ($this->order_item_id) {
            $order_item = OrderItems::where('id', $this->order_item_id)->first();
        }

        $order = Order::where('order_id', $order_id)->first();

        $message = SendMessage::tg(order: $order, status: $status, order_item: $order_item);

        (new TelegramService())->sendMessage($message);
    }
}
