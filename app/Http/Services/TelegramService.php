<?php

namespace App\Http\Services;

use App\Models\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    /** @var string */
    private string $base_url = 'https://api.telegram.org/bot';
    /** @var PendingRequest */
    private PendingRequest $client;

    public function __construct(?string $token = null)
    {
        $token = $token ?: Settings::get('TG_TOKEN', config('services.tg.token'));
        $this->client = Http::baseUrl($this->base_url . $token);
    }

    /**
     * @param string $message
     * @param string|null $chat_id
     * @return int
     * @throws ConnectionException
     */
    public function sendMessage(string $message, ?string $chat_id = null): int
    {
        $chat_id = $chat_id ?: Settings::get('TG_CHAT_ID', config('services.tg.chat_id'));

        $response = $this->client->post('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('result.message_id');
    }
}
