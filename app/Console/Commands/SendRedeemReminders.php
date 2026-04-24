<?php

namespace App\Console\Commands;

use App\Models\Order\OrderItems;
use App\Http\Services\YmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRedeemReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redeem:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a reminder message to customers who started redemption but did not finish.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find items that started redeem > 30 minutes ago, are not activated, and haven't had a reminder sent yet.
        $stuckItems = OrderItems::where('is_activated', false)
            ->whereNotNull('redeem_started_at')
            ->where('redeem_started_at', '<=', now()->subMinutes(30))
            ->whereNull('reminder_sent_at')
            ->get();

        $this->info("Found " . $stuckItems->count() . " stuck redemptions.");

        foreach ($stuckItems as $item) {
            $order = $item->order;
            
            if (!$order || !$order->chat_id) {
                continue;
            }

            try {
                $service = new YmService($order->shop);
                
                $message = "Здравствуйте! Мы заметили, что вы начали активацию вашего ваучера (код: " . $item->key . "), но не завершили процесс. \n\nЕсли у вас возникли технические сложности или вопросы — просто напишите ответ в этот чат, и мы вам поможем!";
                
                $service->sendMessage($order->chat_id, $message);
                
                $item->update(['reminder_sent_at' => now()]);
                
                $order->comments()->create([
                    'comment' => "Отправлено напоминание о незавершенной активации в чат Яндекс.Маркета"
                ]);
                
                $this->info("Reminder sent for Order ID: {$order->order_id}");
                
            } catch (\Exception $e) {
                Log::error("Failed to send redeem reminder for order {$order->id}: " . $e->getMessage());
            }
        }
    }
}
