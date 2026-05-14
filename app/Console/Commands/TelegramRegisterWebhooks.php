<?php

namespace App\Console\Commands;

use App\Models\DirectChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramRegisterWebhooks extends Command
{
    protected $signature = 'telegram:register-webhooks {domain : The public domain of your app (e.g. https://my-market.com)}';
    protected $description = 'Register webhooks for all active Telegram Bot channels';

    public function handle()
    {
        $domain = rtrim($this->argument('domain'), '/');
        $channels = DirectChannel::where('type', 'telegram_bot')->where('is_active', true)->get();

        if ($channels->isEmpty()) {
            $this->warn('No active Telegram channels found.');
            return;
        }

        foreach ($channels as $channel) {
            $settings = $channel->settings ?? [];
            $token = $settings['telegram_bot_token'] ?? null;

            if ($token) {
                $webhookUrl = "{$domain}/api/telegram/webhook/{$token}";
                $response = Http::get("https://api.telegram.org/bot{$token}/setWebhook", [
                    'url' => $webhookUrl
                ]);

                if ($response->successful()) {
                    $this->info("Successfully registered webhook for channel ID {$channel->id} to {$webhookUrl}");
                } else {
                    $this->error("Failed to register webhook for channel ID {$channel->id}: " . $response->body());
                }
            }
        }
    }
}
